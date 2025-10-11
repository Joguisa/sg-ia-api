<?php
namespace Src\Models;
final class PlayerAnswer {
  public function __construct(
    public int $sessionId,
    public int $questionId,
    public ?int $selectedOptionId,
    public bool $isCorrect,
    public float $timeTaken,
    public float $difficulty
  ) {}
}
