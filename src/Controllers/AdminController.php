<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Services\ValidationService;
use Src\Services\GameService;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;
use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;
use Src\Utils\Response;

final class AdminController {
  public function __construct(
    private QuestionRepositoryInterface $questions,
    private ?SystemPromptRepositoryInterface $prompts = null,
    private ?GameService $gameService = null
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
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid question ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['statement']);

    $statement = trim($data['statement']);

    if (empty($statement)) {
      Response::json(['ok'=>false,'error'=>'Question statement cannot be empty'], 400);
      return;
    }

    if (strlen($statement) < 10) {
      Response::json(['ok'=>false,'error'=>'Question statement must be at least 10 characters'], 400);
      return;
    }

    if (strlen($statement) > 1000) {
      Response::json(['ok'=>false,'error'=>'Question statement cannot exceed 1000 characters'], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok'=>false,'error'=>'Question not found'], 404);
        return;
      }

      // Actualizar statement manteniendo el estado de verificación actual
      $this->questions->update($questionId, $statement, $question->adminVerified);

      Response::json([
        'ok' => true,
        'message' => 'Question updated successfully',
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
      Response::json(['ok'=>false,'error'=>'Failed to update question: ' . $e->getMessage()], 500);
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
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid question ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['verified']);

    $verified = (bool)$data['verified'];

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok'=>false,'error'=>'Question not found'], 404);
        return;
      }

      // Actualizar estado de verificación manteniendo el statement actual
      $this->questions->update($questionId, $question->statement, $verified);

      Response::json([
        'ok' => true,
        'message' => 'Question verification status updated',
        'question' => [
          'id' => $question->id,
          'statement' => $question->statement,
          'admin_verified' => $verified
        ]
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to verify question: ' . $e->getMessage()], 500);
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
    if (!$this->prompts) {
      Response::json(['ok'=>false,'error'=>'Prompt repository not available'], 500);
      return;
    }

    $prompt = $this->prompts->getActive();

    if (!$prompt) {
      Response::json(['ok'=>false,'error'=>'No active prompt found'], 404);
      return;
    }

    Response::json([
      'ok' => true,
      'prompt' => [
        'id' => $prompt->id,
        'prompt_text' => $prompt->promptText,
        'temperature' => $prompt->temperature,
        'is_active' => $prompt->isActive
      ]
    ], 200);
  }

  /**
   * Actualizar configuración de prompt de IA
   *
   * Endpoint: PUT /admin/config/prompt
   * Body: { "prompt_text": "...", "temperature": 0.7 }
   *
   * @return void
   */
  public function updatePromptConfig(): void {
    if (!$this->prompts) {
      Response::json(['ok'=>false,'error'=>'Prompt repository not available'], 500);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['prompt_text', 'temperature']);

    $promptText = trim($data['prompt_text']);
    $temperature = (float)$data['temperature'];

    if (empty($promptText)) {
      Response::json(['ok'=>false,'error'=>'Prompt text cannot be empty'], 400);
      return;
    }

    if ($temperature < 0 || $temperature > 1) {
      Response::json(['ok'=>false,'error'=>'Temperature must be between 0 and 1'], 400);
      return;
    }

    try {
      $this->prompts->update($promptText, $temperature);
      Response::json([
        'ok' => true,
        'message' => 'Prompt configuration updated successfully'
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to update prompt: ' . $e->getMessage()], 500);
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
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['name']);

    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');

    if (empty($name)) {
      Response::json(['ok'=>false,'error'=>'Category name cannot be empty'], 400);
      return;
    }

    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>'Database connection failed'], 500);
        return;
      }

      $stmt = $pdo->prepare("INSERT INTO question_categories (name, description) VALUES (?, ?)");
      $stmt->execute([$name, $description ?: null]);

      $categoryId = (int)$pdo->lastInsertId();

      Response::json([
        'ok' => true,
        'category_id' => $categoryId,
        'message' => 'Category created successfully'
      ], 201);
    } catch (\Exception $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        Response::json(['ok'=>false,'error'=>'Category name already exists'], 409);
      } else {
        Response::json(['ok'=>false,'error'=>'Failed to create category: ' . $e->getMessage()], 500);
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
    $categoryId = (int)($params['id'] ?? 0);

    if ($categoryId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid category ID'], 400);
      return;
    }

    try {
      $pdo = $this->questions->getPdo();
      if (!$pdo) {
        Response::json(['ok'=>false,'error'=>'Database connection failed'], 500);
        return;
      }

      $stmt = $pdo->prepare("DELETE FROM question_categories WHERE id = ?");
      $stmt->execute([$categoryId]);

      if ($stmt->rowCount() === 0) {
        Response::json(['ok'=>false,'error'=>'Category not found'], 404);
        return;
      }

      Response::json([
        'ok' => true,
        'message' => 'Category deleted successfully'
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to delete category: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Generar preguntas en batch usando IA
   *
   * Endpoint: POST /admin/generate-batch
   * Body: { "quantity": 5, "category_id": 1, "difficulty": 3 }
   *
   * @return void
   */
  public function generateBatch(): void {
    if (!$this->gameService) {
      Response::json(['ok'=>false,'error'=>'Game service not available'], 500);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['quantity', 'category_id', 'difficulty']);

    $quantity = (int)$data['quantity'];
    $categoryId = (int)$data['category_id'];
    $difficulty = (int)$data['difficulty'];

    if ($quantity <= 0 || $quantity > 50) {
      Response::json(['ok'=>false,'error'=>'Quantity must be between 1 and 50'], 400);
      return;
    }

    if ($categoryId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid category ID'], 400);
      return;
    }

    if ($difficulty < 1 || $difficulty > 5) {
      Response::json(['ok'=>false,'error'=>'Difficulty must be between 1 and 5'], 400);
      return;
    }

    try {
      $generated = [];
      $failed = 0;

      for ($i = 0; $i < $quantity; $i++) {
        $question = $this->gameService->generateAndSaveQuestion($categoryId, $difficulty);

        if ($question) {
          $generated[] = $question;
        } else {
          $failed++;
        }
      }

      Response::json([
        'ok' => true,
        'generated' => count($generated),
        'failed' => $failed,
        'questions' => $generated,
        'message' => "Generated " . count($generated) . " questions successfully"
      ], 201);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Batch generation failed: ' . $e->getMessage()], 500);
    }
  }
}
