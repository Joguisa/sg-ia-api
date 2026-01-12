<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Services\ValidationService;
use Src\Services\GameService;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;
use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;
use Src\Repositories\Interfaces\QuestionBatchRepositoryInterface;
use Src\Repositories\Interfaces\CategoryRepositoryInterface;
use Src\Repositories\Interfaces\AdminRepositoryInterface;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

final class AdminController {
  public function __construct(
    private QuestionRepositoryInterface $questions,
    private ?SystemPromptRepositoryInterface $prompts = null,
    private ?GameService $gameService = null,
    private ?QuestionBatchRepositoryInterface $batchRepo = null,
    private ?CategoryRepositoryInterface $categoryRepo = null,
    private ?AdminRepositoryInterface $adminRepo = null
  ) {}

  /**
   * Actualizar el texto/enunciado de una pregunta
   *
   * Endpoint: PUT /admin/questions/{id}
   * Body: { "statement": "Nuevo enunciado de la pregunta" }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function updateQuestion(array $params): void {
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['statement']);

    $statement = trim($data['statement']);

    if (empty($statement)) {
      Response::json(['ok'=>false,'error'=>Translations::get('question_statement_empty', $lang)], 400);
      return;
    }

    if (strlen($statement) < 10) {
      Response::json(['ok'=>false,'error'=>Translations::get('question_statement_too_short', $lang)], 400);
      return;
    }

    if (strlen($statement) > 1000) {
      Response::json(['ok'=>false,'error'=>Translations::get('question_statement_too_long', $lang)], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok'=>false,'error'=>Translations::get('question_not_found', $lang)], 404);
        return;
      }

      // Actualizar statement manteniendo el estado de verificación actual
      $this->questions->update($questionId, $statement, $question->adminVerified);

      Response::json([
        'ok' => true,
        'message' => Translations::get('question_updated', $lang),
        'question' => [
          'id' => $question->id,
          'statement' => $statement,
          'difficulty' => $question->difficulty,
          'category_id' => $question->categoryId,
          'is_ai_generated' => $question->isAiGenerated,
          'admin_verified' => $question->adminVerified
        ]
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('failed_to_update_question', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene una pregunta completa con opciones y explicaciones
   *
   * Endpoint: GET /admin/questions/{id}/full
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function getQuestionFull(array $params): void {
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    try {
      $question = $this->questions->getFullQuestion($questionId);

      if (!$question) {
        Response::json(['ok' => false, 'error' => Translations::get('question_not_found', $lang)], 404);
        return;
      }

      Response::json([
        'ok' => true,
        'question' => $question
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_fetch_question', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Actualiza una pregunta completa (enunciado, opciones, explicaciones)
   *
   * Endpoint: PUT /admin/questions/{id}/full
   * Body: {
   *   "statement": "Nuevo enunciado",
   *   "difficulty": 1-5,
   *   "category_id": int,
   *   "options": [
   *     {"text": "Opción A", "is_correct": false},
   *     {"text": "Opción B", "is_correct": true},
   *     {"text": "Opción C", "is_correct": false},
   *     {"text": "Opción D", "is_correct": false}
   *   ],
   *   "explanation_correct": "Explicación cuando acierta",
   *   "explanation_incorrect": "Explicación cuando falla"
   * }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function updateQuestionFull(array $params): void {
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validar campos requeridos
    ValidationService::requireFields($data, ['statement', 'difficulty', 'category_id', 'options']);

    // Validar enunciado
    $statement = trim($data['statement']);
    if (empty($statement) || strlen($statement) < 10) {
      Response::json(['ok' => false, 'error' => Translations::get('question_statement_too_short', $lang)], 400);
      return;
    }

    if (strlen($statement) > 1000) {
      Response::json(['ok' => false, 'error' => Translations::get('question_statement_too_long', $lang)], 400);
      return;
    }

    // Validar dificultad
    $difficulty = (int)$data['difficulty'];
    if ($difficulty < 1 || $difficulty > 5) {
      Response::json(['ok' => false, 'error' => Translations::get('difficulty_invalid', $lang)], 400);
      return;
    }

    // Validar opciones
    $options = $data['options'];
    if (!is_array($options) || count($options) < 2 || count($options) > 6) {
      Response::json(['ok' => false, 'error' => Translations::get('options_count_invalid', $lang)], 400);
      return;
    }

    $correctCount = 0;
    foreach ($options as $opt) {
      if (!isset($opt['text']) || empty(trim($opt['text']))) {
        Response::json(['ok' => false, 'error' => Translations::get('options_must_have_text', $lang)], 400);
        return;
      }
      if (isset($opt['is_correct']) && $opt['is_correct']) {
        $correctCount++;
      }
    }

    if ($correctCount !== 1) {
      Response::json(['ok' => false, 'error' => Translations::get('must_have_one_correct', $lang)], 400);
      return;
    }

    try {
      // Verificar que la pregunta existe
      $existingQuestion = $this->questions->find($questionId);
      if (!$existingQuestion) {
        Response::json(['ok' => false, 'error' => Translations::get('question_not_found', $lang)], 404);
        return;
      }

      // Preparar datos para actualización
      $updateData = [
        'statement' => $statement,
        'difficulty' => $difficulty,
        'category_id' => (int)$data['category_id'],
        'options' => $options
      ];

      // Agregar explicaciones si se proporcionan
      if (isset($data['explanation_correct'])) {
        $updateData['explanation_correct'] = $data['explanation_correct'];
      }
      if (isset($data['explanation_incorrect'])) {
        $updateData['explanation_incorrect'] = $data['explanation_incorrect'];
      }

      // Ejecutar actualización
      $success = $this->questions->updateFull($questionId, $updateData);

      if (!$success) {
        Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_question', $lang)], 500);
        return;
      }

      // Obtener pregunta actualizada
      $updatedQuestion = $this->questions->getFullQuestion($questionId);

      Response::json([
        'ok' => true,
        'message' => Translations::get('question_updated_verification_reset', $lang),
        'question' => $updatedQuestion
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_question', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Marcar una pregunta como verificada por el administrador
   *
   * Endpoint: PATCH /admin/questions/{id}/verify
   * Body: { "verified": true|false }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function verifyQuestion(array $params): void {
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['verified']);

    $verified = (bool)$data['verified'];

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok'=>false,'error'=>Translations::get('question_not_found', $lang)], 404);
        return;
      }

      // Actualizar estado de verificación manteniendo el statement actual
      $this->questions->update($questionId, $question->statement, $verified);

      Response::json([
        'ok' => true,
        'message' => Translations::get('question_verification_updated', $lang),
        'question' => [
          'id' => $question->id,
          'statement' => $question->statement,
          'admin_verified' => $verified
        ]
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('failed_to_verify_question', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener configuración de prompt de IA
   *
   * Endpoint: GET /admin/config/prompt
   *
   * @return void
   */
  public function getPromptConfig(): void {
    $lang = LanguageDetector::detect();
    if (!$this->prompts) {
      Response::json(['ok'=>false,'error'=>Translations::get('prompt_repo_unavailable', $lang)], 500);
      return;
    }

    $prompt = $this->prompts->getActive();

    if (!$prompt) {
      Response::json(['ok'=>false,'error'=>Translations::get('no_active_prompt', $lang)], 404);
      return;
    }

    Response::json([
      'ok' => true,
      'prompt' => [
        'id' => $prompt->id,
        'prompt_text' => $prompt->promptText,
        'temperature' => $prompt->temperature,
        'is_active' => $prompt->isActive,
        'preferred_ai_provider' => $prompt->preferredAiProvider,
        'max_questions_per_game' => $prompt->maxQuestionsPerGame
      ]
    ], 200);
  }

  /**
   * Actualizar configuración de prompt de IA
   *
   * Endpoint: PUT /admin/config/prompt
   * Body: { "prompt_text": "...", "temperature": 0.7, "preferred_ai_provider": "gemini" (opcional) }
   *
   * @return void
   */
  public function updatePromptConfig(): void {
    $lang = LanguageDetector::detect();
    if (!$this->prompts) {
      Response::json(['ok'=>false,'error'=>Translations::get('prompt_repo_unavailable', $lang)], 500);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['prompt_text', 'temperature']);

    $promptText = trim($data['prompt_text']);
    $temperature = (float)$data['temperature'];
    $preferredProvider = $data['preferred_ai_provider'] ?? null;
    $maxQuestions = isset($data['max_questions_per_game']) ? (int)$data['max_questions_per_game'] : 15;

    if (empty($promptText)) {
      Response::json(['ok'=>false,'error'=>Translations::get('prompt_text_empty', $lang)], 400);
      return;
    }

    if ($temperature < 0 || $temperature > 1) {
      Response::json(['ok'=>false,'error'=>Translations::get('temperature_invalid', $lang)], 400);
      return;
    }

    // Validar max_questions
    if ($maxQuestions < 5 || $maxQuestions > 100) {
      Response::json([
        'ok' => false,
        'error' => Translations::get('max_questions_invalid', $lang)
      ], 400);
      return;
    }

    // Validar provider si se proporciona
    if ($preferredProvider !== null) {
      $validProviders = ['auto', 'gemini', 'groq', 'deepseek', 'fireworks'];
      if (!in_array($preferredProvider, $validProviders)) {
        Response::json([
          'ok' => false,
          'error' => Translations::get('invalid_ai_provider', $lang) . ': ' . implode(', ', $validProviders)
        ], 400);
        return;
      }
    }

    try {
      // Usar updateWithProvider si se proporciona el provider, sino usar update
      if ($preferredProvider !== null) {
        $this->prompts->updateWithProvider($promptText, $temperature, $preferredProvider, $maxQuestions);
      } else {
        $this->prompts->update($promptText, $temperature);
      }

      Response::json([
        'ok' => true,
        'message' => Translations::get('prompt_updated', $lang)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('failed_to_update_prompt', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Crear una nueva categoría
   *
   * Endpoint: POST /admin/categories
   * Body: { "name": "...", "description": "..." }
   *
   * @return void
   */
  public function createCategory(): void {
    $lang = LanguageDetector::detect();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['name']);

    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');

    if (empty($name)) {
      Response::json(['ok'=>false,'error'=>Translations::get('category_name_empty', $lang)], 400);
      return;
    }

    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      $stmt = $pdo->prepare("INSERT INTO question_categories (name, description) VALUES (?, ?)");
      $stmt->execute([$name, $description ?: null]);

      $categoryId = (int)$pdo->lastInsertId();

      Response::json([
        'ok' => true,
        'category_id' => $categoryId,
        'message' => Translations::get('category_created', $lang)
      ], 201);
    } catch (\Exception $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_name_exists', $lang)], 409);
      } else {
        Response::json(['ok'=>false,'error'=>Translations::get('failed_to_create_category', $lang) . ': ' . $e->getMessage()], 500);
      }
    }
  }

  /**
   * Actualizar una categoría
   *
   * Endpoint: PUT /admin/categories/{id}
   * Body: { "name": "...", "description": "..." }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function updateCategory(array $params): void {
    $lang = LanguageDetector::detect();
    $categoryId = (int)($params['id'] ?? 0);

    if ($categoryId <= 0) {
      Response::json(['ok'=>false,'error'=>Translations::get('invalid_category_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['name']);

    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');

    if (empty($name)) {
      Response::json(['ok'=>false,'error'=>Translations::get('category_name_empty', $lang)], 400);
      return;
    }

    try {
      if (!$this->categoryRepo) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_repo_unavailable', $lang)], 500);
        return;
      }

      // Verificar que la categoría existe
      $existingCategory = $this->categoryRepo->find($categoryId);
      if (!$existingCategory) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_not_found', $lang)], 404);
        return;
      }

      // Actualizar categoría
      $success = $this->categoryRepo->update($categoryId, [
        'name' => $name,
        'description' => $description ?: null
      ]);

      if (!$success) {
        Response::json(['ok'=>false,'error'=>Translations::get('failed_to_update_category', $lang)], 500);
        return;
      }

      Response::json([
        'ok' => true,
        'category_id' => $categoryId,
        'message' => Translations::get('category_updated', $lang)
      ], 200);
    } catch (\Exception $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_name_exists', $lang)], 409);
      } else {
        Response::json(['ok'=>false,'error'=>Translations::get('failed_to_update_category', $lang) . ': ' . $e->getMessage()], 500);
      }
    }
  }

  /**
   * Eliminar una categoría
   *
   * Endpoint: DELETE /admin/categories/{id}
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function deleteCategory(array $params): void {
    $lang = LanguageDetector::detect();
    $categoryId = (int)($params['id'] ?? 0);

    if ($categoryId <= 0) {
      Response::json(['ok'=>false,'error'=>Translations::get('invalid_category_id', $lang)], 400);
      return;
    }

    try {
      if (!$this->categoryRepo) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_repo_unavailable', $lang)], 500);
        return;
      }

      // Verificar que la categoría existe
      $existingCategory = $this->categoryRepo->find($categoryId);
      if (!$existingCategory) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_not_found', $lang)], 404);
        return;
      }

      // Intentar eliminar
      $success = $this->categoryRepo->delete($categoryId);

      if (!$success) {
        Response::json(['ok'=>false,'error'=>Translations::get('failed_to_delete_category', $lang)], 500);
        return;
      }

      Response::json([
        'ok' => true,
        'message' => Translations::get('category_deleted', $lang)
      ], 200);
    } catch (\Exception $e) {
      if (str_contains($e->getMessage(), 'preguntas asociadas')) {
        Response::json(['ok'=>false,'error'=>Translations::get('category_has_questions', $lang)], 409);
      } else {
        Response::json(['ok'=>false,'error'=>Translations::get('failed_to_delete_category', $lang) . ': ' . $e->getMessage()], 500);
      }
    }
  }

  /**
   * Generar preguntas en batch usando IA
   *
   * Endpoint: POST /admin/generate-batch
   * Body: { "quantity": 5, "category_id": 1, "difficulty": 3, "language": "es" }
   *
   * @return void
   */
  public function generateBatch(): void {
    $lang = LanguageDetector::detect();
    if (!$this->gameService) {
      Response::json(['ok'=>false,'error'=>Translations::get('game_service_unavailable', $lang)], 500);
      return;
    }

    if (!$this->batchRepo) {
      Response::json(['ok'=>false,'error'=>Translations::get('batch_repo_unavailable', $lang)], 500);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['quantity', 'category_id', 'difficulty']);

    $quantity = (int)$data['quantity'];
    $categoryId = (int)$data['category_id'];
    $difficulty = (int)$data['difficulty'];
    $language = $data['language'] ?? 'es';

    if ($quantity <= 0 || $quantity > 50) {
      Response::json(['ok'=>false,'error'=>Translations::get('quantity_invalid', $lang)], 400);
      return;
    }

    if ($categoryId <= 0) {
      Response::json(['ok'=>false,'error'=>Translations::get('invalid_category_id', $lang)], 400);
      return;
    }

    if ($difficulty < 1 || $difficulty > 5) {
      Response::json(['ok'=>false,'error'=>Translations::get('difficulty_invalid', $lang)], 400);
      return;
    }

    // Validar idioma
    if (!in_array($language, ['es', 'en'])) {
      Response::json(['ok'=>false,'error'=>Translations::get('language_invalid', $lang)], 400);
      return;
    }

    try {
      // Generar preguntas primero para capturar el proveedor correcto
      $generated = [];
      $failed = 0;
      $actualProvider = null;

      for ($i = 0; $i < $quantity; $i++) {
        // Primera iteración: generar SIN batch para capturar proveedor
        $question = $this->gameService->generateAndSaveQuestion($categoryId, $difficulty, null, $language);

        if ($question) {
          // Capturar el proveedor solo en la PRIMERA generación exitosa
          if ($actualProvider === null) {
            $generativeAi = $this->gameService->getGenerativeAI();
            if ($generativeAi && method_exists($generativeAi, 'getLastUsedProviderName')) {
              $actualProvider = $generativeAi->getLastUsedProviderName();
            } else {
              $actualProvider = 'unknown';
            }
          }
          $generated[] = $question;
        } else {
          $failed++;
        }
      }

      // Si NO se generó ninguna pregunta, retornar error
      if (count($generated) === 0) {
        Response::json([
          'ok' => false,
          'error' => Translations::get('no_questions_generated', $lang),
          'generated' => 0,
          'failed' => $failed
        ], 500);
        return;
      }

      // Crear batch SOLO si hubo preguntas exitosas
      $langLabel = $language === 'es' ? 'ES' : 'EN';
      $batchName = "IA-Gen-{$langLabel}-" . date('Y-m-d_H-i-s');

      $batchId = $this->batchRepo->create([
        'batch_name' => $batchName,
        'batch_type' => 'ai_generated',
        'description' => "Generación automática: {$quantity} preguntas, dificultad {$difficulty}, idioma {$language}",
        'total_questions' => count($generated), // Total real, no el solicitado
        'language' => $language,
        'ai_provider_used' => $actualProvider // Proveedor real usado
      ]);

      if ($batchId) {
        $pdo = $this->questions->getPdo();
        $checkStmt = $pdo->prepare("SELECT ai_provider_used FROM question_batches WHERE id = :id");
        $checkStmt->execute([':id' => $batchId]);
        $savedProvider = $checkStmt->fetchColumn();
      }

      // Asociar preguntas generadas al batch
      if ($batchId) {
        $pdo = $this->questions->getPdo();
        $updateStmt = $pdo->prepare("UPDATE questions SET batch_id = :batch_id WHERE id = :question_id");

        foreach ($generated as $q) {
          $updateStmt->execute([
            ':batch_id' => $batchId,
            ':question_id' => $q['id']
          ]);
        }
      }

      // Actualizar status del batch según resultados
      $status = $failed === 0 ? 'complete' : 'partial';
      $this->batchRepo->updateStatus($batchId, $status);

      Response::json([
        'ok' => true,
        'batch_id' => $batchId,
        'batch_name' => $batchName,
        'language' => $language,
        'ai_provider' => $actualProvider,
        'generated' => count($generated),
        'failed' => $failed,
        'questions' => $generated,
        'message' => count($generated) . " " . Translations::get('questions_generated', $lang) . " {$batchName}"
      ], 201);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('batch_generation_failed', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener estadísticas del dashboard administrativo
   *
   * Endpoint: GET /admin/dashboard
   *
   * @return void
   */
  public function dashboardStats(): void {
    $lang = LanguageDetector::detect();
    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      // Resumen: conteos totales
      $summaryStmt = $pdo->prepare("
        SELECT
          (SELECT COUNT(id) FROM players) AS total_players,
          (SELECT COUNT(id) FROM game_sessions) AS total_sessions,
          (SELECT COUNT(id) FROM questions) AS total_questions,
          (SELECT COUNT(id) FROM questions WHERE admin_verified = 0) AS pending_verification
      ");
      $summaryStmt->execute();
      $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC);

      // Top 5 preguntas más difíciles (menor porcentaje de acierto)
      $hardestStmt = $pdo->prepare("
        SELECT
          q.id,
          q.statement,
          q.difficulty,
          qc.name AS category_name,
          COUNT(pa.id) AS times_answered,
          ROUND((SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(pa.id)) * 100, 2) AS success_rate
        FROM questions q
        LEFT JOIN question_categories qc ON qc.id = q.category_id
        LEFT JOIN player_answers pa ON pa.question_id = q.id
        WHERE pa.id IS NOT NULL
        GROUP BY q.id, q.statement, q.difficulty, qc.name
        ORDER BY success_rate ASC
        LIMIT 5
      ");
      $hardestStmt->execute();
      $hardestQuestions = $hardestStmt->fetchAll(\PDO::FETCH_ASSOC);

      // Top 5 preguntas más fáciles (mayor porcentaje de acierto)
      $easiestStmt = $pdo->prepare("
        SELECT
          q.id,
          q.statement,
          q.difficulty,
          qc.name AS category_name,
          COUNT(pa.id) AS times_answered,
          ROUND((SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(pa.id)) * 100, 2) AS success_rate
        FROM questions q
        LEFT JOIN question_categories qc ON qc.id = q.category_id
        LEFT JOIN player_answers pa ON pa.question_id = q.id
        WHERE pa.id IS NOT NULL
        GROUP BY q.id, q.statement, q.difficulty, qc.name
        ORDER BY success_rate DESC
        LIMIT 5
      ");
      $easiestStmt->execute();
      $easiestQuestions = $easiestStmt->fetchAll(\PDO::FETCH_ASSOC);

      Response::json([
        'ok' => true,
        'summary' => [
          'total_players' => (int)($summary['total_players'] ?? 0),
          'total_sessions' => (int)($summary['total_sessions'] ?? 0),
          'total_questions' => (int)($summary['total_questions'] ?? 0),
          'pending_verification' => (int)($summary['pending_verification'] ?? 0)
        ],
        'hardest_questions' => array_map(fn($q) => [
          'id' => (int)$q['id'],
          'statement' => $q['statement'],
          'difficulty' => (int)$q['difficulty'],
          'category_name' => $q['category_name'],
          'times_answered' => (int)($q['times_answered'] ?? 0),
          'success_rate' => (float)($q['success_rate'] ?? 0)
        ], $hardestQuestions),
        'easiest_questions' => array_map(fn($q) => [
          'id' => (int)$q['id'],
          'statement' => $q['statement'],
          'difficulty' => (int)$q['difficulty'],
          'category_name' => $q['category_name'],
          'times_answered' => (int)($q['times_answered'] ?? 0),
          'success_rate' => (float)($q['success_rate'] ?? 0)
        ], $easiestQuestions)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('failed_to_fetch_dashboard_stats', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener todas las preguntas activas con información de categoría
   *
   * Endpoint: GET /admin/questions
   *
   * @return void
   */
  public function getQuestions(): void {
    $lang = LanguageDetector::detect();
    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      $sql = "SELECT q.id, q.statement, q.difficulty, q.category_id, qc.name AS category_name,
                     q.is_ai_generated, q.admin_verified
              FROM questions q
              LEFT JOIN question_categories qc ON qc.id = q.category_id
              WHERE q.is_active = 1
              ORDER BY q.id DESC";

      $stmt = $pdo->prepare($sql);
      $stmt->execute();
      $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      Response::json([
        'ok' => true,
        'questions' => $questions ?: []
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch questions: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener todas las categorías
   *
   * Endpoint: GET /admin/categories
   *
   * @return void
   */
  public function getCategories(): void {
    $lang = LanguageDetector::detect();
    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      $sql = "SELECT id, name, description FROM question_categories";

      $stmt = $pdo->prepare($sql);
      $stmt->execute();
      $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      Response::json([
        'ok' => true,
        'categories' => $categories ?: []
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('failed_to_fetch_categories', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Eliminar una pregunta
   *
   * Endpoint: DELETE /admin/questions/{id}
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function deleteQuestion(array $params): void {
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      // Verificar que la pregunta existe
      $checkStmt = $pdo->prepare("SELECT id FROM questions WHERE id = ?");
      $checkStmt->execute([$questionId]);

      if (!$checkStmt->fetch()) {
        Response::json(['ok'=>false,'error'=>Translations::get('question_not_found', $lang)], 404);
        return;
      }

      $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
      $success = $stmt->execute([$questionId]);

      if ($success) {
        Response::json(['ok'=>true,'message'=>Translations::get('question_deleted', $lang)], 200);
      } else {
        Response::json(['ok'=>false,'error'=>Translations::get('failed_to_delete_question', $lang)], 500);
      }
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>Translations::get('error_deleting_question', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Verifica todas las preguntas de un batch (cambia admin_verified a true)
   *
   * Endpoint: POST /admin/batch/{batchId}/verify
   *
   * @param array $params Parámetros de la ruta (contiene 'batchId')
   * @return void
   */
  public function verifyBatch(array $params): void
  {
    $lang = LanguageDetector::detect();
    $batchId = (int)($params['batchId'] ?? 0);

    if ($batchId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_batch_id', $lang)], 400);
      return;
    }

    try {
      // Obtener todas las preguntas del batch
      $questions = $this->questions->getByBatchId($batchId);

      if (empty($questions)) {
        Response::json(['ok' => false, 'error' => Translations::get('batch_not_found', $lang)], 404);
        return;
      }

      // Verificar cada pregunta
      foreach ($questions as $question) {
        $this->questions->updateVerificationStatus((int)$question['id'], true);
      }

      // Actualizar contador de verificadas en el batch
      if ($this->batchRepo) {
        $this->batchRepo->updateVerificationCount($batchId);
        $this->batchRepo->updateStatus($batchId, 'complete');
      }

      // Registrar en admin_system_logs
      $pdo = $this->questions->getPdo();
      if ($pdo) {
        $logSQL = "INSERT INTO admin_system_logs (action, entity_table, entity_id, details, logged_at)
                   VALUES (:action, :entity_table, :entity_id, :details, NOW())";
        $logSt = $pdo->prepare($logSQL);
        $logSt->execute([
          ':action' => 'batch_verified',
          ':entity_table' => 'question_batches',
          ':entity_id' => $batchId,
          ':details' => json_encode(['verified_count' => count($questions)])
        ]);
      }

      Response::json([
        'ok' => true,
        'message' => count($questions) . ' ' . Translations::get('questions_verified', $lang),
        'verified_count' => count($questions),
        'batch_id' => $batchId
      ]);
    } catch (\Exception $e) {
      error_log("Error verifying batch: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => Translations::get('error_verifying_batch', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Verificación masiva de preguntas
   *
   * Endpoint: POST /admin/questions/verify-bulk
   * Body: { "verify_all_pending": true } O { "question_ids": [1, 2, 3...] }
   *
   * @return void
   */
  public function verifyBulk(): void
  {
    $lang = LanguageDetector::detect();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok' => false, 'error' => Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      $verifiedCount = 0;

      // Opción 1: Verificar todas las pendientes
      if (isset($data['verify_all_pending']) && $data['verify_all_pending'] === true) {
        $stmt = $pdo->prepare("UPDATE questions SET admin_verified = 1 WHERE admin_verified = 0");
        $stmt->execute();
        $verifiedCount = $stmt->rowCount();
      }
      // Opción 2: Verificar IDs específicos
      elseif (isset($data['question_ids']) && is_array($data['question_ids']) && !empty($data['question_ids'])) {
        $questionIds = array_map('intval', $data['question_ids']);
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

        $stmt = $pdo->prepare("UPDATE questions SET admin_verified = 1 WHERE id IN ($placeholders) AND admin_verified = 0");
        $stmt->execute($questionIds);
        $verifiedCount = $stmt->rowCount();
      }
      // Opción 3: Verificar por batch_id
      elseif (isset($data['batch_id']) && is_numeric($data['batch_id'])) {
        $batchId = (int)$data['batch_id'];
        $stmt = $pdo->prepare("UPDATE questions SET admin_verified = 1 WHERE batch_id = :batch_id AND admin_verified = 0");
        $stmt->execute([':batch_id' => $batchId]);
        $verifiedCount = $stmt->rowCount();

        // Actualizar estado del batch
        if ($this->batchRepo && $verifiedCount > 0) {
          $this->batchRepo->updateStatus($batchId, 'complete');
        }
      }
      else {
        Response::json(['ok' => false, 'error' => Translations::get('must_specify_verification_method', $lang)], 400);
        return;
      }

      // Registrar en logs
      $logSQL = "INSERT INTO admin_system_logs (action, entity_table, entity_id, details, logged_at)
                 VALUES (:action, :entity_table, :entity_id, :details, NOW())";
      $logSt = $pdo->prepare($logSQL);
      $logSt->execute([
        ':action' => 'bulk_verify',
        ':entity_table' => 'questions',
        ':entity_id' => 0,
        ':details' => json_encode([
          'verified_count' => $verifiedCount,
          'method' => isset($data['verify_all_pending']) ? 'all_pending' : (isset($data['batch_id']) ? 'by_batch' : 'by_ids')
        ])
      ]);

      Response::json([
        'ok' => true,
        'message' => $verifiedCount > 0
          ? "$verifiedCount " . Translations::get('questions_verified_successfully', $lang)
          : Translations::get('no_pending_questions', $lang),
        'verified_count' => $verifiedCount
      ], 200);

    } catch (\Exception $e) {
      error_log("Error in bulk verification: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => Translations::get('error_verifying_questions', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Importa preguntas desde archivo CSV
   *
   * Endpoint: POST /admin/batch/import-csv
   *
   * @return void
   */
  public function importCSV(): void
  {
    // Validar archivo
    if (!isset($_FILES['csv_file'])) {
      Response::json(['ok' => false, 'error' => 'No se proporcionó archivo CSV'], 400);
      return;
    }

    $file = $_FILES['csv_file'];
    $mimeTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];

    if (!in_array($file['type'], $mimeTypes)) {
      Response::json(['ok' => false, 'error' => 'Tipo de archivo inválido. Debe ser CSV'], 400);
      return;
    }

    if ($file['size'] > 5242880) { // 5MB
      Response::json(['ok' => false, 'error' => 'Archivo demasiado grande (máximo 5MB)'], 400);
      return;
    }

    try {
      $handle = fopen($file['tmp_name'], 'r');
      if (!$handle) {
        Response::json(['ok' => false, 'error' => 'No se pudo leer el archivo'], 500);
        return;
      }

      // Leer headers
      $headers = fgetcsv($handle, 0, ',', '"', '\\');
      $requiredHeaders = ['category_id', 'difficulty', 'statement', 'option_1', 'option_2', 'option_3', 'option_4', 'correct_option_index', 'explanation_correct', 'explanation_incorrect', 'source_citation', 'language'];

      if (!$headers || count(array_intersect($headers, $requiredHeaders)) !== count($requiredHeaders)) {
        fclose($handle);
        Response::json(['ok' => false, 'error' => 'Headers del CSV inválidos. Requeridos: ' . implode(', ', $requiredHeaders)], 400);
        return;
      }

      // Crear batch
      if (!$this->batchRepo) {
        Response::json(['ok' => false, 'error' => 'Repositorio de batch no disponible'], 500);
        return;
      }

      if (!$this->categoryRepo) {
        Response::json(['ok' => false, 'error' => 'Repositorio de categorías no disponible'], 500);
        return;
      }

      // Determinar idioma del batch (se usará el de la primera fila válida)
      $batchLanguage = 'es'; // Default
      
      $batchId = $this->batchRepo->create([
        'batch_name' => 'CSV-' . date('Y-m-d H:i:s'),
        'batch_type' => 'csv_imported',
        'description' => 'Importado desde CSV por admin',
        'language' => $batchLanguage, // Se actualizará con la primera pregunta
        'total_questions' => 0
      ]);

      $successCount = 0;
      $errorCount = 0;
      $errors = [];
      $lineNumber = 2; // Headers en línea 1

      // Procesar líneas
      while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        // Mapear valores del CSV a array asociativo
        $rowData = array_combine($headers, $row);

        try {
          // Validar campos básicos
          $categoryId = (int)($rowData['category_id'] ?? 0);
          $difficulty = (int)($rowData['difficulty'] ?? 0);
          $correctOptionIndex = (int)($rowData['correct_option_index'] ?? -1);
          $language = trim($rowData['language'] ?? 'es');

          if ($categoryId <= 0) {
            throw new \Exception('category_id debe ser un número positivo');
          }

          if (!in_array($difficulty, [1, 2, 3, 4, 5])) {
            throw new \Exception('difficulty debe estar entre 1 y 5');
          }

          if (!in_array($correctOptionIndex, [0, 1, 2, 3])) {
            throw new \Exception('correct_option_index debe estar entre 0 y 3');
          }

          // Validar idioma
          if (!in_array($language, ['es', 'en'])) {
            throw new \Exception('language debe ser "es" o "en"');
          }

          // Actualizar idioma del batch con la primera pregunta válida
          if ($successCount === 0 && $this->batchRepo) {
            $pdo = $this->questions->getPdo();
            if ($pdo) {
              $stmt = $pdo->prepare("UPDATE question_batches SET language = :lang WHERE id = :id");
              $stmt->execute([':lang' => $language, ':id' => $batchId]);
            }
          }

          if (empty(trim($rowData['statement'] ?? ''))) {
            throw new \Exception('statement no puede estar vacío');
          }

          // Validar opciones
          foreach (['option_1', 'option_2', 'option_3', 'option_4'] as $optKey) {
            if (empty(trim($rowData[$optKey] ?? ''))) {
              throw new \Exception("$optKey no puede estar vacío");
            }
          }

          // Validar explicaciones (obligatorias)
          $explanationCorrect = trim($rowData['explanation_correct'] ?? '');
          $explanationIncorrect = trim($rowData['explanation_incorrect'] ?? '');
          $sourceCitation = trim($rowData['source_citation'] ?? '');

          if (empty($explanationCorrect)) {
            throw new \Exception('explanation_correct no puede estar vacío');
          }
          if (strlen($explanationCorrect) > 2000) {
            throw new \Exception('explanation_correct no puede exceder 2000 caracteres');
          }

          if (empty($explanationIncorrect)) {
            throw new \Exception('explanation_incorrect no puede estar vacío');
          }
          if (strlen($explanationIncorrect) > 2000) {
            throw new \Exception('explanation_incorrect no puede exceder 2000 caracteres');
          }

          if (empty($sourceCitation)) {
            throw new \Exception('source_citation no puede estar vacío');
          }
          if (strlen($sourceCitation) > 255) {
            throw new \Exception('source_citation no puede exceder 255 caracteres');
          }

          // Verificar que la categoría existe
          $category = $this->categoryRepo->find($categoryId);
          if (!$category) {
            throw new \Exception('Categoría no existe con ID: ' . $categoryId);
          }

          // Crear pregunta
          $questionId = $this->questions->createWithBatch([
            'statement' => $rowData['statement'],
            'difficulty' => $difficulty,
            'category_id' => $categoryId,
            'source_id' => null,
            'language' => $language
          ], $batchId);

          if (!$questionId) {
            throw new \Exception('Error al crear pregunta');
          }

          // Crear opciones (correct_option_index es 0-indexed: 0=option_1, 1=option_2, etc.)
          $optionsData = [
            ['text' => $rowData['option_1'], 'is_correct' => $correctOptionIndex === 0],
            ['text' => $rowData['option_2'], 'is_correct' => $correctOptionIndex === 1],
            ['text' => $rowData['option_3'], 'is_correct' => $correctOptionIndex === 2],
            ['text' => $rowData['option_4'], 'is_correct' => $correctOptionIndex === 3]
          ];

          $this->questions->saveOptions($questionId, $optionsData);

          // Guardar explicación correcta
          $this->questions->saveExplanation(
            $questionId,
            $explanationCorrect,
            $sourceCitation,
            'correct'
          );

          // Guardar explicación incorrecta
          $this->questions->saveExplanation(
            $questionId,
            $explanationIncorrect,
            $sourceCitation,
            'incorrect'
          );

          $successCount++;
        } catch (\Exception $e) {
          $errorCount++;
          $errors[] = "Línea $lineNumber: " . $e->getMessage();
        }

        $lineNumber++;
      }

      fclose($handle);

      // Actualizar status del batch
      if ($successCount > 0 && $this->batchRepo) {
        $this->batchRepo->updateStatus($batchId, $errorCount === 0 ? 'complete' : 'partial');
      }

      // Registrar en admin_system_logs
      $pdo = $this->questions->getPdo();
      if ($pdo) {
        $logSQL = "INSERT INTO admin_system_logs (action, entity_table, entity_id, details, logged_at)
                   VALUES (:action, :entity_table, :entity_id, :details, NOW())";
        $logSt = $pdo->prepare($logSQL);
        $logSt->execute([
          ':action' => 'csv_imported',
          ':entity_table' => 'question_batches',
          ':entity_id' => $batchId,
          ':details' => json_encode(['filename' => $file['name'], 'imported' => $successCount, 'errors' => $errorCount])
        ]);
      }

      Response::json([
        'ok' => true,
        'imported' => $successCount,
        'errors' => $errorCount,
        'batch_id' => $batchId,
        'error_details' => $errors
      ]);
    } catch (\Exception $e) {
      error_log("Error importing CSV: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Error al importar CSV: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene todas las preguntas sin verificar
   *
   * Endpoint: GET /admin/unverified?batchId={optional}
   *
   * @return void
   */
  public function getUnverifiedQuestions(): void
  {
    try {
      $batchId = $_GET['batchId'] ?? null;
      $batchId = $batchId ? (int)$batchId : null;

      $unverified = $this->questions->getUnverifiedQuestions($batchId);

      Response::json([
        'ok' => true,
        'questions' => $unverified,
        'count' => count($unverified)
      ]);
    } catch (\Exception $e) {
      error_log("Error getting unverified questions: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Error al obtener preguntas: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Edita una explicación de una pregunta
   *
   * Endpoint: PUT /admin/explanation/{explanationId}
   *
   * @param array $params Parámetros de la ruta (contiene 'explanationId')
   * @return void
   */
  public function editExplanation(array $params): void
  {
    $lang = LanguageDetector::detect();
    $explanationId = (int)($params['explanationId'] ?? 0);

    if ($explanationId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de explicación inválido'], 400);
      return;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $newText = $body['text'] ?? null;

    if (!$newText || empty(trim($newText))) {
      Response::json(['ok' => false, 'error' => Translations::get('text_cannot_be_empty', $lang)], 400);
      return;
    }

    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok' => false, 'error' => Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      // Actualizar explicación
      $sql = "UPDATE question_explanations SET text = :text WHERE id = :id";
      $st = $pdo->prepare($sql);
      $success = $st->execute([
        ':text' => trim($newText),
        ':id' => $explanationId
      ]);

      if (!$success || $st->rowCount() === 0) {
        Response::json(['ok' => false, 'error' => 'Explicación no encontrada'], 404);
        return;
      }

      // Registrar en admin_system_logs
      $logSQL = "INSERT INTO admin_system_logs (action, entity_table, entity_id, details, logged_at)
                 VALUES (:action, :entity_table, :entity_id, :details, NOW())";
      $logSt = $pdo->prepare($logSQL);
      $logSt->execute([
        ':action' => 'explanation_edited',
        ':entity_table' => 'question_explanations',
        ':entity_id' => $explanationId,
        ':details' => json_encode(['updated_text' => substr($newText, 0, 100)])
      ]);

      Response::json([
        'ok' => true,
        'explanation_id' => $explanationId,
        'message' => 'Explicación actualizada'
      ]);
    } catch (\Exception $e) {
      error_log("Error editing explanation: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Error al editar explicación: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene la lista de proveedores de IA disponibles
   *
   * Endpoint: GET /admin/available-providers
   *
   * @return void
   */
  public function getAvailableProviders(): void
  {
    $lang = LanguageDetector::detect();
    try {
      if (!$this->gameService) {
        Response::json(['ok' => false, 'error' => Translations::get('game_service_unavailable', $lang)], 500);
        return;
      }

      $multiAI = $this->gameService->getGenerativeAI();

      if (!$multiAI) {
        Response::json(['ok' => false, 'error' => 'AI service not available'], 500);
        return;
      }

      // Verificar si el servicio tiene el método getAvailableProviders
      if (!method_exists($multiAI, 'getAvailableProviders')) {
        Response::json(['ok' => false, 'error' => 'Multi-AI service not configured'], 500);
        return;
      }

      $providers = $multiAI->getAvailableProviders();

      Response::json([
        'ok' => true,
        'providers' => $providers,
        'count' => count($providers)
      ]);
    } catch (\Exception $e) {
      error_log("Error getting available providers: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Error al obtener proveedores: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene estadísticas de todos los batches
   *
   * Endpoint: GET /admin/batch-statistics
   *
   * @return void
   */
  public function getBatchStatistics(): void
  {
    $lang = LanguageDetector::detect();
    try {
      if (!$this->batchRepo) {
        Response::json(['ok' => false, 'error' => Translations::get('batch_repo_unavailable', $lang)], 500);
        return;
      }

      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok' => false, 'error' => Translations::get('database_connection_failed', $lang)], 500);
        return;
      }

      // Calcular estadísticas en tiempo real con JOIN
      $sql = "SELECT
                qb.id,
                qb.batch_name,
                qb.batch_type,
                qb.ai_provider_used,
                COALESCE(COUNT(q.id), 0) as total_questions,
                COALESCE(SUM(q.admin_verified = 1), 0) as verified_count,
                CASE
                  WHEN COUNT(q.id) > 0 THEN ROUND((SUM(q.admin_verified = 1) / COUNT(q.id) * 100), 2)
                  ELSE 0
                END as verification_percent,
                qb.imported_at,
                qb.status
              FROM question_batches qb
              LEFT JOIN questions q ON q.batch_id = qb.id
              GROUP BY qb.id, qb.batch_name, qb.batch_type, qb.ai_provider_used, qb.imported_at, qb.status
              ORDER BY qb.imported_at DESC";

      $st = $pdo->prepare($sql);
      $st->execute();
      $batches = $st->fetchAll() ?: [];

      Response::json([
        'ok' => true,
        'batches' => $batches,
        'count' => count($batches)
      ]);
    } catch (\Exception $e) {
      error_log("Error getting batch statistics: " . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Error al obtener estadísticas: ' . $e->getMessage()], 500);
    }
  }

  // ============================================================
  // ADMIN MANAGEMENT CRUD
  // ============================================================

  /**
   * List all admins
   *
   * Endpoint: GET /admin/admins
   *
   * @return void
   */
  public function indexAdmins(): void {
    $lang = LanguageDetector::detect();
    if (!$this->adminRepo) {
      Response::json(['ok' => false, 'error' => 'Admin repository not available'], 500);
      return;
    }

    try {
      // Get current admin from middleware
      $currentAdmin = $_SERVER['ADMIN'] ?? null;
      
      // Superadmins can see all admins (including inactive)
      // Regular admins can only see active admins
      $includeInactive = ($currentAdmin['role'] ?? '') === 'superadmin';
      
      $admins = $this->adminRepo->all($includeInactive);

      Response::json([
        'ok' => true,
        'admins' => array_map(fn($admin) => $admin->toArray(), $admins)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Failed to fetch admins: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Get single admin by ID
   *
   * Endpoint: GET /admin/admins/{id}
   *
   * @param array $params Route parameters (contains 'id')
   * @return void
   */
  public function showAdmin(array $params): void {
    $lang = LanguageDetector::detect();
    if (!$this->adminRepo) {
      Response::json(['ok' => false, 'error' => 'Admin repository not available'], 500);
      return;
    }

    $adminId = (int)($params['id'] ?? 0);

    if ($adminId <= 0) {
      Response::json(['ok' => false, 'error' => 'Invalid admin ID'], 400);
      return;
    }

    try {
      $admin = $this->adminRepo->find($adminId);

      if (!$admin) {
        Response::json(['ok' => false, 'error' => 'Admin not found'], 404);
        return;
      }

      Response::json([
        'ok' => true,
        'admin' => $admin->toArray()
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Failed to fetch admin: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Create new admin (superadmin only)
   *
   * Endpoint: POST /admin/admins
   * Body: { "email": "...", "password": "...", "role": "admin|superadmin" }
   *
   * @return void
   */
  public function storeAdmin(): void {
    $lang = LanguageDetector::detect();
    if (!$this->adminRepo) {
      Response::json(['ok' => false, 'error' => 'Admin repository not available'], 500);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['email', 'password', 'role']);

    $email = trim($data['email']);
    $password = trim($data['password']);
    $role = trim($data['role']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      Response::json(['ok' => false, 'error' => 'Invalid email format'], 400);
      return;
    }

    // Validate password length
    if (strlen($password) < 8) {
      Response::json(['ok' => false, 'error' => 'Password must be at least 8 characters'], 400);
      return;
    }

    // Validate role
    if (!in_array($role, ['admin', 'superadmin'])) {
      Response::json(['ok' => false, 'error' => 'Role must be "admin" or "superadmin"'], 400);
      return;
    }

    try {
      // Check if email already exists
      $existing = $this->adminRepo->findByEmail($email);
      if ($existing) {
        Response::json(['ok' => false, 'error' => 'Email already exists'], 409);
        return;
      }

      // Hash password
      $passwordHash = password_hash($password, PASSWORD_BCRYPT);

      // Create admin
      $admin = $this->adminRepo->create($email, $passwordHash, $role);

      Response::json([
        'ok' => true,
        'admin' => $admin->toArray(),
        'message' => 'Admin created successfully'
      ], 201);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Failed to create admin: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Update admin (superadmin only)
   *
   * Endpoint: PUT /admin/admins/{id}
   * Body: { "email"?: "...", "password"?: "...", "role"?: "admin|superadmin" }
   *
   * @param array $params Route parameters (contains 'id')
   * @return void
   */
  public function updateAdmin(array $params): void {
    $lang = LanguageDetector::detect();
    if (!$this->adminRepo) {
      Response::json(['ok' => false, 'error' => 'Admin repository not available'], 500);
      return;
    }

    $adminId = (int)($params['id'] ?? 0);

    if ($adminId <= 0) {
      Response::json(['ok' => false, 'error' => 'Invalid admin ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data)) {
      Response::json(['ok' => false, 'error' => 'No data provided'], 400);
      return;
    }

    try {
      // Verify admin exists
      $admin = $this->adminRepo->find($adminId);
      if (!$admin) {
        Response::json(['ok' => false, 'error' => 'Admin not found'], 404);
        return;
      }

      $updateData = [];

      // Validate and prepare email if provided
      if (isset($data['email'])) {
        $email = trim($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          Response::json(['ok' => false, 'error' => 'Invalid email format'], 400);
          return;
        }

        // Check if new email already exists (but not for current admin)
        $existing = $this->adminRepo->findByEmail($email);
        if ($existing && $existing->id !== $adminId) {
          Response::json(['ok' => false, 'error' => 'Email already exists'], 409);
          return;
        }

        $updateData['email'] = $email;
      }

      // Validate and prepare password if provided
      if (isset($data['password'])) {
        $password = trim($data['password']);
        if (strlen($password) < 8) {
          Response::json(['ok' => false, 'error' => 'Password must be at least 8 characters'], 400);
          return;
        }
        $updateData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
      }

      // Validate and prepare role if provided
      if (isset($data['role'])) {
        $role = trim($data['role']);
        if (!in_array($role, ['admin', 'superadmin'])) {
          Response::json(['ok' => false, 'error' => 'Role must be "admin" or "superadmin"'], 400);
          return;
        }
        $updateData['role'] = $role;
      }

      // Update admin
      $success = $this->adminRepo->update($adminId, $updateData);

      if (!$success) {
        Response::json(['ok' => false, 'error' => 'Failed to update admin'], 500);
        return;
      }

      // Get updated admin
      $updatedAdmin = $this->adminRepo->find($adminId);

      Response::json([
        'ok' => true,
        'admin' => $updatedAdmin->toArray(),
        'message' => 'Admin updated successfully'
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Failed to update admin: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Logical deletion - deactivate admin (superadmin only)
   *
   * Endpoint: DELETE /admin/admins/{id}
   *
   * @param array $params Route parameters (contains 'id')
   * @return void
   */
  public function destroyAdmin(array $params): void {
    $lang = LanguageDetector::detect();
    if (!$this->adminRepo) {
      Response::json(['ok' => false, 'error' => 'Admin repository not available'], 500);
      return;
    }

    $adminId = (int)($params['id'] ?? 0);

    if ($adminId <= 0) {
      Response::json(['ok' => false, 'error' => 'Invalid admin ID'], 400);
      return;
    }

    try {
      // Get current admin
      $currentAdmin = $_SERVER['ADMIN'] ?? null;
      
      // Prevent self-deletion
      if ($currentAdmin && (int)$currentAdmin['id'] === $adminId) {
        Response::json(['ok' => false, 'error' => 'Cannot deactivate your own account'], 403);
        return;
      }

      // Verify admin exists
      $admin = $this->adminRepo->find($adminId);
      if (!$admin) {
        Response::json(['ok' => false, 'error' => 'Admin not found'], 404);
        return;
      }

      // Logical delete (set is_active = 0)
      $success = $this->adminRepo->delete($adminId);

      if (!$success) {
        Response::json(['ok' => false, 'error' => 'Failed to deactivate admin'], 500);
        return;
      }

      Response::json([
        'ok' => true,
        'message' => 'Admin deactivated successfully'
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Failed to deactivate admin: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Toggle admin active status (superadmin only)
   *
   * Endpoint: PATCH /admin/admins/{id}/status
   * Body: { "is_active": true|false }
   *
   * @param array $params Route parameters (contains 'id')
   * @return void
   */
  public function toggleAdminStatus(array $params): void {
    $lang = LanguageDetector::detect();
    if (!$this->adminRepo) {
      Response::json(['ok' => false, 'error' => 'Admin repository not available'], 500);
      return;
    }

    $adminId = (int)($params['id'] ?? 0);

    if ($adminId <= 0) {
      Response::json(['ok' => false, 'error' => 'Invalid admin ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['is_active']);

    $isActive = (bool)$data['is_active'];

    try {
      // Get current admin
      $currentAdmin = $_SERVER['ADMIN'] ?? null;
      
      // Prevent self-deactivation
      if (!$isActive && $currentAdmin && (int)$currentAdmin['id'] === $adminId) {
        Response::json(['ok' => false, 'error' => 'Cannot deactivate your own account'], 403);
        return;
      }

      // Verify admin exists
      $admin = $this->adminRepo->find($adminId);
      if (!$admin) {
        Response::json(['ok' => false, 'error' => 'Admin not found'], 404);
        return;
      }

      // Update status
      $success = $this->adminRepo->updateStatus($adminId, $isActive);

      if (!$success) {
        Response::json(['ok' => false, 'error' => 'Failed to update admin status'], 500);
        return;
      }

      // Get updated admin
      $updatedAdmin = $this->adminRepo->find($adminId);

      Response::json([
        'ok' => true,
        'admin' => $updatedAdmin->toArray(),
        'message' => Translations::get('admin_status_updated', $lang)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_admin_status', $lang) . ': ' . $e->getMessage()], 500);
    }
  }
}
