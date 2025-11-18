<?php
namespace Src\Controllers;
use Src\Services\GameService;
use Src\Services\ValidationService;
use Src\Utils\Response;

final class GameController {
  public function __construct(private GameService $game) {}

  public function start(): void {
    try {
      $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
      ValidationService::requireFields($data, ['player_id']);

      $playerId = (int)$data['player_id'];
      $startDiff = isset($data['start_difficulty']) ? (float)$data['start_difficulty'] : 1.0;

      if ($playerId <= 0) {
        throw new \InvalidArgumentException('player_id debe ser > 0');
      }

      $out = $this->game->startSession($playerId, $startDiff);
      Response::json(['ok' => true] + $out, 201);
    } catch (\JsonException $e) {
      Response::json(['ok' => false, 'error' => 'JSON inválido'], 400);
    } catch (\InvalidArgumentException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RangeError $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  public function next(): void {
    try {
      $categoryId = (int)($_GET['category_id'] ?? 1);
      $difficulty = (int)($_GET['difficulty'] ?? 1);

      if ($categoryId <= 0 || $difficulty < 1 || $difficulty > 5) {
        throw new \InvalidArgumentException('Parámetros inválidos');
      }

      $q = $this->game->nextQuestion($categoryId, $difficulty);
      if (!$q) {
        Response::json(['ok' => false, 'error' => 'No hay preguntas'], 404);
      } else {
        Response::json(['ok' => true, 'question' => $q]);
      }
    } catch (\InvalidArgumentException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RangeError $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  public function answer(array $params): void {
    try {
      $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
      ValidationService::requireFields($data, ['question_id', 'is_correct', 'time_taken']);

      $sessionId = (int)$params['id'];
      if ($sessionId <= 0) {
        throw new \InvalidArgumentException('ID de sesión inválido');
      }

      $out = $this->game->submitAnswer(
        $sessionId,
        (int)$data['question_id'],
        isset($data['selected_option_id']) ? (int)$data['selected_option_id'] : null,
        (bool)$data['is_correct'],
        (float)$data['time_taken']
      );
      Response::json(['ok' => true] + $out);
    } catch (\JsonException $e) {
      Response::json(['ok' => false, 'error' => 'JSON inválido'], 400);
    } catch (\InvalidArgumentException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RangeError $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }
}
