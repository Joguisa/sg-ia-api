<?php
declare(strict_types=1);

use Src\Database\Connection;
use Src\Utils\Router;
use Src\Utils\Response;
use Src\Middleware\CorsMiddleware;
use Src\Middleware\AuthMiddleware;
use Dotenv\Dotenv;

use Src\Repositories\Implementations\{PlayerRepository,QuestionRepository,SessionRepository,AnswerRepository,SystemPromptRepository,CategoryRepository,ErrorLogRepository};
use Src\Controllers\{PlayerController,GameController,QuestionController,StatisticsController,AdminController,AuthController,CategoryController,LogController};
use Src\Services\{GameService,AIEngine,AuthService};
use Src\Services\AI\GeminiAIService;

require_once __DIR__ . '/../vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

$envPath = __DIR__ . '/../';
if (file_exists($envPath . '.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

$config = require __DIR__ . '/../config/config.php';
$dbCfg = require __DIR__ . '/../config/database.php';

// CORS
CorsMiddleware::handle($config['cors']);

// DI básico
$conn          = new Connection($dbCfg);
$playersRepo   = new PlayerRepository($conn);
$questionsRepo = new QuestionRepository($conn);
$sessionsRepo  = new SessionRepository($conn);
$answersRepo   = new AnswerRepository($conn);
$promptsRepo   = new SystemPromptRepository($conn);
$categoriesRepo = new CategoryRepository($conn);
$errorLogRepo  = new ErrorLogRepository($conn);
$ai            = new AIEngine();

$generativeAi = null;
if (!empty($config['gemini']['api_key'] ?? null)) {
  $generativeAi = new GeminiAIService($config['gemini']['api_key'], $promptsRepo);
}

$gameService = new GameService($sessionsRepo, $questionsRepo, $answersRepo, $playersRepo, $ai, $generativeAi);

$playerCtrl   = new PlayerController($playersRepo);
$gameCtrl     = new GameController($gameService);
$questionCtrl = new QuestionController($questionsRepo);
$statsCtrl    = new StatisticsController($conn);
$adminCtrl    = new AdminController($questionsRepo, $promptsRepo, $gameService);
$categoryCtrl = new CategoryController($categoriesRepo);
$logCtrl      = new LogController($errorLogRepo);
$authService  = new AuthService($conn->pdo());
$authCtrl     = new AuthController($authService);
$authMiddleware = new AuthMiddleware($authService);

// Router
$router = new Router();

// Health check
$router->add('GET','/', fn()=> \Src\Utils\Response::json([
  'ok'=>true,'service'=>'sg-ia-api','time'=>date('c')
]));

// Auth (Public)
$router->add('POST','/auth/login', fn()=> $authCtrl->login());

// Logging (Public - sin autenticación)
$router->add('POST','/logs/error', fn()=> $logCtrl->logError());

// Players (Public)
$router->add('POST','/players', fn()=> $playerCtrl->create());
$router->add('GET','/players', fn()=> $playerCtrl->index());

// Game (Public)
$router->add('POST','/games/start', fn()=> $gameCtrl->start());
$router->add('GET','/games/next', fn()=> $gameCtrl->next());
$router->add('POST','/games/{id}/answer', fn($p)=> $gameCtrl->answer($p));

// Questions (Public)
// $router->add('GET','/questions/{id}', fn($p)=> $questionCtrl->find($p));
$router->add('GET','/admin/questions', fn()=> $questionCtrl->list(), fn()=> $authMiddleware->validate());
// $router->add('PATCH','/admin/questions/{id}/verify', fn($p)=> $questionCtrl->verify($p), fn()=> $authMiddleware->validate());
$router->add('DELETE','/admin/questions/{id}', fn($p)=> $questionCtrl->delete($p), fn()=> $authMiddleware->validate());

// Stats (Public)
$router->add('GET','/stats/session/{id}', fn($p)=> $statsCtrl->session($p));

$router->add('GET','/stats/player/{id}', fn($p)=> $statsCtrl->playerStats($p));
$router->add('GET','/stats/leaderboard', fn()=> $statsCtrl->leaderboard());

// Question Management
$router->add('PUT','/admin/questions/{id}', fn($p)=> $adminCtrl->updateQuestion($p), fn()=> $authMiddleware->validate());
$router->add('PATCH','/admin/questions/{id}/verify', fn($p)=> $adminCtrl->verifyQuestion($p), fn()=> $authMiddleware->validate());

// Prompt Configuration
$router->add('GET','/admin/config/prompt', fn()=> $adminCtrl->getPromptConfig(), fn()=> $authMiddleware->validate());
$router->add('PUT','/admin/config/prompt', fn()=> $adminCtrl->updatePromptConfig(), fn()=> $authMiddleware->validate());

// Category Management
$router->add('GET','/admin/categories', fn()=> $categoryCtrl->list(), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/categories', fn()=> $adminCtrl->createCategory(), fn()=> $authMiddleware->validate());
$router->add('DELETE','/admin/categories/{id}', fn($p)=> $adminCtrl->deleteCategory($p), fn()=> $authMiddleware->validate());

// Batch Generation
$router->add('POST','/admin/generate-batch', fn()=> $adminCtrl->generateBatch(), fn()=> $authMiddleware->validate());

// Dashboard Analytics
$router->add('GET','/admin/dashboard', fn()=> $adminCtrl->dashboardStats(), fn()=> $authMiddleware->validate());

// Dispatch
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $router->dispatch($method, $path);
} catch (Throwable $e) {
  $status = $config['app']['debug'] ? 500 : 400;
  Response::json(['ok'=>false,'error'=>$e->getMessage()], $status);
}