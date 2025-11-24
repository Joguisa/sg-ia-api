<?php
namespace Src\Controllers;
use Src\Utils\Response;
use Src\Database\Connection;

final class StatisticsController {
  public function __construct(private Connection $db) {}

  /**
   * Obtener estadísticas de una sesión
   *
   * Endpoint: GET /stats/session/{id}
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function session(array $params): void {
    $st = $this->db->pdo()->prepare("SELECT * FROM v_session_stats WHERE session_id=:id");
    $st->execute([':id'=>(int)$params['id']]);
    $r = $st->fetch();
    if (!$r) Response::json(['ok'=>false,'error'=>'Sin datos'],404);
    else Response::json(['ok'=>true,'stats'=>$r]);
  }

  /**
   * Obtener estadísticas globales de un jugador
   *
   * Endpoint: GET /stats/player/{id}
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function playerStats(array $params): void {
    $playerId = (int)($params['id'] ?? 0);

    if ($playerId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid player ID'], 400);
      return;
    }

    try {
      $pdo = $this->db->pdo();

      // Verificar que el jugador existe
      $checkStmt = $pdo->prepare("SELECT id FROM players WHERE id = ?");
      $checkStmt->execute([$playerId]);
      if (!$checkStmt->fetch()) {
        Response::json(['ok'=>false,'error'=>'Player not found'], 404);
        return;
      }

      // Obtener estadísticas globales de game_sessions
      $globalStmt = $pdo->prepare("
        SELECT
          COUNT(DISTINCT id) AS total_games,
          COALESCE(MAX(score), 0) AS high_score,
          COALESCE(SUM(score), 0) AS total_score,
          COALESCE(AVG(score), 0) AS avg_score,
          COALESCE((SELECT AVG(current_difficulty) FROM game_sessions WHERE player_id = ?), 0) AS avg_difficulty
        FROM game_sessions
        WHERE player_id = ?
      ");
      $globalStmt->execute([$playerId, $playerId]);
      $global = $globalStmt->fetch(\PDO::FETCH_ASSOC);

      // Obtener estadísticas por categoría de la vista v_player_topic_stats
      $topicsStmt = $pdo->prepare("
        SELECT
          category_id,
          category_name,
          answers,
          accuracy,
          avg_time_sec
        FROM v_player_topic_stats
        WHERE player_id = ?
        ORDER BY category_name
      ");
      $topicsStmt->execute([$playerId]);
      $topics = $topicsStmt->fetchAll(\PDO::FETCH_ASSOC);

      Response::json([
        'ok' => true,
        'player_id' => $playerId,
        'global' => [
          'total_games' => (int)($global['total_games'] ?? 0),
          'high_score' => (int)($global['high_score'] ?? 0),
          'total_score' => (int)($global['total_score'] ?? 0),
          'avg_score' => (float)($global['avg_score'] ?? 0),
          'avg_difficulty' => (float)($global['avg_difficulty'] ?? 0)
        ],
        'topics' => array_map(fn($t) => [
          'category_id' => (int)$t['category_id'],
          'category_name' => $t['category_name'],
          'answers' => (int)$t['answers'],
          'accuracy' => (float)($t['accuracy'] ?? 0),
          'avg_time_sec' => (float)($t['avg_time_sec'] ?? 0)
        ], $topics)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch player stats: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener el top 10 de jugadores por puntuación
   *
   * Endpoint: GET /stats/leaderboard
   *
   * @return void
   */
  public function leaderboard(): void {
    try {
      $pdo = $this->db->pdo();

      $stmt = $pdo->prepare("
        SELECT
          p.id AS player_id,
          p.name AS player_name,
          p.age,
          MAX(gs.score) AS high_score,
          COUNT(DISTINCT gs.id) AS total_games,
          COALESCE(SUM(gs.score), 0) AS total_score,
          ROUND(AVG(CASE WHEN pa.is_correct = 1 THEN 100 ELSE 0 END), 2) AS overall_accuracy
        FROM players p
        LEFT JOIN game_sessions gs ON gs.player_id = p.id
        LEFT JOIN player_answers pa ON pa.session_id = gs.id
        GROUP BY p.id, p.name, p.age
        ORDER BY MAX(gs.score) DESC
        LIMIT 10
      ");
      $stmt->execute();
      $leaderboard = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      Response::json([
        'ok' => true,
        'leaderboard' => array_map(fn($row) => [
          'rank' => 0, // Se asignará después
          'player_id' => (int)$row['player_id'],
          'player_name' => $row['player_name'],
          'age' => (int)$row['age'],
          'high_score' => (int)($row['high_score'] ?? 0),
          'total_games' => (int)($row['total_games'] ?? 0),
          'total_score' => (int)($row['total_score'] ?? 0),
          'overall_accuracy' => (float)($row['overall_accuracy'] ?? 0)
        ], $leaderboard)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch leaderboard: ' . $e->getMessage()], 500);
    }
  }
}
