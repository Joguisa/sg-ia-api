<?php
namespace Src\Repositories\Implementations;
use Src\Database\Connection;
use Src\Models\Question;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;

final class QuestionRepository implements QuestionRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id,q.is_ai_generated,q.admin_verified
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d AND q.admin_verified=1
            ORDER BY q.id DESC LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':c'=>$categoryId, ':d'=>$difficulty]);
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

  public function find(int $id): ?Question {
    $st = $this->db->pdo()->prepare("SELECT id,statement,difficulty,category_id,is_ai_generated,admin_verified FROM questions WHERE id=:id");
    $st->execute([':id'=>$id]);
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
  public function update(int $id, string $statement, bool $isVerified): bool {
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
  public function create(array $data): int {
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
  public function saveOptions(int $questionId, array $options): void {
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
  public function saveExplanation(int $questionId, string $text, ?string $sourceRef = null): void {
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
  public function getExplanation(int $questionId): ?string {
    $sql = "SELECT text FROM question_explanations WHERE question_id = :question_id LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':question_id' => $questionId]);
    $result = $st->fetch();
    return $result ? $result['text'] : null;
  }

  /**
   * Retorna la instancia de PDO para operaciones directas
   *
   * @return \PDO
   */
  public function getPdo(): ?\PDO {
    return $this->db->pdo();
  }
}
