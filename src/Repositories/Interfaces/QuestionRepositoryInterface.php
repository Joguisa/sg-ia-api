<?php

namespace Src\Repositories\Interfaces;

use Src\Models\Question;

interface QuestionRepositoryInterface
{
  public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question;
  public function getRandomByDifficultyExcludingAnswered(int $categoryId, int $difficulty, int $sessionId): ?Question;
  public function find(int $id): ?Question;
  public function update(int $id, string $statement, bool $isVerified): bool;
  public function create(array $data): int;
  public function saveOptions(int $questionId, array $options): void;
  public function saveExplanation(int $questionId, string $text, ?string $sourceRef = null): void;
  public function getExplanation(int $questionId): ?string;
  public function getCorrectOptionId(int $questionId): ?int;
  public function getOptionsByQuestionId(int $questionId): array;
  public function getPdo(): ?\PDO;
  public function findAll(): array;
  public function updateVerification(int $id, bool $isVerified): bool;
  public function delete(int $id): bool;
}
