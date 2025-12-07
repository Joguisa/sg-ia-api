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
    // 🔍 LOGGING DIAGNÓSTICO - Punto 2: Ver preguntas ya respondidas
    error_log("=== DIAGNOSTICO QUESTION REPOSITORY ===");
    error_log("Buscando pregunta para: CategoryID=$categoryId, Difficulty=$difficulty, SessionID=$sessionId");

    // Primero, obtener las preguntas ya respondidas para esta sesión
    $answeredQuery = "SELECT pa.question_id, pa.answered_at
                      FROM player_answers pa
                      WHERE pa.session_id = :session_id
                      ORDER BY pa.answered_at DESC";
    $stAnswered = $this->db->pdo()->prepare($answeredQuery);
    $stAnswered->execute([':session_id' => $sessionId]);
    $answeredQuestions = $stAnswered->fetchAll();
    error_log("Preguntas ya respondidas en sesión $sessionId: " . json_encode(array_column($answeredQuestions, 'question_id')));
    error_log("Total preguntas respondidas: " . count($answeredQuestions));

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

    error_log("Estrategia de búsqueda - Dificultades a intentar: " . json_encode($difficulties));

    $sql = "SELECT q.id, q.statement, q.difficulty, q.category_id, q.is_ai_generated, q.admin_verified
            FROM questions q
            WHERE q.is_active = 1
              AND q.category_id = :c
              AND q.difficulty IN (" . implode(',', array_fill(0, count($difficulties), '?')) . ")
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

    $st = $this->db->pdo()->prepare($sql);
    $params = [':c' => $categoryId, ':session_id' => $sessionId, ':preferred_diff' => $difficulty];

    // Agregar dificultades como parámetros posicionales
    foreach ($difficulties as $index => $diff) {
      $st->bindValue($index + 1, $diff, \PDO::PARAM_INT);
    }

    // Ejecutar con parámetros nombrados
    foreach ($params as $key => $value) {
      $st->bindValue($key, $value);
    }

    $st->execute();
    $r = $st->fetch();

    if ($r) {
      error_log("✅ Pregunta seleccionada - ID: {$r['id']}, Difficulty: {$r['difficulty']} " .
                ($r['difficulty'] != $difficulty ? "(FALLBACK desde dificultad $difficulty)" : "(EXACTA)"));
    } else {
      error_log("❌ No se encontró ninguna pregunta disponible ni en dificultades cercanas");
    }

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
   * @return void
   */
  public function saveExplanation(int $questionId, string $text, ?string $sourceRef = null): void
  {
    if (empty(trim($text))) {
      throw new \InvalidArgumentException("El texto de la explicación no puede estar vacío");
    }

    $sql = "INSERT INTO question_explanations (question_id, text, source_ref)
            VALUES (:question_id, :text, :source_ref)";
    $st = $this->db->pdo()->prepare($sql);

    $st->execute([
      ':question_id' => $questionId,
      ':text' => trim($text),
      ':source_ref' => $sourceRef
    ]);
  }

  /**
   * Obtiene la explicación de una pregunta
   *
   * @param int $questionId ID de la pregunta
   * @return string|null Texto de la explicación o null si no existe
   */
  public function getExplanation(int $questionId): ?string
  {
    $sql = "SELECT text FROM question_explanations WHERE question_id = :question_id LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':question_id' => $questionId]);
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
   * Elimina una pregunta y todos sus registros relacionados
   *
   * @param int $id ID de la pregunta
   * @return bool true si la eliminación fue exitosa
   */
  public function delete(int $id): bool
  {
    try {
      // 0. Eliminar respuestas de jugadores relacionadas (player_answers)
      // Esto es necesario porque las respuestas hacen referencia a las opciones
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

      // 3. Eliminar la pregunta
      $sql = "DELETE FROM questions WHERE id = :id";
      $st = $this->db->pdo()->prepare($sql);
      return $st->execute([':id' => $id]);
    } catch (\Exception $e) {
      error_log('Error deleting question: ' . $e->getMessage());
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
}
