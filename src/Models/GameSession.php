<?php
namespace Src\Models;
final class GameSession {
  public function __construct(
    public int $id,
    public int $playerId,
    public float $currentDifficulty,
    public string $status='active',
    public int $score=0,
    public int $lives=3
  ) {}
}
