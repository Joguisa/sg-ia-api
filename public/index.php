<?php
declare(strict_types=1);

use Src\Database\Connection;
use Src\Utils\Router;
use Src\Utils\Response;
use Src\Middleware\CorsMiddleware;
use Src\Middleware\AuthMiddleware;
use Src\Middleware\SuperAdminMiddleware;
use Dotenv\Dotenv;

use Src\Repositories\Implementations\{PlayerRepository,QuestionRepository,SessionRepository,AnswerRepository,SystemPromptRepository,CategoryRepository,ErrorLogRepository,QuestionBatchRepository,RoomRepository,AdminRepository};
use Src\Controllers\{PlayerController,GameController,QuestionController,StatisticsController,AdminController,AuthController,CategoryController,LogController,RoomController};
use Src\Services\{GameService,AIEngine,AuthService,RoomService,ExportService};
use Src\Services\AI\GeminiAIService;
use Src\Services\AI\GroqAIService;
use Src\Services\AI\DeepSeekAIService;
use Src\Services\AI\FireworksAIService;
use Src\Services\AI\MultiAIService;

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
$batchRepo     = new QuestionBatchRepository($conn);
$roomRepo      = new RoomRepository($conn);
$adminRepo     = new AdminRepository($conn);
$ai            = new AIEngine();

// $generativeAi = null;
// if (!empty($config['gemini']['api_key'] ?? null)) {
//   $generativeAi = new GeminiAIService($config['gemini']['api_key'], $promptsRepo);
// }

// 1. CONFIGURAR PROVEEDORES DE IA
$aiProvidersConfig = [
  'gemini' => [
      'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
      'enabled' => filter_var($_ENV['GEMINI_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
  ],
  'groq' => [
      'api_key' => $_ENV['GROQ_API_KEY'] ?? '',
      'enabled' => filter_var($_ENV['GROQ_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
  ],
  'deepseek' => [
      'api_key' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
      'enabled' => filter_var($_ENV['DEEPSEEK_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
  ],
  'fireworks' => [
      'api_key' => $_ENV['FIREWORKS_API_KEY'] ?? '',
      'enabled' => filter_var($_ENV['FIREWORKS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN)
  ]
];

// 2. OBTENER PROVEEDOR PREFERIDO DE BD
$preferredProvider = $promptsRepo->getPreferredProvider();

// 3. CREAR SERVICIO MULTI-IA (fallover automático) CON PROVEEDOR PREFERIDO
$generativeAi = new MultiAIService($aiProvidersConfig, $promptsRepo, $preferredProvider);

// 4. LOG del proveedor activo (para debugging)
error_log("Proveedor IA preferido: {$preferredProvider}");
error_log("Proveedor IA inicial: " . $generativeAi->getActiveProvider());

$gameService = new GameService($sessionsRepo, $questionsRepo, $answersRepo, $playersRepo, $ai, $generativeAi, $batchRepo, $roomRepo);
$roomService = new RoomService($roomRepo);
$exportService = new ExportService();

$playerCtrl   = new PlayerController($playersRepo);
$gameCtrl     = new GameController($gameService, $sessionsRepo);
$questionCtrl = new QuestionController($questionsRepo);
$statsCtrl    = new StatisticsController($conn);
$adminCtrl    = new AdminController($questionsRepo, $promptsRepo, $gameService, $batchRepo, $categoriesRepo, $adminRepo);
$categoryCtrl = new CategoryController($categoriesRepo);
$logCtrl      = new LogController($errorLogRepo);
$roomCtrl     = new RoomController($roomService, $exportService);
$authService  = new AuthService($conn->pdo());
$authCtrl     = new AuthController($authService);
$authMiddleware = new AuthMiddleware($authService);
$superAdminMiddleware = new SuperAdminMiddleware($authService);

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
$router->add('GET','/stats/session/{id}/answers', fn($p)=> $statsCtrl->sessionAnswers($p));
$router->add('GET','/stats/session/{id}/streaks', fn($p)=> $statsCtrl->sessionStreaks($p));
$router->add('GET','/stats/player/{id}', fn($p)=> $statsCtrl->playerStats($p));
$router->add('GET','/stats/player/{id}/sessions', fn($p)=> $statsCtrl->playerSessions($p));
$router->add('GET','/stats/player/{id}/streaks', fn($p)=> $statsCtrl->playerStreaks($p));
$router->add('GET','/stats/leaderboard', fn()=> $statsCtrl->leaderboard());

// Question Management
$router->add('PUT','/admin/questions/{id}', fn($p)=> $adminCtrl->updateQuestion($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/questions/{id}/full', fn($p)=> $adminCtrl->getQuestionFull($p), fn()=> $authMiddleware->validate());
$router->add('PUT','/admin/questions/{id}/full', fn($p)=> $adminCtrl->updateQuestionFull($p), fn()=> $authMiddleware->validate());
$router->add('PATCH','/admin/questions/{id}/verify', fn($p)=> $adminCtrl->verifyQuestion($p), fn()=> $authMiddleware->validate());

// Prompt Configuration
$router->add('GET','/admin/config/prompt', fn()=> $adminCtrl->getPromptConfig(), fn()=> $authMiddleware->validate());
$router->add('PUT','/admin/config/prompt', fn()=> $adminCtrl->updatePromptConfig(), fn()=> $authMiddleware->validate());

// Category Management
$router->add('GET','/admin/categories', fn()=> $categoryCtrl->list(), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/categories', fn()=> $adminCtrl->createCategory(), fn()=> $authMiddleware->validate());
$router->add('PUT','/admin/categories/{id}', fn($p)=> $adminCtrl->updateCategory($p), fn()=> $authMiddleware->validate());
$router->add('DELETE','/admin/categories/{id}', fn($p)=> $adminCtrl->deleteCategory($p), fn()=> $authMiddleware->validate());

// Batch Generation
$router->add('POST','/admin/generate-batch', fn()=> $adminCtrl->generateBatch(), fn()=> $authMiddleware->validate());

// Dashboard Analytics
$router->add('GET','/admin/dashboard', fn()=> $adminCtrl->dashboardStats(), fn()=> $authMiddleware->validate());

// Batch Management & Verification
$router->add('POST','/admin/batch/{batchId}/verify', fn($p)=> $adminCtrl->verifyBatch($p), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/questions/verify-bulk', fn()=> $adminCtrl->verifyBulk(), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/questions/unverify-bulk', fn()=> $adminCtrl->unverifyBulk(), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/questions/delete-bulk', fn()=> $adminCtrl->deleteBulk(), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/batch/import-csv', fn()=> $adminCtrl->importCSV(), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/csv-template', fn()=> $adminCtrl->downloadCsvTemplate(), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/unverified', fn()=> $adminCtrl->getUnverifiedQuestions(), fn()=> $authMiddleware->validate());
$router->add('PUT','/admin/explanation/{explanationId}', fn($p)=> $adminCtrl->editExplanation($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/batch-statistics', fn()=> $adminCtrl->getBatchStatistics(), fn()=> $authMiddleware->validate());

// AI Providers
$router->add('GET','/admin/available-providers', fn()=> $adminCtrl->getAvailableProviders(), fn()=> $authMiddleware->validate());

// Room Management (Admin Protected)
$router->add('POST','/admin/rooms', fn($p)=> $roomCtrl->create($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms', fn($p)=> $roomCtrl->list($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}', fn($p)=> $roomCtrl->get($p), fn()=> $authMiddleware->validate());
$router->add('PUT','/admin/rooms/{id}', fn($p)=> $roomCtrl->update($p), fn()=> $authMiddleware->validate());
$router->add('DELETE','/admin/rooms/{id}', fn($p)=> $roomCtrl->delete($p), fn()=> $authMiddleware->validate());
$router->add('PATCH','/admin/rooms/{id}/status', fn($p)=> $roomCtrl->updateStatus($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}/players', fn($p)=> $roomCtrl->getPlayers($p), fn()=> $authMiddleware->validate());

// Room Statistics (Admin Protected)
$router->add('GET','/admin/rooms/{id}/stats', fn($p)=> $roomCtrl->getStats($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}/stats/players', fn($p)=> $roomCtrl->getPlayerStats($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}/stats/questions', fn($p)=> $roomCtrl->getQuestionStats($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}/stats/categories', fn($p)=> $roomCtrl->getCategoryStats($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}/stats/analysis', fn($p)=> $roomCtrl->getQuestionAnalysis($p), fn()=> $authMiddleware->validate());

// Room Export (Admin Protected)
$router->add('GET','/admin/rooms/{id}/export/pdf', fn($p)=> $roomCtrl->exportPdf($p), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/rooms/{id}/export/excel', fn($p)=> $roomCtrl->exportExcel($p), fn()=> $authMiddleware->validate());


// Room Public Endpoints (for players)
$router->add('GET','/rooms/validate/{code}', fn($p)=> $roomCtrl->validateCode($p));

// Admin Management (Superadmin Protected for write operations)
$router->add('GET','/admin/admins', fn()=> $adminCtrl->indexAdmins(), fn()=> $authMiddleware->validate());
$router->add('GET','/admin/admins/{id}', fn($p)=> $adminCtrl->showAdmin($p), fn()=> $authMiddleware->validate());
$router->add('POST','/admin/admins', fn()=> $adminCtrl->storeAdmin(), fn()=> $superAdminMiddleware->validate());
$router->add('PUT','/admin/admins/{id}', fn($p)=> $adminCtrl->updateAdmin($p), fn()=> $superAdminMiddleware->validate());
$router->add('DELETE','/admin/admins/{id}', fn($p)=> $adminCtrl->destroyAdmin($p), fn()=> $superAdminMiddleware->validate());
$router->add('PATCH','/admin/admins/{id}/status', fn($p)=> $adminCtrl->toggleAdminStatus($p), fn()=> $superAdminMiddleware->validate());


// Dispatch
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $router->dispatch($method, $path);
} catch (Throwable $e) {
  $status = $config['app']['debug'] ? 500 : 400;
  Response::json(['ok'=>false,'error'=>$e->getMessage()], $status);
}