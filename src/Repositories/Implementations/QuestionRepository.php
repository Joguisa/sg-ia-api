<?php

namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Models\Question;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;

final class QuestionRepository implements QuestionRepositoryInterface
{
  public function __construct(private Connection $db) {}

  public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question
  {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id,q.is_ai_generated,q.admin_verified
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d AND q.admin_verified=1
            ORDER BY q.id DESC LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':c' => $categoryId, ':d' => $difficulty]);
    $r = $st->fetch();
    return $r ? new Question(
      (int)$r['id'],
      $r['statement'],
      (int)$r['difficulty'],
      (int)$r['category_id'],
      (bool)$r['is_ai_generated'],
      (bool)$r['admin_verified']
    ) : null;
  }

  /**
   * Obtiene una pregunta aleatoria de la dificultad especificada
   * excluyendo las preguntas ya respondidas en la sesión actual
   *
   * @param int $categoryId ID de la categoría
   * @param int $difficulty Nivel de dificultad
   * @param int $sessionId ID de la sesión actual
   * @return Question|null Pregunta aleatoria o null si no hay disponibles
   */
  public function getRandomByDifficultyExcludingAnswered(int $categoryId, int $difficulty, int $sessionId): ?Question
  {
    // ESTRATEGIA DE FALLBACK: Buscar en múltiples dificultades
    // 1. Dificultad exacta
    // 2. Dificultad -1
    // 3. Dificultad +1
    // 4. Cualquier dificultad disponible
    $difficulties = [$difficulty];

    if ($difficulty > 1) {
      $difficulties[] = $difficulty - 1;
    }
    if ($difficulty < 5) {
      $difficulties[] = $difficulty + 1;
    }

    // Generate named placeholders for IN clause
    $diffPlaceholders = [];
    foreach ($difficulties as $index => $diff) {
      $diffPlaceholders[] = ':diff_' . $index;
    }

    $sql = "SELECT q.id, q.statement, q.difficulty, q.category_id, q.is_ai_generated, q.admin_verified
            FROM questions q
            WHERE q.is_active = 1
              AND q.category_id = :c
              AND q.difficulty IN (" . implode(',', $diffPlaceholders) . ")
              AND q.admin_verified = 1
              AND q.id NOT IN (
                SELECT pa.question_id
                FROM player_answers pa
                WHERE pa.session_id = :session_id
              )
            ORDER BY
              CASE q.difficulty
                WHEN :preferred_diff THEN 0
                ELSE 1
              END,
              RAND()
            LIMIT 1";

    // Build complete params array with all named parameters
    $params = [
      ':c' => $categoryId,
      ':session_id' => $sessionId,
      ':preferred_diff' => $difficulty
    ];

    // Add dynamic difficulty parameters
    foreach ($difficulties as $index => $diff) {
      $params[':diff_' . $index] = $diff;
    }

    $st = $this->db->pdo()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();

    return $r ? new Question(
      (int)$r['id'],
      $r['statement'],
      (int)$r['difficulty'],
      (int)$r['category_id'],
      (bool)$r['is_ai_generated'],
      (bool)$r['admin_verified']
    ) : null;
  }

  /**
   * Obtiene una pregunta aleatoria excluyendo preguntas respondidas Y niveles bloqueados
   *
   * @param int|null $categoryId ID de la categoría (NULL = todas las categorías)
   * @param int $difficulty Nivel de dificultad preferido
   * @param int $sessionId ID de la sesión actual
   * @param array $lockedLevels Array de niveles bloqueados [1, 2, 3]
   * @param array|null $roomFilters Filtros de sala ['categories' => [1,2,3], 'difficulties' => [1,2,3]]
   * @return Question|null Pregunta aleatoria o null si no hay disponibles
   */
  public function getRandomExcludingAnsweredAndLockedLevels(
    ?int $categoryId,
    int $difficulty,
    int $sessionId,
    array $lockedLevels,
    ?array $roomFilters = null
  ): ?Question {
    // Aplicar filtros de sala si existen
    $allowedCategories = null;
    $allowedDifficulties = null;

    if ($roomFilters !== null) {
      // Si la sala tiene filtro de categorías, usarlo (ignora $categoryId del jugador)
      if (!empty($roomFilters['categories'])) {
        $allowedCategories = $roomFilters['categories'];
        error_log("Session $sessionId - Room filter: categories " . json_encode($allowedCategories));
      }

      // Si la sala tiene filtro de dificultades, aplicarlo
      if (!empty($roomFilters['difficulties'])) {
        $allowedDifficulties = $roomFilters['difficulties'];
        error_log("Session $sessionId - Room filter: difficulties " . json_encode($allowedDifficulties));
      }
    }

    // ESTRATEGIA DE FALLBACK CON 2 INTENTOS
    // INTENTO 1: Buscar en el nivel actual y adyacentes (no bloqueados)
    $difficulties = [$difficulty];

    if ($difficulty > 1) {
      $difficulties[] = $difficulty - 1;
    }
    if ($difficulty < 5) {
      $difficulties[] = $difficulty + 1;
    }

    // ELIMINAR niveles bloqueados de las dificultades candidatas
    $difficulties = array_diff($difficulties, $lockedLevels);

    // Si hay filtro de dificultades de sala, intersectar
    if ($allowedDifficulties !== null) {
      $difficulties = array_intersect($difficulties, $allowedDifficulties);
    }

    error_log("Session $sessionId - Attempt 1: Searching in levels " . json_encode(array_values($difficulties)));

    // Si todos los niveles cercanos están bloqueados, ir directo al fallback
    if (!empty($difficulties)) {
      $question = $this->searchQuestionInLevels($categoryId, $difficulties, $sessionId, $difficulty, $allowedCategories);
      if ($question) {
        error_log("Session $sessionId - Found question {$question->id} in attempt 1");
        return $question;
      }
      error_log("Session $sessionId - No questions found in attempt 1");
    } else {
      error_log("Session $sessionId - All nearby levels are locked, skipping attempt 1");
    }

    // INTENTO 2: Si no se encontró pregunta, buscar en TODOS los niveles no bloqueados
    $allLevels = $allowedDifficulties ?? [1, 2, 3, 4, 5];
    $allAvailableLevels = array_diff($allLevels, $lockedLevels);

    // Si todos los niveles están bloqueados, no hay preguntas disponibles
    if (empty($allAvailableLevels)) {
      error_log("Session $sessionId - All levels are locked, no questions available");
      return null;
    }

    error_log("Session $sessionId - Attempt 2 (FALLBACK): Searching in all available levels " . json_encode(array_values($allAvailableLevels)));

    $question = $this->searchQuestionInLevels($categoryId, $allAvailableLevels, $sessionId, $difficulty, $allowedCategories);

    if ($question) {
      error_log("Session $sessionId - Found question {$question->id} in attempt 2 (fallback)");
    } else {
      error_log("Session $sessionId - No questions found even in fallback - all questions answered or no verified questions");
    }

    return $question;
  }

  /**
   * Método auxiliar para buscar pregunta en niveles específicos
   *
   * @param int|null $categoryId Categoría específica del jugador (puede ser ignorada por filtro de sala)
   * @param array $difficulties Niveles de dificultad permitidos
   * @param int $sessionId ID de la sesión
   * @param int $preferredDifficulty Dificultad preferida para ordenar resultados
   * @param array|null $allowedCategories Categorías permitidas por la sala (null = todas)
   */
  private function searchQuestionInLevels(
    ?int $categoryId,
    array $difficulties,
    int $sessionId,
    int $preferredDifficulty,
    ?array $allowedCategories = null
  ): ?Question {
    if (empty($difficulties)) {
      return null;
    }

    // Generate named placeholders for IN clause
    $diffPlaceholders = [];
    foreach (array_values($difficulties) as $index => $diff) {
      $diffPlaceholders[] = ':diff_' . $index;
    }

    // Construir condición de categoría
    // Prioridad: filtro de sala > categoría del jugador > todas las categorías
    $categoryCondition = "";
    $categoryPlaceholders = [];

    if ($allowedCategories !== null && !empty($allowedCategories)) {
      // Filtro de sala tiene prioridad
      foreach (array_values($allowedCategories) as $index => $catId) {
        $categoryPlaceholders[] = ':cat_' . $index;
      }
      $categoryCondition = "AND q.category_id IN (" . implode(',', $categoryPlaceholders) . ")";
    } elseif ($categoryId !== null) {
      // Categoría específica del jugador
      $categoryCondition = "AND q.category_id = :c";
    }
    // Si ambos son null, no hay filtro de categoría (todas las categorías)

    $sql = "SELECT q.id, q.statement, q.difficulty, q.category_id, q.is_ai_generated, q.admin_verified
            FROM questions q
            WHERE q.is_active = 1
              $categoryCondition
              AND q.difficulty IN (" . implode(',', $diffPlaceholders) . ")
              AND q.admin_verified = 1
              AND q.id NOT IN (
                SELECT pa.question_id
                FROM player_answers pa
                WHERE pa.session_id = :session_id
              )
            ORDER BY
              CASE q.difficulty
                WHEN :preferred_diff THEN 0
                ELSE 1
              END,
              RAND()
            LIMIT 1";

    // Build complete params array with all named parameters
    $params = [
      ':session_id' => $sessionId,
      ':preferred_diff' => $preferredDifficulty
    ];

    // Agregar parámetros de categoría según el caso
    if ($allowedCategories !== null && !empty($allowedCategories)) {
      foreach (array_values($allowedCategories) as $index => $catId) {
        $params[':cat_' . $index] = $catId;
      }
    } elseif ($categoryId !== null) {
      $params[':c'] = $categoryId;
    }

    // Add dynamic difficulty parameters
    foreach (array_values($difficulties) as $index => $diff) {
      $params[':diff_' . $index] = $diff;
    }

    $st = $this->db->pdo()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();

    return $r ? new Question(
      (int)$r['id'],
      $r['statement'],
      (int)$r['difficulty'],
      (int)$r['category_id'],
      (bool)$r['is_ai_generated'],
      (bool)$r['admin_verified']
    ) : null;
  }

  public function find(int $id): ?Question
  {
    $st = $this->db->pdo()->prepare("SELECT id,statement,difficulty,category_id,is_ai_generated,admin_verified FROM questions WHERE id=:id");
    $st->execute([':id' => $id]);
    $r = $st->fetch();
    return $r ? new Question(
      (int)$r['id'],
      $r['statement'],
      (int)$r['difficulty'],
      (int)$r['category_id'],
      (bool)$r['is_ai_generated'],
      (bool)$r['admin_verified']
    ) : null;
  }

  /**
   * Actualiza el enunciado y el estado de verificación de una pregunta
   *
   * @param int $id ID de la pregunta
   * @param string $statement Nuevo enunciado
   * @param bool $isVerified Estado de verificación admin
   * @return bool true si la actualización fue exitosa
   */
  public function update(int $id, string $statement, bool $isVerified): bool
  {
    $sql = "UPDATE questions
            SET statement = :statement, admin_verified = :admin_verified, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':id' => $id,
      ':statement' => $statement,
      ':admin_verified' => $isVerified ? 1 : 0
    ]);
  }

  /**
   * Crea una nueva pregunta en la base de datos
   *
   * @param array $data Array con estructura:
   *   - statement: string (requerido)
   *   - difficulty: int (requerido, 1-5)
   *   - category_id: int (requerido)
   *   - is_active: int (opcional, default 1)
   *   - is_ai_generated: bool (opcional, default false)
   *   - admin_verified: bool (opcional, default false para IA)
   * @return int ID de la pregunta creada
   * @throws \InvalidArgumentException Si faltan campos requeridos
   */
  public function create(array $data): int
  {
    $required = ['statement', 'difficulty', 'category_id'];
    foreach ($required as $field) {
      if (!isset($data[$field])) {
        throw new \InvalidArgumentException("Campo requerido faltante: $field");
      }
    }

    $difficulty = (int)$data['difficulty'];
    if ($difficulty < 1 || $difficulty > 5) {
      throw new \InvalidArgumentException("La dificultad debe estar entre 1 y 5");
    }

    $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $isAiGenerated = isset($data['is_ai_generated']) ? (bool)$data['is_ai_generated'] : false;
    $adminVerified = isset($data['admin_verified']) ? (bool)$data['admin_verified'] : (!$isAiGenerated);

    $sql = "INSERT INTO questions (statement, difficulty, category_id, is_active, is_ai_generated, admin_verified)
            VALUES (:statement, :difficulty, :category_id, :is_active, :is_ai_generated, :admin_verified)";

    $st = $this->db->pdo()->prepare($sql);
    $st->execute([
      ':statement' => $data['statement'],
      ':difficulty' => $difficulty,
      ':category_id' => (int)$data['category_id'],
      ':is_active' => $isActive,
      ':is_ai_generated' => $isAiGenerated ? 1 : 0,
      ':admin_verified' => $adminVerified ? 1 : 0
    ]);

    return (int)$this->db->pdo()->lastInsertId();
  }

  /**
   * Guarda las opciones de una pregunta
   *
   * @param int $questionId ID de la pregunta
   * @param array $options Array de opciones:
   *   [
   *     ['text' => 'Opción A', 'is_correct' => false],
   *     ['text' => 'Opción B', 'is_correct' => true],
   *     ...
   *   ]
   * @return void
   * @throws \InvalidArgumentException Si hay opciones duplicadas o ninguna correcta
   */
  public function saveOptions(int $questionId, array $options): void
  {
    if (empty($options)) {
      throw new \InvalidArgumentException("Una pregunta debe tener al menos 1 opción");
    }

    $correctCount = 0;
    $texts = [];

    foreach ($options as $opt) {
      if (!isset($opt['text']) || !isset($opt['is_correct'])) {
        throw new \InvalidArgumentException("Cada opción debe tener 'text' e 'is_correct'");
      }

      $text = trim($opt['text']);
      if (empty($text)) {
        throw new \InvalidArgumentException("El texto de la opción no puede estar vacío");
      }

      if (in_array($text, $texts)) {
        throw new \InvalidArgumentException("Las opciones no pueden duplicarse");
      }

      $texts[] = $text;
      if ($opt['is_correct']) {
        $correctCount++;
      }
    }

    if ($correctCount !== 1) {
      throw new \InvalidArgumentException("Debe haber exactamente 1 opción correcta");
    }

    $sql = "INSERT INTO question_options (question_id, content, is_correct)
            VALUES (:question_id, :content, :is_correct)";
    $st = $this->db->pdo()->prepare($sql);

    foreach ($options as $opt) {
      $st->execute([
        ':question_id' => $questionId,
        ':content' => trim($opt['text']),
        ':is_correct' => $opt['is_correct'] ? 1 : 0
      ]);
    }
  }

  /**
   * Guarda la explicación de una pregunta
   *
   * @param int $questionId ID de la pregunta
   * @param string $text Texto de la explicación
   * @param string|null $sourceRef Referencia a la fuente (opcional)
   * @param string $explanationType Tipo de explicación: 'correct' o 'incorrect'
   * @return void
   */
  public function saveExplanation(int $questionId, string $text, ?string $sourceRef = null, string $explanationType = 'correct'): void
  {
    if (empty(trim($text))) {
      throw new \InvalidArgumentException("El texto de la explicación no puede estar vacío");
    }

    if (!in_array($explanationType, ['correct', 'incorrect'])) {
      throw new \InvalidArgumentException("El tipo de explicación debe ser 'correct' o 'incorrect'");
    }

    $sql = "INSERT INTO question_explanations (question_id, text, source_ref, explanation_type)
            VALUES (:question_id, :text, :source_ref, :explanation_type)";
    $st = $this->db->pdo()->prepare($sql);

    $st->execute([
      ':question_id' => $questionId,
      ':text' => trim($text),
      ':source_ref' => $sourceRef,
      ':explanation_type' => $explanationType
    ]);
  }

  /**
   * Obtiene la explicación de una pregunta (por compatibilidad, devuelve la explicación 'correct')
   *
   * @param int $questionId ID de la pregunta
   * @return string|null Texto de la explicación o null si no existe
   */
  public function getExplanation(int $questionId): ?string
  {
    return $this->getExplanationByType($questionId, 'correct');
  }

  /**
   * Obtiene la explicación de una pregunta por tipo
   *
   * @param int $questionId ID de la pregunta
   * @param string $explanationType Tipo: 'correct' o 'incorrect'
   * @return string|null Texto de la explicación o null si no existe
   */
  public function getExplanationByType(int $questionId, string $explanationType): ?string
  {
    if (!in_array($explanationType, ['correct', 'incorrect'])) {
      throw new \InvalidArgumentException("El tipo de explicación debe ser 'correct' o 'incorrect'");
    }

    $sql = "SELECT text FROM question_explanations
            WHERE question_id = :question_id AND explanation_type = :explanation_type
            LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([
      ':question_id' => $questionId,
      ':explanation_type' => $explanationType
    ]);
    $result = $st->fetch();
    return $result ? $result['text'] : null;
  }

  /**
   * Obtiene el ID de la opción correcta de una pregunta
   *
   * @param int $questionId ID de la pregunta
   * @return int|null ID de la opción correcta o null si no existe
   */
  public function getCorrectOptionId(int $questionId): ?int
  {
    $sql = "SELECT id FROM question_options WHERE question_id = :question_id AND is_correct = 1 LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':question_id' => $questionId]);
    $result = $st->fetch();
    return $result ? (int)$result['id'] : null;
  }

  /**
   * Retorna la instancia de PDO para operaciones directas
   *
   * @return \PDO
   */
  public function getPdo(): ?\PDO
  {
    return $this->db->pdo();
  }

  /**
   * Obtiene todas las preguntas activas con información de categoría
   *
   * @return array Array de preguntas activas
   */
  public function findAll(): array
  {
    $sql = "SELECT q.id, q.statement, q.difficulty, q.category_id, qc.name AS category_name,
                   q.is_ai_generated, q.admin_verified
            FROM questions q
            LEFT JOIN question_categories qc ON qc.id = q.category_id
            WHERE q.is_active = 1
            ORDER BY q.id DESC";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute();
    return $st->fetchAll() ?: [];
  }

  /**
   * Obtiene todas las preguntas con filtro de estado activo/inactivo
   *
   * @param string|null $statusFilter 'active', 'inactive', o null para todas
   * @return array Array de preguntas
   */
  public function findAllWithInactive(?string $statusFilter = null): array
  {
    $whereClause = match($statusFilter) {
      'active' => "WHERE q.is_active = 1",
      'inactive' => "WHERE q.is_active = 0",
      default => ""
    };

    $sql = "SELECT q.id, q.statement, q.difficulty, q.category_id, qc.name AS category_name,
                   q.is_ai_generated, q.admin_verified, q.is_active
            FROM questions q
            LEFT JOIN question_categories qc ON qc.id = q.category_id
            $whereClause
            ORDER BY q.id DESC";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute();
    return $st->fetchAll() ?: [];
  }

  /**
   * Actualiza el estado de verificación de una pregunta
   *
   * @param int $id ID de la pregunta
   * @param bool $isVerified Estado de verificación admin
   * @return bool true si la actualización fue exitosa
   */
  public function updateVerification(int $id, bool $isVerified): bool
  {
    $sql = "UPDATE questions SET admin_verified = :verified, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':id' => $id,
      ':verified' => $isVerified ? 1 : 0
    ]);
  }

  /**
   * Elimina lógicamente una pregunta (soft delete)
   * Marca is_active = 0 en lugar de eliminar físicamente los datos
   * Esto preserva el historial de respuestas (player_answers) y los datos relacionados
   *
   * @param int $id ID de la pregunta
   * @return bool true si la eliminación lógica fue exitosa
   */
  public function delete(int $id): bool
  {
    try {
      // Eliminación lógica: marcar como inactiva
      // Los datos históricos (player_answers, opciones, explicaciones) se conservan
      $sql = "UPDATE questions SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
      $st = $this->db->pdo()->prepare($sql);
      return $st->execute([':id' => $id]);
    } catch (\Exception $e) {
      error_log("Error in logical delete of question $id: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Restaura una pregunta eliminada lógicamente (reactivar)
   *
   * @param int $id ID de la pregunta
   * @return bool true si la restauración fue exitosa
   */
  public function restore(int $id): bool
  {
    try {
      $sql = "UPDATE questions SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
      $st = $this->db->pdo()->prepare($sql);
      return $st->execute([':id' => $id]);
    } catch (\Exception $e) {
      error_log("Error restoring question $id: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Elimina físicamente una pregunta y todos sus registros relacionados
   * PRECAUCIÓN: Esta operación es irreversible y elimina datos históricos
   *
   * @param int $id ID de la pregunta
   * @return bool true si la eliminación física fue exitosa
   */
  public function hardDelete(int $id): bool
  {
    try {
      // 0. Eliminar respuestas de jugadores relacionadas (player_answers)
      $sqlAnswers = "DELETE FROM player_answers WHERE question_id = :id";
      $stAnswers = $this->db->pdo()->prepare($sqlAnswers);
      $stAnswers->execute([':id' => $id]);

      // 1. Eliminar opciones de la pregunta
      $sqlOptions = "DELETE FROM question_options WHERE question_id = :id";
      $stOptions = $this->db->pdo()->prepare($sqlOptions);
      $stOptions->execute([':id' => $id]);

      // 2. Eliminar explicaciones de la pregunta
      $sqlExplanations = "DELETE FROM question_explanations WHERE question_id = :id";
      $stExplanations = $this->db->pdo()->prepare($sqlExplanations);
      $stExplanations->execute([':id' => $id]);

      // 3. Eliminar la pregunta físicamente
      $sql = "DELETE FROM questions WHERE id = :id";
      $st = $this->db->pdo()->prepare($sql);
      return $st->execute([':id' => $id]);
    } catch (\Exception $e) {
      error_log("Error in hard delete of question $id: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Obtiene las opciones de una pregunta
   *
   * @param int $questionId ID de la pregunta
   * @return array Array de opciones: [{'id' => int, 'text' => string, 'is_correct' => bool}]
   */
  public function getOptionsByQuestionId(int $questionId): array
  {
    $sql = "SELECT id, content AS text, is_correct FROM question_options
            WHERE question_id = :question_id
            ORDER BY id ASC";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':question_id' => $questionId]);
    return $st->fetchAll() ?: [];
  }

  /**
   * Obtiene preguntas no verificadas con información de batch y explicaciones
   *
   * @param int|null $batchId ID del batch (opcional, filtra por batch si se proporciona)
   * @return array Array de preguntas con estado de explicaciones y opciones
   */
  public function getUnverifiedQuestions(?int $batchId = null): array
  {
    $sql = "SELECT
              q.id,
              q.statement,
              q.category_id,
              qc.name as category,
              q.difficulty,
              qb.batch_name,
              qb.batch_type,
              q.is_ai_generated,
              (SELECT COUNT(*) FROM question_explanations WHERE question_id = q.id AND explanation_type = 'correct') as has_correct,
              (SELECT COUNT(*) FROM question_explanations WHERE question_id = q.id AND explanation_type = 'incorrect') as has_incorrect,
              (SELECT COUNT(*) FROM question_options WHERE question_id = q.id) as option_count
            FROM questions q
            LEFT JOIN question_batches qb ON q.batch_id = qb.id
            LEFT JOIN question_categories qc ON q.category_id = qc.id
            WHERE q.admin_verified = 0";

    if ($batchId !== null) {
      $sql .= " AND q.batch_id = :batch_id";
    }

    $sql .= " ORDER BY qb.imported_at DESC, q.created_at DESC";

    $st = $this->db->pdo()->prepare($sql);

    if ($batchId !== null) {
      $st->execute([':batch_id' => $batchId]);
    } else {
      $st->execute();
    }

    return $st->fetchAll() ?: [];
  }

  /**
   * Obtiene todas las preguntas de un batch
   *
   * @param int $batchId ID del batch
   * @return array Array de preguntas
   */
  public function getByBatchId(int $batchId): array
  {
    $sql = "SELECT * FROM questions WHERE batch_id = :batch_id ORDER BY id";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':batch_id' => $batchId]);
    return $st->fetchAll() ?: [];
  }

  /**
   * Actualiza el estado de verificación de una pregunta
   *
   * @param int $questionId ID de la pregunta
   * @param bool $verified Estado de verificación
   * @return bool true si la actualización fue exitosa
   */
  public function updateVerificationStatus(int $questionId, bool $verified): bool
  {
    $sql = "UPDATE questions SET admin_verified = :verified WHERE id = :question_id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':verified' => $verified ? 1 : 0,
      ':question_id' => $questionId
    ]);
  }

  /**
   * Cuenta preguntas no verificadas de un batch
   *
   * @param int $batchId ID del batch
   * @return int Cantidad de preguntas no verificadas
   */
  public function countUnverifiedByBatch(int $batchId): int
  {
    $sql = "SELECT COUNT(*) as count FROM questions WHERE batch_id = :batch_id AND admin_verified = 0";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':batch_id' => $batchId]);
    $result = $st->fetch();
    return $result ? (int)$result['count'] : 0;
  }

  /**
   * Actualiza una pregunta completa incluyendo opciones y explicaciones
   *
   * @param int $questionId ID de la pregunta
   * @param array $data Datos de actualización:
   *   - statement: string (enunciado)
   *   - difficulty: int (1-5)
   *   - category_id: int
   *   - options: array de opciones [{text, is_correct}, ...]
   *   - explanation_correct: string (opcional)
   *   - explanation_incorrect: string (opcional)
   * @return bool true si la actualización fue exitosa
   */
  public function updateFull(int $questionId, array $data): bool
  {
    $pdo = $this->db->pdo();

    try {
      $pdo->beginTransaction();

      // 1. Actualizar pregunta principal
      $sqlQuestion = "UPDATE questions SET
                        statement = :statement,
                        difficulty = :difficulty,
                        category_id = :category_id,
                        admin_verified = 0,
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
      $stQuestion = $pdo->prepare($sqlQuestion);
      $stQuestion->execute([
        ':id' => $questionId,
        ':statement' => $data['statement'],
        ':difficulty' => (int)$data['difficulty'],
        ':category_id' => (int)$data['category_id']
      ]);

      // 2. Actualizar opciones si se proporcionan
      if (isset($data['options']) && is_array($data['options'])) {
        // Eliminar opciones existentes
        $sqlDeleteOptions = "DELETE FROM question_options WHERE question_id = :question_id";
        $stDeleteOptions = $pdo->prepare($sqlDeleteOptions);
        $stDeleteOptions->execute([':question_id' => $questionId]);

        // Insertar nuevas opciones
        $sqlInsertOption = "INSERT INTO question_options (question_id, content, is_correct)
                            VALUES (:question_id, :content, :is_correct)";
        $stInsertOption = $pdo->prepare($sqlInsertOption);

        foreach ($data['options'] as $option) {
          $stInsertOption->execute([
            ':question_id' => $questionId,
            ':content' => trim($option['text']),
            ':is_correct' => $option['is_correct'] ? 1 : 0
          ]);
        }
      }

      // 3. Actualizar explicación correcta si se proporciona
      if (isset($data['explanation_correct']) && !empty(trim($data['explanation_correct']))) {
        // Verificar si existe
        $sqlCheckCorrect = "SELECT id FROM question_explanations
                            WHERE question_id = :question_id AND explanation_type = 'correct'";
        $stCheckCorrect = $pdo->prepare($sqlCheckCorrect);
        $stCheckCorrect->execute([':question_id' => $questionId]);
        $existingCorrect = $stCheckCorrect->fetch();

        if ($existingCorrect) {
          $sqlUpdateCorrect = "UPDATE question_explanations SET text = :text
                               WHERE question_id = :question_id AND explanation_type = 'correct'";
          $stUpdateCorrect = $pdo->prepare($sqlUpdateCorrect);
          $stUpdateCorrect->execute([
            ':question_id' => $questionId,
            ':text' => trim($data['explanation_correct'])
          ]);
        } else {
          $sqlInsertCorrect = "INSERT INTO question_explanations (question_id, text, explanation_type)
                               VALUES (:question_id, :text, 'correct')";
          $stInsertCorrect = $pdo->prepare($sqlInsertCorrect);
          $stInsertCorrect->execute([
            ':question_id' => $questionId,
            ':text' => trim($data['explanation_correct'])
          ]);
        }
      }

      // 4. Actualizar explicación incorrecta si se proporciona
      if (isset($data['explanation_incorrect']) && !empty(trim($data['explanation_incorrect']))) {
        $sqlCheckIncorrect = "SELECT id FROM question_explanations
                              WHERE question_id = :question_id AND explanation_type = 'incorrect'";
        $stCheckIncorrect = $pdo->prepare($sqlCheckIncorrect);
        $stCheckIncorrect->execute([':question_id' => $questionId]);
        $existingIncorrect = $stCheckIncorrect->fetch();

        if ($existingIncorrect) {
          $sqlUpdateIncorrect = "UPDATE question_explanations SET text = :text
                                 WHERE question_id = :question_id AND explanation_type = 'incorrect'";
          $stUpdateIncorrect = $pdo->prepare($sqlUpdateIncorrect);
          $stUpdateIncorrect->execute([
            ':question_id' => $questionId,
            ':text' => trim($data['explanation_incorrect'])
          ]);
        } else {
          $sqlInsertIncorrect = "INSERT INTO question_explanations (question_id, text, explanation_type)
                                 VALUES (:question_id, :text, 'incorrect')";
          $stInsertIncorrect = $pdo->prepare($sqlInsertIncorrect);
          $stInsertIncorrect->execute([
            ':question_id' => $questionId,
            ':text' => trim($data['explanation_incorrect'])
          ]);
        }
      }

      $pdo->commit();
      return true;
    } catch (\Exception $e) {
      $pdo->rollBack();
      error_log("Error updating question full: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Obtiene una pregunta completa con opciones y explicaciones
   *
   * @param int $questionId ID de la pregunta
   * @return array|null Datos completos de la pregunta o null si no existe
   */
  public function getFullQuestion(int $questionId): ?array
  {
    $pdo = $this->db->pdo();

    // Obtener pregunta
    $sqlQuestion = "SELECT q.id, q.statement, q.difficulty, q.category_id, qc.name as category_name,
                          q.is_ai_generated, q.admin_verified, q.batch_id
                    FROM questions q
                    LEFT JOIN question_categories qc ON q.category_id = qc.id
                    WHERE q.id = :id";
    $stQuestion = $pdo->prepare($sqlQuestion);
    $stQuestion->execute([':id' => $questionId]);
    $question = $stQuestion->fetch(\PDO::FETCH_ASSOC);

    if (!$question) {
      return null;
    }

    // Obtener opciones
    $sqlOptions = "SELECT id, content as text, is_correct FROM question_options
                  WHERE question_id = :question_id ORDER BY id";
    $stOptions = $pdo->prepare($sqlOptions);
    $stOptions->execute([':question_id' => $questionId]);
    $options = $stOptions->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // Obtener explicaciones
    $sqlExplanations = "SELECT id, text, explanation_type FROM question_explanations
                        WHERE question_id = :question_id";
    $stExplanations = $pdo->prepare($sqlExplanations);
    $stExplanations->execute([':question_id' => $questionId]);
    $explanations = $stExplanations->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return [
      'id' => (int)$question['id'],
      'statement' => $question['statement'],
      'difficulty' => (int)$question['difficulty'],
      'category_id' => (int)$question['category_id'],
      'category_name' => $question['category_name'],
      'is_ai_generated' => (bool)$question['is_ai_generated'],
      'admin_verified' => (bool)$question['admin_verified'],
      'batch_id' => $question['batch_id'] ? (int)$question['batch_id'] : null,
      'options' => array_map(fn($o) => [
        'id' => (int)$o['id'],
        'text' => $o['text'],
        'is_correct' => (bool)$o['is_correct']
      ], $options),
      'explanations' => array_map(fn($e) => [
        'id' => (int)$e['id'],
        'text' => $e['text'],
        'type' => $e['explanation_type']
      ], $explanations)
    ];
  }

  /**
   * Crea una pregunta asociada a un batch
   *
   * @param array $questionData Datos de la pregunta
   * @param int $batchId ID del batch
   * @return int|false ID de la pregunta creada o false en caso de error
   */
  public function createWithBatch(array $questionData, int $batchId): int|false
  {
    try {
      $sql = "INSERT INTO questions (statement, difficulty, category_id, source_id, batch_id, language, is_ai_generated, admin_verified, is_active)
              VALUES (:statement, :difficulty, :category_id, :source_id, :batch_id, :language, 0, 0, 1)";

      $st = $this->db->pdo()->prepare($sql);
      $success = $st->execute([
        ':statement' => $questionData['statement'],
        ':difficulty' => (int)$questionData['difficulty'],
        ':category_id' => (int)$questionData['category_id'],
        ':source_id' => $questionData['source_id'] ?? null,
        ':batch_id' => $batchId,
        ':language' => $questionData['language'] ?? 'es'
      ]);

      if ($success) {
        return (int)$this->db->pdo()->lastInsertId();
      }

      return false;
    } catch (\Exception $e) {
      error_log("Error creating question with batch: " . $e->getMessage());
      return false;
    }
  }
}
