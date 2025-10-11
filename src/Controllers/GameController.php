<?php
namespace Src\Controllers;
use Src\Services\GameService;
use Src\Services\ValidationService;
use Src\Utils\Response;

final class GameController {
  public function __construct(private GameService $game) {}

  public function start(): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data, ['player_id']);
    $out = $this->game->startSession((int)$data['player_id'], (float)($data['start_difficulty'] ?? 1.0));
    Response::json(['ok'=>true]+$out,201);
  }

  public function next(): void {
    // default categoría 1 (Cáncer de Colon). Ajusta cuando parametrices.
    $cat = (int)($_GET['category_id'] ?? 1);
    $diff = (int)($_GET['difficulty'] ?? 1);
    $q = $this->game->nextQuestion($cat,$diff);
    if (!$q) Response::json(['ok'=>false,'error'=>'No hay preguntas'],404);
    else Response::json(['ok'=>true,'question'=>$q]);
  }

  public function answer(array $params): void {
    $sessionId = (int)$params['id'];
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    ValidationService::requireFields($data,['question_id','is_correct','time_taken','current_difficulty']);
    $out = $this->game->submitAnswer(
      $sessionId,
      (int)$data['question_id'],
      isset($data['selected_option_id']) ? (int)$data['selected_option_id'] : null,
      (bool)$data['is_correct'],
      (float)$data['time_taken'],
      (float)$data['current_difficulty']
    );
    Response::json(['ok'=>true]+$out);
  }
}
