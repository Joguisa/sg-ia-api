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
}
