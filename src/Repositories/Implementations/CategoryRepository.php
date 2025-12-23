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

  /**
   * Obtiene el ID de una categoría por su nombre
   *
   * @param string $categoryName Nombre de la categoría
   * @return int|null ID de la categoría o null si no existe
   */
  public function getIdByName(string $categoryName): ?int
  {
    $sql = "SELECT id FROM question_categories WHERE name = :name LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':name' => trim($categoryName)]);
    $result = $st->fetch();
    return $result ? (int)$result['id'] : null;
  }

  /**
   * Actualiza una categoría existente
   *
   * @param int $id ID de la categoría
   * @param array $data Array con estructura:
   *   - name: string (requerido)
   *   - description: string (opcional)
   * @return bool true si la actualización fue exitosa
   * @throws \InvalidArgumentException Si faltan campos requeridos o valores inválidos
   */
  public function update(int $id, array $data): bool
  {
    if (!isset($data['name'])) {
      throw new \InvalidArgumentException("Campo requerido faltante: name");
    }

    $name = trim((string)$data['name']);
    if (empty($name)) {
      throw new \InvalidArgumentException("El nombre de la categoría no puede estar vacío");
    }

    $description = isset($data['description']) ? trim((string)$data['description']) : null;

    $sql = "UPDATE question_categories SET name = :name, description = :description WHERE id = :id";
    $st = $this->db->pdo()->prepare($sql);
    return $st->execute([
      ':id' => $id,
      ':name' => $name,
      ':description' => $description
    ]);
  }

  /**
   * Elimina físicamente una categoría de la base de datos
   *
   * @param int $id ID de la categoría a eliminar
   * @return bool true si la eliminación fue exitosa
   * @throws \Exception Si hay preguntas asociadas a la categoría
   */
  public function delete(int $id): bool
  {
    try {
      // Verificar si hay preguntas asociadas a esta categoría
      $checkSql = "SELECT COUNT(*) as count FROM questions WHERE category_id = :id";
      $checkSt = $this->db->pdo()->prepare($checkSql);
      $checkSt->execute([':id' => $id]);
      $result = $checkSt->fetch();

      if ($result && (int)$result['count'] > 0) {
        throw new \Exception("No se puede eliminar la categoría porque tiene preguntas asociadas");
      }

      $sql = "DELETE FROM question_categories WHERE id = :id";
      $st = $this->db->pdo()->prepare($sql);
      return $st->execute([':id' => $id]);
    } catch (\Exception $e) {
      throw $e;
    }
  }
}
