<?php
namespace Src\Models;

final class Player {
  public function __construct(
    public int $id,
    public string $name,
    public int $age,
    public ?string $createdAt = null
  ) {}
}
