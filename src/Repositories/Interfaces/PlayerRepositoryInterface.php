<?php
namespace Src\Repositories\Interfaces;
use Src\Models\Player;

interface PlayerRepositoryInterface {
  public function create(string $name): Player;
  public function all(): array;
  public function find(int $id): ?Player;
}
