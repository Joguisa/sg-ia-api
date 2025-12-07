<?php

namespace Src\Models;

/**
 * Modelo de ErrorLog
 * Representa un registro de error del frontend en la tabla error_logs
 */
final class ErrorLog
{
  public function __construct(
    public readonly int $id,
    public readonly string $message,
    public readonly int $status,
    public readonly ?string $statusText,
    public readonly string $url,
    public readonly string $createdAt
  ) {}
}
