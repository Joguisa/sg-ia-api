<?php
namespace Src\Models;

final class GameSession {
  public function __construct(
    public int $id,
    public int $playerId,
    public ?int $roomId = null,
    public float $currentDifficulty = 1.0,
    public string $status = 'active',
    public int $score = 0,
    public int $lives = 3
  ) {}
}
