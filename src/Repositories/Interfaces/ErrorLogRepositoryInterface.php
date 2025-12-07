<?php

namespace Src\Repositories\Interfaces;

use Src\DTOs\ErrorLogDTO;

interface ErrorLogRepositoryInterface
{
  /**
   * Guarda un registro de error en la base de datos
   *
   * @param ErrorLogDTO $dto Datos del error a guardar
   * @return bool true si se guardó exitosamente, false en caso de error
   */
  public function save(ErrorLogDTO $dto): bool;
}
