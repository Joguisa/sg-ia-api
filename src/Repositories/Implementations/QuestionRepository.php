<?php
namespace Src\Repositories\Implementations;
use Src\Database\Connection;
use Src\Models\Question;
use Src\Repositories\Interfaces\QuestionRepositoryInterface;

final class QuestionRepository implements QuestionRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function getActiveByDifficulty(int $categoryId, int $difficulty): ?Question {
    $sql = "SELECT q.id,q.statement,q.difficulty,q.category_id
            FROM questions q
            WHERE q.is_active=1 AND q.category_id=:c AND q.difficulty=:d
            ORDER BY q.id DESC LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute([':c'=>$categoryId, ':d'=>$difficulty]);
    $r = $st->fetch();
    return $r ? new Question((int)$r['id'],$r['statement'],(int)$r['difficulty'],(int)$r['category_id']) : null;
  }

  public function find(int $id): ?Question {
    $st = $this->db->pdo()->prepare("SELECT id,statement,difficulty,category_id FROM questions WHERE id=:id");
    $st->execute([':id'=>$id]);
    $r = $st->fetch();
    return $r ? new Question((int)$r['id'],$r['statement'],(int)$r['difficulty'],(int)$r['category_id']) : null;
  }
}
