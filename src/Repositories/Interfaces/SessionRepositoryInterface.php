<?php
namespace Src\Repositories\Interfaces;
use Src\Models\GameSession;

interface SessionRepositoryInterface {
  public function start(int $playerId, float $difficulty, ?int $roomId = null): GameSession;
  public function get(int $id): ?GameSession;
  public function updateProgress(int $id, int $score, int $lives, string $status, float $newDifficulty): void;
}
