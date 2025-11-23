<?php
namespace Src\Models;

final class Question {
  public function __construct(
    public int $id,
    public string $statement,
    public int $difficulty,
    public int $categoryId,
    public bool $isAiGenerated = false,
    public bool $adminVerified = true
  ) {}
}
