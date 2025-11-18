<?php
declare(strict_types=1);

use Src\Database\Connection;
use Src\Utils\Router;
use Src\Utils\Response;
use Src\Middleware\CorsMiddleware;

use Src\Repositories\Implementations\{PlayerRepository,QuestionRepository,SessionRepository,AnswerRepository};
use Src\Repositories\Interfaces\{PlayerRepositoryInterface,QuestionRepositoryInterface,SessionRepositoryInterface,AnswerRepositoryInterface};

use Src\Controllers\{PlayerController,GameController,QuestionController,StatisticsController};
use Src\Services\{GameService,AIEngine};

require_once __DIR__ . '/../vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config/config.php';
$dbCfg = require __DIR__ . '/../config/database.php';

// CORS
CorsMiddleware::handle($config['cors']);

// DI básico
$conn  = new Connection($dbCfg);
$playersRepo   = new PlayerRepository($conn);
$questionsRepo = new QuestionRepository($conn);
$sessionsRepo  = new SessionRepository($conn);
$answersRepo   = new AnswerRepository($conn);
$ai            = new AIEngine();

$gameService = new GameService($sessionsRepo,$questionsRepo,$answersRepo,$playersRepo,$ai);

$playerCtrl = new PlayerController($playersRepo);
$gameCtrl   = new GameController($gameService);
$questionCtrl = new QuestionController($questionsRepo);
$statsCtrl  = new StatisticsController($conn);

// Router
$router = new Router();

// Health check
$router->add('GET','/', fn()=> \Src\Utils\Response::json([
  'ok'=>true,'service'=>'sg-ia-api','time'=>date('c')
]));

// Players
$router->add('POST','/players', fn()=> $playerCtrl->create());
$router->add('GET','/players', fn()=> $playerCtrl->index());

// Game
$router->add('POST','/games/start', fn()=> $gameCtrl->start());
$router->add('GET','/games/next', fn()=> $gameCtrl->next());
$router->add('POST','/games/{id}/answer', fn($p)=> $gameCtrl->answer($p));

// Questions
$router->add('GET','/questions/{id}', fn($p)=> $questionCtrl->find($p));

// Stats
$router->add('GET','/stats/session/{id}', fn($p)=> $statsCtrl->session($p));

// Dispatch
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $router->dispatch($method, $path);
} catch (Throwable $e) {
  $status = $config['app']['debug'] ? 500 : 400;
  Response::json(['ok'=>false,'error'=>$e->getMessage()], $status);
}
