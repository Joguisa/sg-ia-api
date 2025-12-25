<?php
namespace Src\Repositories\Interfaces;
use Src\Models\Player;

interface PlayerRepositoryInterface {
  public function create(string $name, int $age): Player;
  public function all(): array;
  public function find(int $id): ?Player;
  public function findByNameAndAge(string $name, int $age): ?Player;
  public function getOrCreate(string $name, int $age): Player;
}
