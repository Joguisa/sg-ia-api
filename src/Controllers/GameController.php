<?php

namespace Src\Controllers;

use Src\Services\GameService;
use Src\Services\ValidationService;
use Src\Utils\Response;
use Src\Repositories\Interfaces\SessionRepositoryInterface;

final class GameController
{
  public function __construct(
    private GameService $game,
    private SessionRepositoryInterface $sessions
  ) {}

  /**
   * Inicia una nueva sesión de juego.
   *
   * Body: {
   *   "player_id": 1,
   *   "start_difficulty": 1.0,  // opcional
   *   "room_code": "ABC123"     // opcional - código de sala
   * }
   */
  public function start(): void
  {
    try {
      $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
      ValidationService::requireFields($data, ['player_id']);

      $playerId = (int)$data['player_id'];
      $startDiff = isset($data['start_difficulty']) ? (float)$data['start_difficulty'] : 1.0;
      $roomCode = isset($data['room_code']) && !empty($data['room_code']) ? trim($data['room_code']) : null;

      if ($playerId <= 0) {
        throw new \InvalidArgumentException('player_id debe ser > 0');
      }

      $out = $this->game->startSession($playerId, $startDiff, $roomCode);
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

  public function next(): void
  {
    try {
      // category_id es opcional: si es 0 o no existe, buscar en TODAS las categorías
      $categoryIdParam = $_GET['category_id'] ?? 0;
      $categoryId = $categoryIdParam > 0 ? (int)$categoryIdParam : null;

      $difficultyFloat = (float)($_GET['difficulty'] ?? 1.0);
      $sessionId = (int)($_GET['session_id'] ?? 0);

      // Redondear dificultad al entero más cercano para buscar preguntas
      $difficulty = (int)round($difficultyFloat);

      if ($difficulty < 1 || $difficulty > 5) {
        throw new \InvalidArgumentException('Parámetros inválidos');
      }

      if ($sessionId <= 0) {
        throw new \InvalidArgumentException('session_id es requerido');
      }

      $q = $this->game->nextQuestion($categoryId, $difficulty, $sessionId);

      if (!$q) {
        // Verificar el estado de la sesión para diferenciar entre 'completed' y 'no questions'
        $session = $this->sessions->get($sessionId);

        if ($session && $session->status === 'completed') {
          // Cuestionario completado exitosamente (alcanzó el límite de preguntas)
          Response::json([
            'ok' => true,
            'completed' => true,
            'message' => '¡Felicitaciones! Completaste el cuestionario'
          ], 200);
        } else {
          // Sin preguntas disponibles pero NO porque alcanzó el límite
          // Esto significa que completó todas las preguntas disponibles
          $catLog = $categoryId ? "category $categoryId" : "all categories";
          error_log("No questions available for session $sessionId, difficulty $difficulty, $catLog");

          // Marcar sesión como completada
          $this->sessions->updateProgress(
            $sessionId,
            $session->score,
            $session->lives,
            'completed',
            $session->currentDifficulty
          );

          $message = $categoryId
            ? '¡Felicitaciones! Completaste todas las preguntas disponibles de esta categoría'
            : '¡Felicitaciones! Completaste todas las preguntas disponibles';

          Response::json([
            'ok' => true,
            'completed' => true,
            'message' => $message
          ], 200);
        }
      } else {
        Response::json(['ok' => true, 'question' => $q]);
      }
    } catch (\InvalidArgumentException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RangeError $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  public function answer(array $params): void
  {
    try {
      $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
      ValidationService::requireFields($data, ['question_id', 'time_taken']);

      $sessionId = (int)$params['id'];
      if ($sessionId <= 0) {
        throw new \InvalidArgumentException('ID de sesión inválido');
      }

      $out = $this->game->submitAnswer(
        $sessionId,
        (int)$data['question_id'],
        isset($data['selected_option_id']) ? (int)$data['selected_option_id'] : null,
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
