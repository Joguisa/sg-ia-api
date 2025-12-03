<?php
namespace Src\Repositories\Implementations;
use Src\Database\Connection;
use Src\Repositories\Interfaces\CategoryRepositoryInterface;

final class CategoryRepository implements CategoryRepositoryInterface {
  public function __construct(private Connection $db) {}

  /**
   * Obtiene todas las categorías
   *
   * @return array Array de categorías con id, name y description
   */
  public function findAll(): array {
    $sql = "SELECT id, name, description FROM question_categories ORDER BY name ASC";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute();
    return $st->fetchAll() ?: [];
  }

  /**
   * Obtiene una categoría por ID
   *
   * @param int $id ID de la categoría
   * @return array|null Array con id, name y description, o null si no existe
   */
  public function find(int $id): ?array {
    $sql = "SELECT id, name, description FROM question_categories WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':id' => $id]);
    $result = $st->fetch();
    return $result ?: null;
  }

  /**
   * Crea una nueva categoría en la base de datos
   *
   * @param array $data Array con estructura:
   *   - name: string (requerido)
   *   - description: string (opcional)
   * @return int ID de la categoría creada
   * @throws \InvalidArgumentException Si faltan campos requeridos o hay valores inválidos
   */
  public function create(array $data): int {
    if (!isset($data['name'])) {
      throw new \InvalidArgumentException("Campo requerido faltante: name");
    }

    $name = trim((string)$data['name']);
    if (empty($name)) {
      throw new \InvalidArgumentException("El nombre de la categoría no puede estar vacío");
    }

    $description = isset($data['description']) ? trim((string)$data['description']) : null;

    $sql = "INSERT INTO question_categories (name, description)
            VALUES (:name, :description)";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([
      ':name' => $name,
      ':description' => $description
    ]);

    return (int)$this->db->pdo()->lastInsertId();
  }
}
