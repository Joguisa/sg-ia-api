<?php
namespace Src\Models;

final class Player {
  public function __construct(
    public int $id,
    public string $name,
    public ?string $createdAt = null
  ) {}
}
