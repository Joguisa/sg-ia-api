<?php
namespace Src\Repositories\Interfaces;

use Src\Models\GameRoom;

interface RoomRepositoryInterface {
  public function create(
    string $name,
    int $adminId,
    ?string $description = null,
    ?array $filterCategories = null,
    ?array $filterDifficulties = null,
    int $maxPlayers = 50
  ): GameRoom;

  public function get(int $id): ?GameRoom;
  public function getByCode(string $roomCode): ?GameRoom;
  public function getAll(): array;
  public function getAllByAdmin(int $adminId): array;
  public function update(int $id, array $data): bool;
  public function updateStatus(int $id, string $status): bool;
  public function delete(int $id): bool;
  public function getActivePlayers(int $roomId): array;
  public function countActivePlayers(int $roomId): int;
  public function validateRoomCode(string $roomCode): bool;
}
