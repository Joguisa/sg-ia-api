<?php
namespace Src\Repositories;
use Src\Database\Connection;
use Src\Repositories\Interfaces\AnswerRepositoryInterface;

final class AnswerRepository implements AnswerRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function register(int $sessionId,int $questionId,?int $optionId,bool $isCorrect,float $timeTaken,float $difficulty): void {
    $sql="INSERT INTO player_answers(session_id,question_id,selected_option_id,is_correct,time_taken_seconds,difficulty_at_answer)
          VALUES(:s,:q,:o,:c,:t,:d)";
    $st=$this->db->pdo()->prepare($sql);
    $st->execute([
      ':s'=>$sessionId, ':q'=>$questionId, ':o'=>$optionId,
      ':c'=>$isCorrect?1:0, ':t'=>$timeTaken, ':d'=>$difficulty
    ]);
  }
}
