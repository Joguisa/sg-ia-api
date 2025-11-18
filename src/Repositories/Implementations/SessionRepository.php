<?php
namespace Src\Repositories\Implementations;
use Src\Database\Connection;
use Src\Models\GameSession;
use Src\Repositories\Interfaces\SessionRepositoryInterface;

final class SessionRepository implements SessionRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function start(int $playerId, float $difficulty): GameSession {
    // Validar rango
    if ($difficulty < 1.00 || $difficulty > 5.00) {
      throw new \RangeError("Dificultad debe estar entre 1.00 y 5.00");
    }

    $st = $this->db->pdo()->prepare(
      "INSERT INTO game_sessions(player_id, current_difficulty) VALUES(:p, :d)"
    );
    $st->execute([':p' => $playerId, ':d' => $difficulty]);
    $id = (int)$this->db->pdo()->lastInsertId();
    return new GameSession($id, $playerId, $difficulty, 'active');
  }

  public function get(int $id): ?GameSession {
    $st = $this->db->pdo()->prepare(
      "SELECT id, player_id, current_difficulty, score, lives, status
       FROM game_sessions WHERE id=:id"
    );
    $st->execute([':id' => $id]);
    $r = $st->fetch();
    return $r ? new GameSession(
      (int)$r['id'],
      (int)$r['player_id'],
      (float)$r['current_difficulty'],
      $r['status'],
      (int)$r['score'],
      (int)$r['lives']
    ) : null;
  }

  /**
   * Actualiza progreso Y dificultad adaptada.
   * CRÍTICO: current_difficulty ahora se persiste en BD.
   */
  public function updateProgress(
    int $id,
    int $score,
    int $lives,
    string $status,
    float $newDifficulty
  ): void {
    if ($newDifficulty < 1.00 || $newDifficulty > 5.00) {
      throw new \RangeError("Dificultad debe estar entre 1.00 y 5.00");
    }

    $st = $this->db->pdo()->prepare(
      "UPDATE game_sessions
       SET score=:s, lives=:l, status=:st, current_difficulty=:d
       WHERE id=:id"
    );
    $st->execute([
      ':s' => $score,
      ':l' => $lives,
      ':st' => $status,
      ':d' => $newDifficulty,
      ':id' => $id
    ]);
  }
}
