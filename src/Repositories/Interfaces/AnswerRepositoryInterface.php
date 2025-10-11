<?php
namespace Src\Repositories\Interfaces;

interface AnswerRepositoryInterface {
  public function register(
    int $sessionId, int $questionId, ?int $optionId,
    bool $isCorrect, float $timeTaken, float $difficulty
  ): void;
}
