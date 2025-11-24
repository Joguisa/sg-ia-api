<?php
declare(strict_types=1);

use Src\Database\Connection;
use Src\Utils\Router;
use Src\Utils\Response;
use Src\Middleware\CorsMiddleware;
use Src\Middleware\AuthMiddleware;

use Src\Repositories\Implementations\{PlayerRepository,QuestionRepository,SessionRepository,AnswerRepository};
use Src\Repositories\Interfaces\{PlayerRepositoryInterface,QuestionRepositoryInterface,SessionRepositoryInterface,AnswerRepositoryInterface};

use Src\Controllers\{PlayerController,GameController,QuestionController,StatisticsController,AdminController,AuthController};
use Src\Services\{GameService,AIEngine,AuthService};
use Src\Services\AI\GeminiAIService;

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

// Inicializar servicio Gemini si la API key está configurada
$generativeAi = null;
if (!empty($config['gemini']['api_key'] ?? null)) {
  $generativeAi = new GeminiAIService($config['gemini']['api_key']);
}

$gameService = new GameService($sessionsRepo,$questionsRepo,$answersRepo,$playersRepo,$ai,$generativeAi);

$playerCtrl = new PlayerController($playersRepo);
$gameCtrl   = new GameController($gameService);
$questionCtrl = new QuestionController($questionsRepo);
$statsCtrl  = new StatisticsController($conn);
$adminCtrl  = new AdminController($questionsRepo);
$authService = new AuthService($conn->pdo());
$authCtrl   = new AuthController($authService);
$authMiddleware = new AuthMiddleware($authService);

// Router
$router = new Router();

// Health check
$router->add('GET','/', fn()=> \Src\Utils\Response::json([
  'ok'=>true,'service'=>'sg-ia-api','time'=>date('c')
]));

// Auth (Public)
$router->add('POST','/auth/login', fn()=> $authCtrl->login());

// Players (Public)
$router->add('POST','/players', fn()=> $playerCtrl->create());
$router->add('GET','/players', fn()=> $playerCtrl->index());

// Game (Public)
$router->add('POST','/games/start', fn()=> $gameCtrl->start());
$router->add('GET','/games/next', fn()=> $gameCtrl->next());
$router->add('POST','/games/{id}/answer', fn($p)=> $gameCtrl->answer($p));

// Questions (Public)
$router->add('GET','/questions/{id}', fn($p)=> $questionCtrl->find($p));

// Stats (Public)
$router->add('GET','/stats/session/{id}', fn($p)=> $statsCtrl->session($p));

// Admin (Protected)
$router->add('PUT','/admin/questions/{id}', fn($p)=> $adminCtrl->updateQuestion($p), fn()=> $authMiddleware->validate());
$router->add('PATCH','/admin/questions/{id}/verify', fn($p)=> $adminCtrl->verifyQuestion($p), fn()=> $authMiddleware->validate());

// Dispatch
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $router->dispatch($method, $path);
} catch (Throwable $e) {
  $status = $config['app']['debug'] ? 500 : 400;
  Response::json(['ok'=>false,'error'=>$e->getMessage()], $status);
}
