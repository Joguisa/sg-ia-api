<?php
namespace Src\Repositories\Interfaces;
use Src\Models\Question;

interface QuestionRepositoryInterface {
  public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question;
  public function find(int $id): ?Question;
}
