<?php

namespace Src\Models;

final class QuestionBatch
{
  public function __construct(
    public int $id,
    public string $batchName,
    public string $batchType,        // 'ai_generated' o 'csv_imported'
    public ?string $description,
    public int $totalQuestions,
    public int $verifiedCount,
    public string $status,            // 'pending', 'partial', 'complete'
    public string $importedAt
  ) {}
}
