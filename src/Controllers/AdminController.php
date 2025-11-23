<?php
namespace Src\Controllers;

use Src\Services\ValidationService;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;
use Src\Utils\Response;

final class AdminController {
  public function __construct(
    private QuestionRepositoryInterface $questions
  ) {}

  /**
   * Actualizar el texto/enunciado de una pregunta
   *
   * Endpoint: PUT /admin/questions/{id}
   * Body: { "statement": "Nuevo enunciado de la pregunta" }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function updateQuestion(array $params): void {
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid question ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['statement']);

    $statement = trim($data['statement']);

    if (empty($statement)) {
      Response::json(['ok'=>false,'error'=>'Question statement cannot be empty'], 400);
      return;
    }

    if (strlen($statement) < 10) {
      Response::json(['ok'=>false,'error'=>'Question statement must be at least 10 characters'], 400);
      return;
    }

    if (strlen($statement) > 1000) {
      Response::json(['ok'=>false,'error'=>'Question statement cannot exceed 1000 characters'], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok'=>false,'error'=>'Question not found'], 404);
        return;
      }

      // Actualizar statement manteniendo el estado de verificación actual
      $this->questions->update($questionId, $statement, $question->adminVerified);

      Response::json([
        'ok' => true,
        'message' => 'Question updated successfully',
        'question' => [
          'id' => $question->id,
          'statement' => $statement,
          'difficulty' => $question->difficulty,
          'category_id' => $question->categoryId,
          'is_ai_generated' => $question->isAiGenerated,
          'admin_verified' => $question->adminVerified
        ]
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to update question: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Marcar una pregunta como verificada por el administrador
   *
   * Endpoint: PATCH /admin/questions/{id}/verify
   * Body: { "verified": true|false }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function verifyQuestion(array $params): void {
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok'=>false,'error'=>'Invalid question ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['verified']);

    $verified = (bool)$data['verified'];

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok'=>false,'error'=>'Question not found'], 404);
        return;
      }

      // Actualizar estado de verificación manteniendo el statement actual
      $this->questions->update($questionId, $question->statement, $verified);

      Response::json([
        'ok' => true,
        'message' => 'Question verification status updated',
        'question' => [
          'id' => $question->id,
          'statement' => $question->statement,
          'admin_verified' => $verified
        ]
      ], 200);
    } catch (\Exception $e) {
      Response::json(['ok'=>false,'error'=>'Failed to verify question: ' . $e->getMessage()], 500);
    }
  }
}
