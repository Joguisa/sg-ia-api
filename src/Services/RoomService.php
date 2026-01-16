<?php

namespace Src\Services;

use Src\Repositories\Interfaces\RoomRepositoryInterface;
use Src\Models\GameRoom;

final class RoomService
{
  public function __construct(
    private RoomRepositoryInterface $rooms
  ) {}

  /**
   * Crea una nueva sala de juego.
   */
  public function createRoom(
    string $name,
    int $adminId,
    ?string $description = null,
    ?array $filterCategories = null,
    ?array $filterDifficulties = null,
    int $maxPlayers = 50,
    string $language = 'es'
  ): array {
    // Validaciones
    if (empty(trim($name))) {
      throw new \InvalidArgumentException("El nombre de la sala es requerido");
    }

    if ($maxPlayers < 1 || $maxPlayers > 500) {
      throw new \RangeError("Máximo de jugadores debe estar entre 1 y 500");
    }

    // Validar dificultades si se proporcionan
    if ($filterDifficulties !== null) {
      foreach ($filterDifficulties as $diff) {
        if ($diff < 1 || $diff > 5) {
          throw new \RangeError("Los niveles de dificultad deben estar entre 1 y 5");
        }
      }
    }

    // Validar idioma
    if (!in_array($language, ['es', 'en'])) {
      $language = 'es';
    }

    $room = $this->rooms->create(
      $name,
      $adminId,
      $description,
      $filterCategories,
      $filterDifficulties,
      $maxPlayers,
      $language
    );

    return $this->formatRoomResponse($room);
  }

  /**
   * Obtiene una sala por ID.
   */
  public function getRoom(int $id): ?array {
    $room = $this->rooms->get($id);
    if (!$room) return null;

    $response = $this->formatRoomResponse($room);
    $response['active_players'] = $this->rooms->countActivePlayers($id);

    return $response;
  }

  /**
   * Obtiene una sala por código.
   */
  public function getRoomByCode(string $code): ?array {
    $room = $this->rooms->getByCode($code);
    if (!$room) return null;

    $response = $this->formatRoomResponse($room);
    $response['active_players'] = $this->rooms->countActivePlayers($room->id);

    return $response;
  }

  /**
   * Lista todas las salas.
   */
  public function getAllRooms(): array {
    $rooms = $this->rooms->getAll();
    return array_map(function($room) {
      $response = $this->formatRoomResponse($room);
      $response['active_players'] = $this->rooms->countActivePlayers($room->id);
      return $response;
    }, $rooms);
  }

  /**
   * Lista las salas de un administrador específico.
   */
  public function getRoomsByAdmin(int $adminId): array {
    $rooms = $this->rooms->getAllByAdmin($adminId);
    return array_map(function($room) {
      $response = $this->formatRoomResponse($room);
      $response['active_players'] = $this->rooms->countActivePlayers($room->id);
      return $response;
    }, $rooms);
  }

  /**
   * Actualiza una sala.
   */
  public function updateRoom(int $id, array $data): bool {
    $room = $this->rooms->get($id);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->update($id, $data);
  }

  /**
   * Cambia el estado de una sala.
   */
  public function updateRoomStatus(int $id, string $status): bool {
    $room = $this->rooms->get($id);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->updateStatus($id, $status);
  }

  /**
   * Elimina una sala.
   */
  public function deleteRoom(int $id): bool {
    $room = $this->rooms->get($id);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->delete($id);
  }

  /**
   * Valida si un código de sala es válido y la sala está activa.
   */
  public function validateRoomCode(string $code): array {
    $room = $this->rooms->getByCode($code);

    if (!$room) {
      return [
        'valid' => false,
        'reason' => 'Código de sala no encontrado'
      ];
    }

    if ($room->status !== 'active') {
      return [
        'valid' => false,
        'reason' => 'La sala no está activa'
      ];
    }

    $activePlayers = $this->rooms->countActivePlayers($room->id);
    if ($activePlayers >= $room->maxPlayers) {
      return [
        'valid' => false,
        'reason' => 'La sala está llena'
      ];
    }

    return [
      'valid' => true,
      'room' => $this->formatRoomResponse($room)
    ];
  }

  /**
   * Obtiene los jugadores activos en una sala.
   */
  public function getActivePlayers(int $roomId): array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->getActivePlayers($roomId);
  }

  /**
   * Obtiene las estadísticas completas de una sala.
   */
  public function getRoomStats(int $roomId): ?array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    $stats = $this->rooms->getRoomStatistics($roomId);
    if (!$stats) {
      return [
        'room' => $this->formatRoomResponse($room),
        'statistics' => null,
        'message' => 'No hay datos de estadísticas aún'
      ];
    }

    return [
      'room' => $this->formatRoomResponse($room),
      'statistics' => [
        'total_sessions' => (int)($stats['total_sessions'] ?? 0),
        'unique_players' => (int)($stats['unique_players'] ?? 0),
        'total_answers' => (int)($stats['total_answers'] ?? 0),
        'avg_accuracy' => (float)($stats['avg_accuracy'] ?? 0),
        'avg_time_sec' => (float)($stats['avg_time_sec'] ?? 0),
        'highest_score' => (int)($stats['highest_score'] ?? 0),
        'avg_score' => (float)($stats['avg_score'] ?? 0)
      ]
    ];
  }

  /**
   * Obtiene estadísticas de jugadores en la sala.
   */
  public function getRoomPlayerStats(int $roomId): array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->getRoomPlayerStats($roomId);
  }

  /**
   * Obtiene estadísticas de preguntas en la sala.
   */
  public function getRoomQuestionStats(int $roomId): array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->getRoomQuestionStats($roomId);
  }

  /**
   * Obtiene estadísticas por categoría en la sala.
   */
  public function getRoomCategoryStats(int $roomId): array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->getRoomCategoryStats($roomId);
  }

  /**
   * Obtiene las preguntas más difíciles de la sala.
   */
  public function getTopHardestQuestions(int $roomId, int $limit = 5): array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->getTopHardestQuestions($roomId, $limit);
  }

  /**
   * Obtiene las preguntas más fáciles de la sala.
   */
  public function getTopEasiestQuestions(int $roomId, int $limit = 5): array {
    $room = $this->rooms->get($roomId);
    if (!$room) {
      throw new \RuntimeException("Sala no encontrada");
    }

    return $this->rooms->getTopEasiestQuestions($roomId, $limit);
  }

  /**
   * Formatea la respuesta de una sala.
   */
  private function formatRoomResponse(GameRoom $room): array {
    return [
      'id' => $room->id,
      'room_code' => $room->roomCode,
      'name' => $room->name,
      'description' => $room->description,
      'admin_id' => $room->adminId,
      'filter_categories' => $room->filterCategories,
      'filter_difficulties' => $room->filterDifficulties,
      'max_players' => $room->maxPlayers,
      'language' => $room->language,
      'status' => $room->status,
      'started_at' => $room->startedAt,
      'ended_at' => $room->endedAt,
      'created_at' => $room->createdAt
    ];
  }
}
