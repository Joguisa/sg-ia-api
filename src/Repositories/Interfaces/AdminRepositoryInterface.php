<?php
declare(strict_types=1);

namespace Src\Repositories\Interfaces;

use Src\Models\Admin;

interface AdminRepositoryInterface {
  /**
   * Get all admins
   * @param bool $includeInactive Whether to include inactive admins
   * @return Admin[]
   */
  public function all(bool $includeInactive = false): array;

  /**
   * Find admin by ID
   * @param int $id
   * @return Admin|null
   */
  public function find(int $id): ?Admin;

  /**
   * Find admin by email
   * @param string $email
   * @return Admin|null
   */
  public function findByEmail(string $email): ?Admin;

  /**
   * Create new admin
   * @param string $email
   * @param string $passwordHash
   * @param string $role
   * @return Admin
   */
  public function create(string $email, string $passwordHash, string $role): Admin;

  /**
   * Update admin fields
   * @param int $id
   * @param array $data Associative array with fields to update
   * @return bool
   */
  public function update(int $id, array $data): bool;

  /**
   * Update admin active status
   * @param int $id
   * @param bool $isActive
   * @return bool
   */
  public function updateStatus(int $id, bool $isActive): bool;

  /**
   * Logical deletion - set is_active to 0
   * @param int $id
   * @return bool
   */
  public function delete(int $id): bool;
}
