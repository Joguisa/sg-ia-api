<?php

namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\DTOs\ErrorLogDTO;
use Src\Repositories\Interfaces\ErrorLogRepositoryInterface;

final class ErrorLogRepository implements ErrorLogRepositoryInterface
{
  public function __construct(private Connection $db) {}

  /**
   * Guarda un registro de error en la base de datos
   * Operación silenciosa: no lanza excepciones, solo retorna false si falla
   *
   * @param ErrorLogDTO $dto Datos del error a guardar
   * @return bool true si se guardó exitosamente, false en caso de error
   */
  public function save(ErrorLogDTO $dto): bool
  {
    try {
      $sql = "INSERT INTO error_logs (message, status, status_text, url, created_at)
              VALUES (:message, :status, :status_text, :url, NOW())";

      $st = $this->db->pdo()->prepare($sql);

      return $st->execute([
        ':message' => substr($dto->message, 0, 255), // Truncar a 255 caracteres
        ':status' => $dto->status,
        ':status_text' => $dto->statusText ? substr($dto->statusText, 0, 100) : null,
        ':url' => substr($dto->url, 0, 512) // Truncar a 512 caracteres
      ]);
    } catch (\PDOException $e) {
      // Operación silenciosa: log opcional pero no lanzar excepción
      error_log("ErrorLogRepository: Fallo al guardar error - " . $e->getMessage());
      return false;
    } catch (\Throwable $e) {
      error_log("ErrorLogRepository: Error inesperado - " . $e->getMessage());
      return false;
    }
  }
}
