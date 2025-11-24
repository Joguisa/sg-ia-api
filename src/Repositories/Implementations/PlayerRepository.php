<?php
namespace Src\Repositories\Implementations;
use PDO;
use Src\Database\Connection;
use Src\Models\Player;
use Src\Repositories\Interfaces\PlayerRepositoryInterface;

final class PlayerRepository implements PlayerRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function create(string $name, int $age): Player {
    $pdo = $this->db->pdo();
    $stmt = $pdo->prepare("INSERT INTO players(name, age) VALUES(:n, :a)");
    $stmt->execute([':n' => $name, ':a' => $age]);
    
    return new Player((int)$pdo->lastInsertId(), $name, $age);
  }

  public function all(): array {
    $stmt = $this->db->pdo()->query("SELECT id, name, age, created_at FROM players ORDER BY id DESC");
    return array_map(fn($r) => new Player(
        (int)$r['id'], 
        $r['name'], 
        (int)$r['age'], 
        $r['created_at']
    ), $stmt->fetchAll());
  }

  public function find(int $id): ?Player {
    $stmt = $this->db->pdo()->prepare("SELECT id, name, age, created_at FROM players WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    
    return $r ? new Player(
        (int)$r['id'], 
        $r['name'], 
        (int)$r['age'], 
        $r['created_at']
    ) : null;
  }

}
