<?php

namespace Src\Controllers;

use Src\DTOs\ErrorLogDTO;
use Src\Repositories\Interfaces\ErrorLogRepositoryInterface;
use Src\Utils\Response;

/**
 * Controlador para gestionar logs de errores del frontend
 */
final class LogController
{
  public function __construct(private ErrorLogRepositoryInterface $errorLogRepo) {}

  /**
   * Endpoint: POST /api/logs/error
   * Recibe errores del frontend y los persiste en la base de datos
   *
   * Request Body:
   * {
   *   "message": "Error description",
   *   "status": 404,
   *   "status_text": "Not Found",
   *   "url": "https://example.com/api/resource"
   * }
   *
   * Response:
   * { "ok": true } (siempre retorna 200, incluso si falla internamente)
   *
   * @return void
   */
  public function logError(): void
  {
    try {
      // Leer el cuerpo del request
      $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];

      // Crear DTO (valida campos requeridos)
      $dto = ErrorLogDTO::fromArray($data);

      // Guardar en BD (operación silenciosa)
      $this->errorLogRepo->save($dto);

      // Siempre retornar éxito al frontend para no romper su flujo
      Response::json(['ok' => true], 200);

    } catch (\JsonException $e) {
      // JSON inválido - retornar éxito para no romper el frontend
      error_log("LogController: JSON inválido - " . $e->getMessage());
      Response::json(['ok' => true], 200);

    } catch (\InvalidArgumentException $e) {
      // Faltan campos requeridos - retornar éxito para no romper el frontend
      error_log("LogController: Campos faltantes - " . $e->getMessage());
      Response::json(['ok' => true], 200);

    } catch (\Throwable $e) {
      // Error inesperado - retornar éxito para no romper el frontend
      error_log("LogController: Error inesperado - " . $e->getMessage());
      Response::json(['ok' => true], 200);
    }
  }
}
