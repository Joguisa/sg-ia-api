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
          accuracy_percent,
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
          'accuracy' => (float)($t['accuracy_percent'] ?? 0),
          'avg_time_sec' => (float)($t['avg_time_sec'] ?? 0)
        ], $topics)
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch player stats: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener las sesiones recientes de un jugador
   *
   * Endpoint: GET /stats/player/{id}/sessions
   * Query params: ?limit=10 (opcional, default 10)
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function playerSessions(array $params): void {
    $playerId = (int)($params['id'] ?? 0);

    if ($playerId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid player ID'], 400);
      return;
    }

    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;

    try {
      $pdo = $this->db->pdo();

      // Verificar que el jugador existe
      $checkStmt = $pdo->prepare("SELECT id, name FROM players WHERE id = ?");
      $checkStmt->execute([$playerId]);
      $player = $checkStmt->fetch(\PDO::FETCH_ASSOC);

      if (!$player) {
        Response::json(['ok'=>false,'error'=>'Player not found'], 404);
        return;
      }

      // Obtener sesiones recientes con estadísticas
      $sql = "SELECT
                gs.id AS session_id,
                gs.score,
                gs.status,
                gs.current_difficulty,
                gs.started_at,
                gs.ended_at,
                gr.name AS room_name,
                gr.room_code,
                (SELECT COUNT(*) FROM player_answers WHERE session_id = gs.id) AS total_answers,
                (SELECT COUNT(*) FROM player_answers WHERE session_id = gs.id AND is_correct = 1) AS correct_answers
              FROM game_sessions gs
              LEFT JOIN game_rooms gr ON gs.room_id = gr.id
              WHERE gs.player_id = :player_id
              ORDER BY gs.started_at DESC
              LIMIT :limit";

      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':player_id', $playerId, \PDO::PARAM_INT);
      $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
      $stmt->execute();
      $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Formatear respuesta
      $formattedSessions = array_map(function($s) {
        $totalAnswers = (int)$s['total_answers'];
        $correctAnswers = (int)$s['correct_answers'];
        $accuracy = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 1) : 0;

        return [
          'session_id' => (int)$s['session_id'],
          'score' => (int)$s['score'],
          'status' => $s['status'],
          'difficulty' => (float)$s['current_difficulty'],
          'started_at' => $s['started_at'],
          'ended_at' => $s['ended_at'],
          'room' => $s['room_name'] ? [
            'name' => $s['room_name'],
            'code' => $s['room_code']
          ] : null,
          'stats' => [
            'total_answers' => $totalAnswers,
            'correct_answers' => $correctAnswers,
            'incorrect_answers' => $totalAnswers - $correctAnswers,
            'accuracy' => $accuracy
          ]
        ];
      }, $sessions);

      Response::json([
        'ok' => true,
        'player_id' => $playerId,
        'player_name' => $player['name'],
        'sessions' => $formattedSessions
      ], 200);

    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch player sessions: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener historial detallado de respuestas de una sesión
   * Incluye pregunta, opciones, respuesta seleccionada, respuesta correcta y estado
   *
   * Endpoint: GET /stats/session/{id}/answers
   * Query params: ?errors_only=1 (opcional, para filtrar solo errores)
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function sessionAnswers(array $params): void {
    $sessionId = (int)($params['id'] ?? 0);

    if ($sessionId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid session ID'], 400);
      return;
    }

    // Parámetro opcional para filtrar solo errores
    $errorsOnly = isset($_GET['errors_only']) && $_GET['errors_only'] === '1';

    try {
      $pdo = $this->db->pdo();

      // Verificar que la sesión existe
      $checkStmt = $pdo->prepare("SELECT id, player_id, score FROM game_sessions WHERE id = ?");
      $checkStmt->execute([$sessionId]);
      $session = $checkStmt->fetch(\PDO::FETCH_ASSOC);

      if (!$session) {
        Response::json(['ok'=>false,'error'=>'Session not found'], 404);
        return;
      }

      // Construir condición WHERE según filtro
      $whereCondition = $errorsOnly ? "AND pa.is_correct = 0" : "";

      // Obtener todas las respuestas con detalles completos
      $sql = "SELECT
                pa.id AS answer_id,
                pa.answered_at,
                pa.is_correct,
                pa.time_taken_seconds,
                q.id AS question_id,
                q.statement AS question_statement,
                q.difficulty AS question_difficulty,
                qc.name AS category_name,
                qo_selected.id AS selected_option_id,
                qo_selected.content AS selected_option_text,
                qo_correct.id AS correct_option_id,
                qo_correct.content AS correct_option_text,
                qe_correct.text AS explanation_correct,
                qe_incorrect.text AS explanation_incorrect
              FROM player_answers pa
              INNER JOIN questions q ON pa.question_id = q.id
              LEFT JOIN question_categories qc ON q.category_id = qc.id
              LEFT JOIN question_options qo_selected ON pa.selected_option_id = qo_selected.id
              LEFT JOIN question_options qo_correct ON pa.question_id = qo_correct.question_id AND qo_correct.is_correct = 1
              LEFT JOIN question_explanations qe_correct ON q.id = qe_correct.question_id AND qe_correct.explanation_type = 'correct'
              LEFT JOIN question_explanations qe_incorrect ON q.id = qe_incorrect.question_id AND qe_incorrect.explanation_type = 'incorrect'
              WHERE pa.session_id = :session_id
              $whereCondition
              ORDER BY pa.answered_at ASC";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([':session_id' => $sessionId]);
      $answers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Si no hay respuestas (filtrado o no), retornar respuesta vacía
      if (empty($answers)) {
        // Obtener estadísticas generales de la sesión (sin filtro)
        $allAnswersStmt = $pdo->prepare("
          SELECT COUNT(*) as total,
                 SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct
          FROM player_answers WHERE session_id = ?
        ");
        $allAnswersStmt->execute([$sessionId]);
        $allStats = $allAnswersStmt->fetch(\PDO::FETCH_ASSOC);

        $total = (int)($allStats['total'] ?? 0);
        $correct = (int)($allStats['correct'] ?? 0);

        Response::json([
          'ok' => true,
          'session_id' => $sessionId,
          'player_id' => (int)$session['player_id'],
          'score' => (int)$session['score'],
          'summary' => [
            'total_answers' => $total,
            'correct' => $correct,
            'incorrect' => $total - $correct,
            'accuracy' => $total > 0 ? round(($correct / $total) * 100, 2) : 0
          ],
          'filter' => $errorsOnly ? 'errors_only' : 'all',
          'answers' => []
        ], 200);
        return;
      }

      // Calcular estadísticas de la sesión
      $totalAnswers = count($answers);
      $correctAnswers = count(array_filter($answers, fn($a) => (bool)$a['is_correct']));
      $incorrectAnswers = $totalAnswers - $correctAnswers;
      $accuracy = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0;

      // Obtener todas las opciones para cada pregunta
      $questionIds = array_unique(array_column($answers, 'question_id'));
      $optionsByQuestion = [];

      $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
      $optionsStmt = $pdo->prepare("
        SELECT question_id, id, content, is_correct
        FROM question_options
        WHERE question_id IN ($placeholders)
        ORDER BY question_id, id
      ");
      $optionsStmt->execute($questionIds);
      $allOptions = $optionsStmt->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($allOptions as $opt) {
        $qId = (int)$opt['question_id'];
        if (!isset($optionsByQuestion[$qId])) {
          $optionsByQuestion[$qId] = [];
        }
        $optionsByQuestion[$qId][] = [
          'id' => (int)$opt['id'],
          'text' => $opt['content'],
          'is_correct' => (bool)$opt['is_correct']
        ];
      }

      // Formatear respuesta
      $formattedAnswers = array_map(function($a) use ($optionsByQuestion) {
        $qId = (int)$a['question_id'];
        return [
          'answer_id' => (int)$a['answer_id'],
          'answered_at' => $a['answered_at'],
          'is_correct' => (bool)$a['is_correct'],
          'time_to_answer' => $a['time_taken_seconds'] ? (float)$a['time_taken_seconds'] : null,
          'question' => [
            'id' => $qId,
            'statement' => $a['question_statement'],
            'difficulty' => (int)$a['question_difficulty'],
            'category' => $a['category_name'],
            'options' => $optionsByQuestion[$qId] ?? []
          ],
          'selected_option' => [
            'id' => $a['selected_option_id'] ? (int)$a['selected_option_id'] : null,
            'text' => $a['selected_option_text']
          ],
          'correct_option' => [
            'id' => $a['correct_option_id'] ? (int)$a['correct_option_id'] : null,
            'text' => $a['correct_option_text']
          ],
          'explanation' => (bool)$a['is_correct']
            ? $a['explanation_correct']
            : $a['explanation_incorrect']
        ];
      }, $answers);

      Response::json([
        'ok' => true,
        'session_id' => $sessionId,
        'player_id' => (int)$session['player_id'],
        'score' => (int)$session['score'],
        'summary' => [
          'total_answers' => $totalAnswers,
          'correct' => $correctAnswers,
          'incorrect' => $incorrectAnswers,
          'accuracy' => $accuracy
        ],
        'filter' => $errorsOnly ? 'errors_only' : 'all',
        'answers' => $formattedAnswers
      ], 200);

    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch session answers: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener rachas (streaks) de un jugador
   * Calcula racha actual y racha máxima en tiempo real
   *
   * Endpoint: GET /stats/player/{id}/streaks
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function playerStreaks(array $params): void {
    $playerId = (int)($params['id'] ?? 0);

    if ($playerId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid player ID'], 400);
      return;
    }

    try {
      $pdo = $this->db->pdo();

      // Verificar que el jugador existe
      $checkStmt = $pdo->prepare("SELECT id, name FROM players WHERE id = ?");
      $checkStmt->execute([$playerId]);
      $player = $checkStmt->fetch(\PDO::FETCH_ASSOC);

      if (!$player) {
        Response::json(['ok'=>false,'error'=>'Player not found'], 404);
        return;
      }

      // Obtener todas las respuestas del jugador ordenadas por fecha (más reciente primero)
      $sql = "SELECT pa.is_correct, pa.answered_at, gs.id AS session_id
              FROM player_answers pa
              INNER JOIN game_sessions gs ON pa.session_id = gs.id
              WHERE gs.player_id = :player_id
              ORDER BY pa.answered_at DESC";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([':player_id' => $playerId]);
      $answers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Calcular racha actual (respuestas correctas consecutivas desde la última)
      $currentStreak = 0;
      foreach ($answers as $answer) {
        if ((bool)$answer['is_correct']) {
          $currentStreak++;
        } else {
          break; // Se rompe la racha al encontrar una respuesta incorrecta
        }
      }

      // Calcular racha máxima (mejor racha histórica)
      // Necesitamos recorrer en orden cronológico para esto
      $answersChronological = array_reverse($answers);
      $maxStreak = 0;
      $tempStreak = 0;

      foreach ($answersChronological as $answer) {
        if ((bool)$answer['is_correct']) {
          $tempStreak++;
          if ($tempStreak > $maxStreak) {
            $maxStreak = $tempStreak;
          }
        } else {
          $tempStreak = 0; // Reset streak on wrong answer
        }
      }

      // Estadísticas adicionales
      $totalAnswers = count($answers);
      $correctAnswers = count(array_filter($answers, fn($a) => (bool)$a['is_correct']));

      Response::json([
        'ok' => true,
        'player_id' => $playerId,
        'player_name' => $player['name'],
        'streaks' => [
          'current' => $currentStreak,
          'max' => $maxStreak,
          'is_on_streak' => $currentStreak > 0
        ],
        'stats' => [
          'total_answers' => $totalAnswers,
          'correct_answers' => $correctAnswers,
          'accuracy' => $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0
        ]
      ], 200);

    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch player streaks: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtener rachas (streaks) de una sesión específica
   *
   * Endpoint: GET /stats/session/{id}/streaks
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function sessionStreaks(array $params): void {
    $sessionId = (int)($params['id'] ?? 0);

    if ($sessionId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid session ID'], 400);
      return;
    }

    try {
      $pdo = $this->db->pdo();

      // Verificar que la sesión existe
      $checkStmt = $pdo->prepare("SELECT gs.id, gs.player_id, p.name AS player_name
                                   FROM game_sessions gs
                                   INNER JOIN players p ON gs.player_id = p.id
                                   WHERE gs.id = ?");
      $checkStmt->execute([$sessionId]);
      $session = $checkStmt->fetch(\PDO::FETCH_ASSOC);

      if (!$session) {
        Response::json(['ok'=>false,'error'=>'Session not found'], 404);
        return;
      }

      // Obtener respuestas de la sesión ordenadas cronológicamente
      $sql = "SELECT is_correct, answered_at
              FROM player_answers
              WHERE session_id = :session_id
              ORDER BY answered_at ASC";

      $stmt = $pdo->prepare($sql);
      $stmt->execute([':session_id' => $sessionId]);
      $answers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Calcular racha máxima de la sesión
      $maxStreak = 0;
      $currentStreak = 0;
      $allStreaks = [];

      foreach ($answers as $answer) {
        if ((bool)$answer['is_correct']) {
          $currentStreak++;
          if ($currentStreak > $maxStreak) {
            $maxStreak = $currentStreak;
          }
        } else {
          if ($currentStreak > 0) {
            $allStreaks[] = $currentStreak;
          }
          $currentStreak = 0;
        }
      }

      // Agregar la última racha si terminó en respuestas correctas
      if ($currentStreak > 0) {
        $allStreaks[] = $currentStreak;
      }

      // La racha final de la sesión (cómo terminó)
      $finalStreak = $currentStreak;

      // Estadísticas
      $totalAnswers = count($answers);
      $correctAnswers = count(array_filter($answers, fn($a) => (bool)$a['is_correct']));

      Response::json([
        'ok' => true,
        'session_id' => $sessionId,
        'player_id' => (int)$session['player_id'],
        'player_name' => $session['player_name'],
        'streaks' => [
          'max' => $maxStreak,
          'final' => $finalStreak,
          'all_streaks' => $allStreaks,
          'streak_count' => count($allStreaks)
        ],
        'stats' => [
          'total_answers' => $totalAnswers,
          'correct_answers' => $correctAnswers,
          'accuracy' => $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100, 2) : 0
        ]
      ], 200);

    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to fetch session streaks: ' . $e->getMessage()], 500);
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
          ROW_NUMBER() OVER (ORDER BY MAX(gs.score) DESC) AS `rank`,
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
        ORDER BY `rank` ASC
        LIMIT 10
      ");
      $stmt->execute();
      $leaderboard = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      Response::json([
        'ok' => true,
        'leaderboard' => array_map(fn($row) => [
          'rank' => (int)$row['rank'],
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
