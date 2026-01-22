<?php
namespace Src\Controllers;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

final class QuestionController {
  public function __construct(private QuestionRepositoryInterface $questions) {}

  public function find(array $params): void {
    $lang = LanguageDetector::detect();
    $q = $this->questions->find((int)$params['id']);
    if (!$q) Response::json(['ok'=>false,'error'=>Translations::get('question_not_found', $lang)],404);
    else Response::json(['ok'=>true,'question'=>[
      'id'=>$q->id,'statement'=>$q->statement,'difficulty'=>$q->difficulty,'category_id'=>$q->categoryId
    ]]);
  }

  /**
   * Listar preguntas con filtros
   *
   * Endpoint: GET /questions?status={active|inactive|all}
   *
   * @return void
   */
  public function list(): void {
    $lang = LanguageDetector::detect();
    $status = $_GET['status'] ?? 'active';
    
    try {
      $questions = $this->questions->findAllWithInactive($status);
      Response::json(['ok' => true, 'questions' => $questions], 200);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_fetch_questions', $lang) . ': ' . $e->getMessage()], 500);
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
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!isset($data['verified'])) {
      Response::json(['ok' => false, 'error' => Translations::get('missing_field_verified', $lang)], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok' => false, 'error' => Translations::get('question_not_found', $lang)], 404);
        return;
      }

      $success = $this->questions->updateVerification($questionId, (bool)$data['verified']);

      if ($success) {
        Response::json(['ok' => true, 'message' => Translations::get('question_verification_updated', $lang)], 200);
      } else {
        Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_verification', $lang)], 500);
      }
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('error_updating_verification', $lang) . ': ' . $e->getMessage()], 500);
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
    $lang = LanguageDetector::detect();
    $questionId = (int)($params['id'] ?? 0);

    if ($questionId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_question_id', $lang)], 400);
      return;
    }

    try {
      $question = $this->questions->find($questionId);

      if (!$question) {
        Response::json(['ok' => false, 'error' => Translations::get('question_not_found', $lang)], 404);
        return;
      }

      $success = $this->questions->delete($questionId);

      if ($success) {
        Response::json(['ok' => true, 'message' => Translations::get('question_deleted', $lang)], 200);
      } else {
        Response::json(['ok' => false, 'error' => Translations::get('failed_to_delete_question', $lang)], 500);
      }
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('error_deleting_question', $lang) . ': ' . $e->getMessage()], 500);
    }
  }
}
