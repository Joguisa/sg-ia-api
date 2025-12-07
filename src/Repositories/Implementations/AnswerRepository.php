<?php

namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Repositories\Interfaces\AnswerRepositoryInterface;

final class AnswerRepository implements AnswerRepositoryInterface
{
  public function __construct(private Connection $db) {}

  public function register(int $sessionId, int $questionId, ?int $optionId, bool $isCorrect, float $timeTaken, float $difficulty): void
  {
    try {
      $sql = "INSERT INTO player_answers(session_id,question_id,selected_option_id,is_correct,time_taken_seconds,difficulty_at_answer)
            VALUES(:s,:q,:o,:c,:t,:d)";
      $st = $this->db->pdo()->prepare($sql);
      $st->execute([
        ':s' => $sessionId,
        ':q' => $questionId,
        ':o' => $optionId,
        ':c' => $isCorrect ? 1 : 0,
        ':t' => $timeTaken,
        ':d' => $difficulty
      ]);
    } catch (\PDOException $e) {
      // Si es error de clave duplicada (código 23000)
      if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        error_log("  La pregunta $questionId ya fue respondida en la sesión $sessionId");
      }

      // Re-lanzar la excepción para que se maneje en la capa superior
      throw $e;
    }
  }
}
