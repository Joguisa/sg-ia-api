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

  public function startSession(int $playerId, float $startDifficulty = 1.0): array {
    if ($startDifficulty < 1.0 || $startDifficulty > 5.0) {
      throw new \RangeError("Dificultad inicial debe estar entre 1.0 y 5.0");
    }

    $player = $this->players->find($playerId);
    if (!$player) throw new \RuntimeException('Jugador no existe');

    $gs = $this->sessions->start($playerId, $startDifficulty);
    return [
      'session_id' => $gs->id,
      'current_difficulty' => $gs->currentDifficulty,
      'status' => $gs->status
    ];
  }

  public function nextQuestion(int $categoryId, int $difficulty): ?array {
    if ($difficulty < 1 || $difficulty > 5) {
      throw new \RangeError("Dificultad debe estar entre 1 y 5");
    }

    $q = $this->questions->getActiveByDifficulty($categoryId, $difficulty);
    return $q ? [
      'id' => $q->id,
      'statement' => $q->statement,
      'difficulty' => $q->difficulty
    ] : null;
  }

  /**
   * REFACTORIZADO:
   * - Obtiene dificultad ACTUAL de BD, no del cliente
   * - Actualiza current_difficulty en BD
   * - Valida que sesión exista
   */
  public function submitAnswer(
    int $sessionId,
    int $questionId,
    ?int $optionId,
    bool $isCorrect,
    float $timeSec
  ): array {
    // Obtener sesión y dificultad ACTUAL de BD
    $session = $this->sessions->get($sessionId);
    if (!$session) throw new \RuntimeException('Sesión no existe');

    $currentDiff = $session->currentDifficulty;

    // Registrar respuesta con dificultad actual
    $this->answers->register($sessionId, $questionId, $optionId, $isCorrect, $timeSec, $currentDiff);

    // Calcular cambios
    $delta = $this->ai->scoreDelta($isCorrect, $timeSec);
    $nextDiff = $this->ai->nextDifficulty($currentDiff, $isCorrect, $timeSec);

    // Actualizar sesión
    $score = $session->score + $delta;
    $lives = $isCorrect ? $session->lives : max(0, $session->lives - 1);
    $status = $lives === 0 ? 'game_over' : 'active';

    $this->sessions->updateProgress($sessionId, $score, $lives, $status, $nextDiff);

    return [
      'score' => $score,
      'lives' => $lives,
      'status' => $status,
      'next_difficulty' => $nextDiff
    ];
  }
}
