<?php
namespace Src\Controllers;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;
use Src\Utils\Response;

final class QuestionController {
  public function __construct(private QuestionRepositoryInterface $questions) {}

  public function find(array $params): void {
    $q = $this->questions->find((int)$params['id']);
    if (!$q) Response::json(['ok'=>false,'error'=>'Pregunta no existe'],404);
    else Response::json(['ok'=>true,'question'=>[
      'id'=>$q->id,'statement'=>$q->statement,'difficulty'=>$q->difficulty,'category_id'=>$q->categoryId
    ]]);
  }

  /**
   * Listar todas las preguntas activas
   *
   * Endpoint: GET /questions
   *
   * @return void
   */
  public function list(): void {
    try {
      $questions = $this->questions->findAll();
      Response::json(['ok' => true, 'questions' => $questions], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Failed to fetch questions: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Actualizar estado de verificación de una pregunta
   *
   * Endpoint: PATCH /questions/{id}/verify
   * Body: { "verified": true|false }
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function verify(array $params): void {
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok' => false, 'error' => 'Invalid question ID'], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!isset($data['verified'])) {
      Response::json(['ok' => false, 'error' => 'Missing required field: verified'], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok' => false, 'error' => 'Question not found'], 404);
        return;
      }

      $success = $this->questions->updateVerification($questionId, (bool)$data['verified']);

      if ($success) {
        Response::json(['ok' => true, 'message' => 'Question verification updated'], 200);
      } else {
        Response::json(['ok' => false, 'error' => 'Failed to update verification'], 500);
      }
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error updating verification: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Eliminar una pregunta
   *
   * Endpoint: DELETE /questions/{id}
   *
   * @param array $params Parámetros de la ruta (contiene 'id')
   * @return void
   */
  public function delete(array $params): void {
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok' => false, 'error' => 'Invalid question ID'], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok' => false, 'error' => 'Question not found'], 404);
        return;
      }

      $success = $this->questions->delete($questionId);

      if ($success) {
        Response::json(['ok' => true, 'message' => 'Question deleted successfully'], 200);
      } else {
        Response::json(['ok' => false, 'error' => 'Failed to delete question'], 500);
      }
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error deleting question: ' . $e->getMessage()], 500);
    }
  }
}
