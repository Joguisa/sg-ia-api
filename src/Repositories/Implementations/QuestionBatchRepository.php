<?php

namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Repositories\Interfaces\QuestionBatchRepositoryInterface;

final class QuestionBatchRepository implements QuestionBatchRepositoryInterface
{
  public function __construct(private Connection $db) {}

  public function create(array $batchData): int
  {
    $required = ['batch_name', 'batch_type', 'total_questions'];
    foreach ($required as $field) {
      if (!isset($batchData[$field])) {
        throw new \InvalidArgumentException("Campo requerido faltante: $field");
      }
    }

    // Validar batch_type
    $validTypes = ['ai_generated', 'csv_imported'];
    if (!in_array($batchData['batch_type'], $validTypes)) {
      throw new \InvalidArgumentException("batch_type debe ser 'ai_generated' o 'csv_imported'");
    }

    $sql = "INSERT INTO question_batches (batch_name, batch_type, description, ai_provider_used, total_questions, status, imported_at)
            VALUES (:batch_name, :batch_type, :description, :ai_provider_used, :total_questions, 'pending', NOW())";

    $st = $this->db->pdo()->prepare($sql);
    $st->execute([
      ':batch_name' => $batchData['batch_name'],
      ':batch_type' => $batchData['batch_type'],
      ':description' => $batchData['description'] ?? null,
      ':ai_provider_used' => $batchData['ai_provider_used'] ?? null,
      ':total_questions' => (int)$batchData['total_questions']
    ]);

    return (int)$this->db->pdo()->lastInsertId();
  }

  public function getById(int $batchId): ?array
  {
    $sql = "SELECT * FROM question_batches WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':id' => $batchId]);
    $result = $st->fetch();
    return $result ?: null;
  }

  public function getAll(): array
  {
    $sql = "SELECT * FROM question_batches ORDER BY imported_at DESC";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute();
    return $st->fetchAll() ?: [];
  }

  public function updateVerificationCount(int $batchId): bool
  {
    $sql = "UPDATE question_batches
            SET verified_count = (
              SELECT COUNT(*) FROM questions
              WHERE batch_id = :batch_id AND admin_verified = 1
            )
            WHERE id = :id";

    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':batch_id' => $batchId,
      ':id' => $batchId
    ]);
  }

  public function updateStatus(int $batchId, string $status): bool
  {
    $validStatuses = ['pending', 'partial', 'complete'];
    if (!in_array($status, $validStatuses)) {
      throw new \InvalidArgumentException("Status debe ser 'pending', 'partial' o 'complete'");
    }

    $sql = "UPDATE question_batches SET status = :status WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':status' => $status,
      ':id' => $batchId
    ]);
  }

  public function updateAiProvider(int $batchId, string $aiProvider): bool
  {
    $sql = "UPDATE question_batches SET ai_provider_used = :ai_provider WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':ai_provider' => $aiProvider,
      ':id' => $batchId
    ]);
  }
}
