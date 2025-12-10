<?php

namespace Src\Repositories\Interfaces;

/**
 * QuestionBatchRepositoryInterface
 *
 * Define el contrato para operaciones CRUD con lotes de preguntas
 */
interface QuestionBatchRepositoryInterface
{
  /**
   * Crea un nuevo batch de preguntas
   *
   * @param array $batchData Datos del batch: batch_name, batch_type, description, total_questions
   * @return int ID del batch creado
   */
  public function create(array $batchData): int;

  /**
   * Obtiene un batch por su ID
   *
   * @param int $batchId ID del batch
   * @return array|null Array asociativo con datos del batch o null si no existe
   */
  public function getById(int $batchId): ?array;

  /**
   * Obtiene todos los batches ordenados por fecha de importación
   *
   * @return array Array de batches
   */
  public function getAll(): array;

  /**
   * Actualiza el contador de preguntas verificadas en un batch
   *
   * @param int $batchId ID del batch
   * @return bool true si la actualización fue exitosa
   */
  public function updateVerificationCount(int $batchId): bool;

  /**
   * Actualiza el estado de un batch
   *
   * @param int $batchId ID del batch
   * @param string $status Estado: 'pending', 'partial', 'complete'
   * @return bool true si la actualización fue exitosa
   */
  public function updateStatus(int $batchId, string $status): bool;
}
