<?php
namespace Src\Services;

use Src\Repositories\Interfaces\{SessionRepositoryInterface,QuestionRepositoryInterface,AnswerRepositoryInterface};
use Src\Repositories\Interfaces\PlayerRepositoryInterface;

final class GameService {
  public function __construct(
    private SessionRepositoryInterface $sessions,
    private QuestionRepositoryInterface $questions,
    private AnswerRepositoryInterface $answers,
    private PlayerRepositoryInterface $players,
    private AIEngine $ai
  ) {}

  public function startSession(int $playerId, float $startDifficulty=1.0): array {
    $player = $this->players->find($playerId);
    if (!$player) throw new \RuntimeException('Jugador no existe');
    $gs = $this->sessions->start($playerId, $startDifficulty);
    return ['session_id'=>$gs->id,'current_difficulty'=>$gs->currentDifficulty,'status'=>$gs->status];
  }

  public function nextQuestion(int $categoryId, int $difficulty): ?array {
    $q = $this->questions->getActiveByDifficulty($categoryId, $difficulty);
    return $q ? ['id'=>$q->id,'statement'=>$q->statement,'difficulty'=>$q->difficulty] : null;
  }

  public function submitAnswer(int $sessionId, int $questionId, ?int $optionId, bool $isCorrect, float $timeSec, float $currentDiff): array {
    $this->answers->register($sessionId,$questionId,$optionId,$isCorrect,$timeSec,$currentDiff);
    $delta = $this->ai->scoreDelta($isCorrect,$timeSec);
    $nextDiff = $this->ai->nextDifficulty($currentDiff,$isCorrect,$timeSec);

    $session = $this->sessions->get($sessionId);
    if (!$session) throw new \RuntimeException('Sesión no existe');

    $score = $session->score + $delta;
    $lives = $isCorrect ? $session->lives : max(0, $session->lives - 1);
    $status = $lives === 0 ? 'game_over' : 'active';
    $this->sessions->updateProgress($sessionId,$score,$lives,$status);

    return ['score'=>$score,'lives'=>$lives,'status'=>$status,'next_difficulty'=>$nextDiff];
  }
}
