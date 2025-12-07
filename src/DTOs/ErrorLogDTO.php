<?php

namespace Src\DTOs;

/**
 * DTO para crear un registro de error
 */
final class ErrorLogDTO
{
  public function __construct(
    public readonly string $message,
    public readonly int $status,
    public readonly ?string $statusText,
    public readonly string $url
  ) {}

  /**
   * Crea un DTO desde un array de datos del request
   *
   * @param array $data Array con las claves: message, status, status_text, url
   * @return self
   * @throws \InvalidArgumentException Si faltan campos requeridos
   */
  public static function fromArray(array $data): self
  {
    if (!isset($data['message']) || !isset($data['status']) || !isset($data['url'])) {
      throw new \InvalidArgumentException('Campos requeridos: message, status, url');
    }

    return new self(
      message: trim($data['message']),
      status: (int)$data['status'],
      statusText: isset($data['status_text']) ? trim($data['status_text']) : null,
      url: trim($data['url'])
    );
  }
}
