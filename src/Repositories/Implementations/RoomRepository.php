<?php
namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Models\GameRoom;
use Src\Repositories\Interfaces\RoomRepositoryInterface;

final class RoomRepository implements RoomRepositoryInterface {
  public function __construct(private Connection $db) {}

  /**
   * Genera un código de sala único de 6 caracteres.
   * Usa caracteres sin ambigüedad (sin I,O,0,1).
   */
  private function generateUniqueCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxAttempts = 10;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
      $code = '';
      for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
      }

      // Verificar que no exista
      $st = $this->db->pdo()->prepare("SELECT COUNT(*) FROM game_rooms WHERE room_code = :code");
      $st->execute([':code' => $code]);

      if ((int)$st->fetchColumn() === 0) {
        return $code;
      }
    }

    throw new \RuntimeException("No se pudo generar un código único después de $maxAttempts intentos");
  }

  public function create(
    string $name,
    int $adminId,
    ?string $description = null,
    ?array $filterCategories = null,
    ?array $filterDifficulties = null,
    int $maxPlayers = 50,
    string $language = 'es'
  ): GameRoom {
    $roomCode = $this->generateUniqueCode();

    // Validate language
    if (!in_array($language, ['es', 'en'])) {
      $language = 'es';
    }

    $st = $this->db->pdo()->prepare(
      "INSERT INTO game_rooms (room_code, name, description, admin_id, filter_categories, filter_difficulties, max_players, language)
       VALUES (:code, :name, :desc, :admin, :cats, :diffs, :max, :lang)"
    );

    $st->execute([
      ':code' => $roomCode,
      ':name' => $name,
      ':desc' => $description,
      ':admin' => $adminId,
      ':cats' => $filterCategories ? json_encode($filterCategories) : null,
      ':diffs' => $filterDifficulties ? json_encode($filterDifficulties) : null,
      ':max' => $maxPlayers,
      ':lang' => $language
    ]);

    $id = (int)$this->db->pdo()->lastInsertId();

    return new GameRoom(
      $id,
      $roomCode,
      $name,
      $description,
      $adminId,
      $filterCategories,
      $filterDifficulties,
      $maxPlayers,
      $language,
      'active'
    );
  }

  public function get(int $id): ?GameRoom {
    $st = $this->db->pdo()->prepare(
      "SELECT id, room_code, name, description, admin_id, filter_categories,
              filter_difficulties, max_players, language, status, started_at, ended_at,
              created_at, updated_at
       FROM game_rooms WHERE id = :id"
    );
    $st->execute([':id' => $id]);
    $r = $st->fetch();

    return $r ? $this->mapToModel($r) : null;
  }

  public function getByCode(string $roomCode): ?GameRoom {
    $st = $this->db->pdo()->prepare(
      "SELECT id, room_code, name, description, admin_id, filter_categories,
              filter_difficulties, max_players, language, status, started_at, ended_at,
              created_at, updated_at
       FROM game_rooms WHERE room_code = :code"
    );
    $st->execute([':code' => strtoupper($roomCode)]);
    $r = $st->fetch();

    return $r ? $this->mapToModel($r) : null;
  }

  public function getAll(): array {
    $st = $this->db->pdo()->query(
      "SELECT id, room_code, name, description, admin_id, filter_categories,
              filter_difficulties, max_players, language, status, started_at, ended_at,
              created_at, updated_at
       FROM game_rooms ORDER BY created_at DESC"
    );

    $rooms = [];
    while ($r = $st->fetch()) {
      $rooms[] = $this->mapToModel($r);
    }
    return $rooms;
  }

  public function getAllByAdmin(int $adminId): array {
    $st = $this->db->pdo()->prepare(
      "SELECT id, room_code, name, description, admin_id, filter_categories,
              filter_difficulties, max_players, language, status, started_at, ended_at,
              created_at, updated_at
       FROM game_rooms WHERE admin_id = :admin ORDER BY created_at DESC"
    );
    $st->execute([':admin' => $adminId]);

    $rooms = [];
    while ($r = $st->fetch()) {
      $rooms[] = $this->mapToModel($r);
    }
    return $rooms;
  }

  public function update(int $id, array $data): bool {
    $allowedFields = ['name', 'description', 'filter_categories', 'filter_difficulties', 'max_players', 'language'];
    $updates = [];
    $params = [':id' => $id];

    foreach ($data as $field => $value) {
      if (in_array($field, $allowedFields)) {
        $dbField = $this->camelToSnake($field);

        // Convertir arrays a JSON
        if (in_array($field, ['filter_categories', 'filter_difficulties', 'filterCategories', 'filterDifficulties'])) {
          $dbField = str_contains($field, 'Categories') ? 'filter_categories' : 'filter_difficulties';
          $value = $value ? json_encode($value) : null;
        }

        // Validate language
        if ($field === 'language' && !in_array($value, ['es', 'en'])) {
          $value = 'es';
        }

        $updates[] = "$dbField = :$field";
        $params[":$field"] = $value;
      }
    }

    if (empty($updates)) {
      return false;
    }

    $sql = "UPDATE game_rooms SET " . implode(', ', $updates) . " WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute($params);
  }

  public function updateStatus(int $id, string $status): bool {
    $validStatuses = ['active', 'paused', 'closed'];
    if (!in_array($status, $validStatuses)) {
      throw new \InvalidArgumentException("Estado inválido: $status");
    }

    $sql = "UPDATE game_rooms SET status = :status";

    if ($status === 'active') {
      $sql .= ", started_at = COALESCE(started_at, NOW())";
    }

    if ($status === 'closed') {
      $sql .= ", ended_at = NOW()";
    }

    $sql .= " WHERE id = :id";

    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':status' => $status,
      ':id' => $id
    ]);
  }

  public function delete(int $id): bool {
    // Las sesiones asociadas tendrán room_id = NULL por ON DELETE SET NULL
    $st = $this->db->pdo()->prepare("DELETE FROM game_rooms WHERE id = :id");
    return $st->execute([':id' => $id]);
  }

  public function getActivePlayers(int $roomId): array {
    $st = $this->db->pdo()->prepare(
      "SELECT DISTINCT p.id, p.name, p.age, gs.score, gs.status as session_status, gs.started_at
       FROM players p
       JOIN game_sessions gs ON gs.player_id = p.id
       WHERE gs.room_id = :room AND gs.status = 'active'
       ORDER BY gs.started_at DESC"
    );
    $st->execute([':room' => $roomId]);
    return $st->fetchAll();
  }

  public function countActivePlayers(int $roomId): int {
    $st = $this->db->pdo()->prepare(
      "SELECT COUNT(DISTINCT player_id)
       FROM game_sessions
       WHERE room_id = :room AND status = 'active'"
    );
    $st->execute([':room' => $roomId]);
    return (int)$st->fetchColumn();
  }

  public function validateRoomCode(string $roomCode): bool {
    $st = $this->db->pdo()->prepare(
      "SELECT COUNT(*) FROM game_rooms WHERE room_code = :code AND status = 'active'"
    );
    $st->execute([':code' => strtoupper($roomCode)]);
    return (int)$st->fetchColumn() > 0;
  }

  /**
   * Obtiene estadísticas de la sala desde la vista v_room_statistics.
   */
  public function getRoomStatistics(int $roomId): ?array {
    $st = $this->db->pdo()->prepare(
      "SELECT * FROM v_room_statistics WHERE room_id = :id"
    );
    $st->execute([':id' => $roomId]);
    return $st->fetch() ?: null;
  }

  /**
   * Obtiene estadísticas de jugadores en la sala.
   */
  public function getRoomPlayerStats(int $roomId): array {
    $st = $this->db->pdo()->prepare(
      "SELECT * FROM v_room_player_stats WHERE room_id = :id ORDER BY high_score DESC"
    );
    $st->execute([':id' => $roomId]);
    return $st->fetchAll();
  }

  /**
   * Obtiene estadísticas de preguntas en la sala.
   * Solo devuelve preguntas que han sido respondidas incorrectamente (error_rate > 0)
   */
  public function getRoomQuestionStats(int $roomId): array {
    $st = $this->db->pdo()->prepare(
      "SELECT * FROM v_room_question_stats
       WHERE room_id = :id AND error_rate > 0
       ORDER BY error_rate DESC"
    );
    $st->execute([':id' => $roomId]);
    return $st->fetchAll();
  }

  /**
   * Obtiene estadísticas por categoría en la sala.
   */
  public function getRoomCategoryStats(int $roomId): array {
    $st = $this->db->pdo()->prepare(
      "SELECT * FROM v_room_category_stats WHERE room_id = :id"
    );
    $st->execute([':id' => $roomId]);
    return $st->fetchAll();
  }

  /**
   * Obtiene las 5 preguntas más difíciles de la sala (menor tasa de éxito).
   * Usa la misma lógica que el dashboard: calcula success_rate directamente desde player_answers.
   * No tiene filtro mínimo de respuestas para incluir todas las preguntas contestadas.
   */
  public function getTopHardestQuestions(int $roomId, int $limit = 5): array {
    $st = $this->db->pdo()->prepare(
      "SELECT
         q.id,
         q.statement,
         COUNT(pa.id) AS times_answered,
         ROUND((SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(pa.id)) * 100, 2) AS success_rate
       FROM questions q
       INNER JOIN player_answers pa ON pa.question_id = q.id
       INNER JOIN game_sessions gs ON gs.id = pa.session_id
       WHERE gs.room_id = :id
       GROUP BY q.id, q.statement
       ORDER BY success_rate ASC, times_answered DESC
       LIMIT :limit"
    );
    $st->bindValue(':id', $roomId, \PDO::PARAM_INT);
    $st->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  /**
   * Obtiene las 5 preguntas más fáciles de la sala (mayor tasa de éxito).
   * Usa la misma lógica que el dashboard: calcula success_rate directamente desde player_answers.
   * No tiene filtro mínimo de respuestas para incluir todas las preguntas contestadas.
   */
  public function getTopEasiestQuestions(int $roomId, int $limit = 5): array {
    $st = $this->db->pdo()->prepare(
      "SELECT
         q.id,
         q.statement,
         COUNT(pa.id) AS times_answered,
         ROUND((SUM(CASE WHEN pa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(pa.id)) * 100, 2) AS success_rate
       FROM questions q
       INNER JOIN player_answers pa ON pa.question_id = q.id
       INNER JOIN game_sessions gs ON gs.id = pa.session_id
       WHERE gs.room_id = :id
       GROUP BY q.id, q.statement
       ORDER BY success_rate DESC, times_answered DESC
       LIMIT :limit"
    );
    $st->bindValue(':id', $roomId, \PDO::PARAM_INT);
    $st->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  private function mapToModel(array $r): GameRoom {
    return new GameRoom(
      (int)$r['id'],
      $r['room_code'],
      $r['name'],
      $r['description'],
      (int)$r['admin_id'],
      $r['filter_categories'] ? json_decode($r['filter_categories'], true) : null,
      $r['filter_difficulties'] ? json_decode($r['filter_difficulties'], true) : null,
      (int)$r['max_players'],
      $r['language'] ?? 'es',
      $r['status'],
      $r['started_at'],
      $r['ended_at'],
      $r['created_at'],
      $r['updated_at']
    );
  }

  private function camelToSnake(string $input): string {
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
  }
}
