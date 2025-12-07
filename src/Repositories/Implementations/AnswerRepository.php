<?php
namespace Src\Repositories\Implementations;
use Src\Database\Connection;
use Src\Repositories\Interfaces\AnswerRepositoryInterface;

final class AnswerRepository implements AnswerRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function register(int $sessionId,int $questionId,?int $optionId,bool $isCorrect,float $timeTaken,float $difficulty): void {
    // 🔍 LOGGING DIAGNÓSTICO - Punto 3: Registrar respuesta
    error_log("=== DIAGNOSTICO ANSWER REPOSITORY ===");
    error_log("Intentando registrar respuesta:");
    error_log("  SessionID: $sessionId");
    error_log("  QuestionID: $questionId");
    error_log("  OptionID: " . ($optionId ?? 'null'));
    error_log("  IsCorrect: " . ($isCorrect ? 'true' : 'false'));
    error_log("  TimeTaken: $timeTaken");
    error_log("  Difficulty: $difficulty");

    try {
      $sql="INSERT INTO player_answers(session_id,question_id,selected_option_id,is_correct,time_taken_seconds,difficulty_at_answer)
            VALUES(:s,:q,:o,:c,:t,:d)";
      $st=$this->db->pdo()->prepare($sql);
      $st->execute([
        ':s'=>$sessionId, ':q'=>$questionId, ':o'=>$optionId,
        ':c'=>$isCorrect?1:0, ':t'=>$timeTaken, ':d'=>$difficulty
      ]);
      error_log("Respuesta registrada exitosamente");
    } catch (\PDOException $e) {
      error_log("❌ ERROR CRÍTICO al registrar respuesta:");
      error_log("  Código de error: " . $e->getCode());
      error_log("  Mensaje: " . $e->getMessage());
      error_log("  SQL State: " . ($e->errorInfo[0] ?? 'N/A'));

      // Si es error de clave duplicada (código 23000)
      if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        error_log("  ⚠️ PREGUNTA DUPLICADA DETECTADA:");
        error_log("  La pregunta $questionId ya fue respondida en la sesión $sessionId");
        error_log("  Esto indica que el sistema de exclusión falló");
      }

      // Re-lanzar la excepción para que se maneje en la capa superior
      throw $e;
    }
  }
}
