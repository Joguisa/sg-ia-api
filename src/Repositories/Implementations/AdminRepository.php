<?php
declare(strict_types=1);

namespace Src\Repositories\Implementations;

use PDO;
use Src\Database\Connection;
use Src\Models\Admin;
use Src\Repositories\Interfaces\AdminRepositoryInterface;

final class AdminRepository implements AdminRepositoryInterface {
  public function __construct(private Connection $db) {}

  /**
   * Get all admins
   */
  public function all(bool $includeInactive = false): array {
    $sql = "SELECT id, email, role, is_active, created_at, updated_at FROM admins";
    
    if (!$includeInactive) {
      $sql .= " WHERE is_active = 1";
    }
    
    $sql .= " ORDER BY id DESC";
    
    $stmt = $this->db->pdo()->query($sql);
    
    return array_map(function($row) {
      return $this->mapRowToAdmin($row);
    }, $stmt->fetchAll());
  }

  /**
   * Find admin by ID
   */
  public function find(int $id): ?Admin {
    $stmt = $this->db->pdo()->prepare(
      "SELECT id, email, role, is_active, created_at, updated_at 
       FROM admins 
       WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row ? $this->mapRowToAdmin($row) : null;
  }

  /**
   * Find admin by email
   */
  public function findByEmail(string $email): ?Admin {
    $stmt = $this->db->pdo()->prepare(
      "SELECT id, email, role, is_active, created_at, updated_at 
       FROM admins 
       WHERE email = :email"
    );
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    return $row ? $this->mapRowToAdmin($row) : null;
  }

  /**
   * Create new admin
   */
  public function create(string $email, string $passwordHash, string $role): Admin {
    $pdo = $this->db->pdo();
    
    $stmt = $pdo->prepare(
      "INSERT INTO admins (email, password_hash, role, is_active) 
       VALUES (:email, :password_hash, :role, 1)"
    );
    
    $stmt->execute([
      ':email' => $email,
      ':password_hash' => $passwordHash,
      ':role' => $role
    ]);

    $id = (int)$pdo->lastInsertId();
    
    // Return the created admin
    return $this->find($id);
  }

  /**
   * Update admin fields
   */
  public function update(int $id, array $data): bool {
    $allowedFields = ['email', 'password_hash', 'role', 'is_active'];
    $updates = [];
    $params = [':id' => $id];

    foreach ($data as $field => $value) {
      if (in_array($field, $allowedFields)) {
        $updates[] = "$field = :$field";
        $params[":$field"] = $value;
      }
    }

    if (empty($updates)) {
      return false;
    }

    $sql = "UPDATE admins SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $this->db->pdo()->prepare($sql);
    
    return $stmt->execute($params);
  }

  /**
   * Update admin active status
   */
  public function updateStatus(int $id, bool $isActive): bool {
    $stmt = $this->db->pdo()->prepare(
      "UPDATE admins SET is_active = :is_active WHERE id = :id"
    );
    
    return $stmt->execute([
      ':id' => $id,
      ':is_active' => $isActive ? 1 : 0
    ]);
  }

  /**
   * Logical deletion - set is_active to 0
   */
  public function delete(int $id): bool {
    return $this->updateStatus($id, false);
  }

  /**
   * Map database row to Admin model
   */
  private function mapRowToAdmin(array $row): Admin {
    return new Admin(
      id: (int)$row['id'],
      email: $row['email'],
      role: $row['role'],
      isActive: (bool)$row['is_active'],
      createdAt: $row['created_at'],
      updatedAt: $row['updated_at']
    );
  }
}
