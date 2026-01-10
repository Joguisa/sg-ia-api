<?php
declare(strict_types=1);

namespace Src\Models;

/**
 * Admin Model (DTO)
 * Represents an administrator entity
 */
final class Admin {
  public function __construct(
    public int $id,
    public string $email,
    public string $role,
    public bool $isActive,
    public string $createdAt,
    public ?string $updatedAt = null
  ) {}

  /**
   * Convert to array for JSON serialization
   * Excludes sensitive data like password_hash
   */
  public function toArray(): array {
    return [
      'id' => $this->id,
      'email' => $this->email,
      'role' => $this->role,
      'is_active' => $this->isActive,
      'created_at' => $this->createdAt,
      'updated_at' => $this->updatedAt
    ];
  }
}
