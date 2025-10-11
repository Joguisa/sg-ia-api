<?php
namespace Src\Database;

use PDO;
use PDOException;

final class Connection {
  private PDO $pdo;

  public function __construct(array $cfg) {
    try {
      $this->pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], $cfg['opts'] ?? []);
      $this->pdo->exec("SET NAMES utf8mb4");
    } catch (PDOException $e) {
      throw new \RuntimeException('DB connection failed: '.$e->getMessage());
    }
  }

  public function pdo(): PDO {
    return $this->pdo;
  }
}
