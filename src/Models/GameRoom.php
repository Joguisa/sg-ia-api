<?php
namespace Src\Models;

final class GameRoom {
  public function __construct(
    public int $id,
    public string $roomCode,
    public string $name,
    public ?string $description,
    public int $adminId,
    public ?array $filterCategories,
    public ?array $filterDifficulties,
    public int $maxPlayers = 50,
    public string $language = 'es',
    public string $status = 'active',
    public ?string $startedAt = null,
    public ?string $endedAt = null,
    public ?string $createdAt = null,
    public ?string $updatedAt = null
  ) {}
}
