<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Src\Database\Connection;
use Src\Services\GameService;
use Src\Services\AIEngine;
use Src\Services\AI\GeminiAIService;
use Src\Repositories\Implementations\{
    PlayerRepository,
    QuestionRepository,
    SessionRepository,
    AnswerRepository,
    SystemPromptRepository 
};
use Dotenv\Dotenv;

echo "═══════════════════════════════════════════════════════════\n";
echo "API INTEGRATION TEST SUITE (CON IA)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Cargar Entorno (Para la API Key)
$envPath = __DIR__ . '/../';
if (file_exists($envPath . '.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

$config = require __DIR__ . '/../config/database.php';
$conn = new Connection($config);
$pdo = $conn->pdo();

// 2. Repositorios
$playersRepo = new PlayerRepository($conn);
$questionsRepo = new QuestionRepository($conn);
$sessionsRepo = new SessionRepository($conn);
$answersRepo = new AnswerRepository($conn);
$promptRepo = new SystemPromptRepository($conn); // Nuevo repo para IA
$aiEngine = new AIEngine();

// 3. Inicializar IA (Si hay API Key)
$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
$generativeAi = null;
if ($apiKey) {
    echo "IA Activada para el test\n";
    $generativeAi = new GeminiAIService($apiKey, $promptRepo);
} else {
    echo "Advertencia: Sin API Key, el test fallará si la BD está vacía.\n";
}

// 4. Inyectar todo en GameService
$gameService = new GameService(
    $sessionsRepo, 
    $questionsRepo, 
    $answersRepo, 
    $playersRepo, 
    $aiEngine, 
    $generativeAi // <--- ¡ESTO FALTABA!
);

// ============================================================
// TEST 1: Verify Player Exists
// ============================================================
echo "\n[TEST 1] Verify Player Exists\n";
echo "─────────────────────────────────────────────────────────\n";

$player = $playersRepo->find(1);
if (!$player) {
    // Crear con edad (fix anterior)
    $testPlayer = $playersRepo->create('Integration Test Player', 25);
    $player = $testPlayer;
}
echo "Player: {$player->name} (Age: {$player->age})\n";

// ============================================================
// TEST 2: POST /games/start
// ============================================================
echo "\n[TEST 2] Start Session\n";
echo "─────────────────────────────────────────────────────────\n";
$sessionResult = $gameService->startSession($player->id, 1.0);
$sessionId = $sessionResult['session_id'];
echo "Session ID: {$sessionId}\n";

// ============================================================
// TEST 3: GET /games/next (Trigger IA)
// ============================================================
echo "\n[TEST 3] Get Next Question (Triggering Gemini...)\n";
echo "─────────────────────────────────────────────────────────\n";

// Buscar una categoría válida (cualquiera)
$catStmt = $pdo->query("SELECT id FROM question_categories LIMIT 1");
$catId = $catStmt->fetchColumn();

if (!$catId) die("Error: No hay categorías en la BD. Ejecuta db/init.sql");

echo "Solicitando pregunta para Categoría ID: {$catId}...\n";

// Esto llamará a la IA porque la tabla questions está vacía
$questionResult = $gameService->nextQuestion((int)$catId, 1);

if (!$questionResult) {
    die("Error: No se generó pregunta. Verifica API Key o conexión.\n");
}

$questionId = $questionResult['id'];
echo "Pregunta Generada: {$questionResult['statement']}\n";
echo "   ID: {$questionId} | Dificultad: {$questionResult['difficulty']}\n";
echo "   (Guardada en BD con is_ai_generated = 1)\n";

// ============================================================
// TEST 4: Submit Answer
// ============================================================
echo "\n[TEST 4] Submit Answer & Feedback\n";
echo "─────────────────────────────────────────────────────────\n";

// Responder correctamente (simulado)
$answerResult = $gameService->submitAnswer(
    $sessionId,
    $questionId,
    null,
    true, // isCorrect
    2.0   // timeTaken
);

echo "Score: {$answerResult['score']}\n";
echo "Feedback: {$answerResult['explanation']}\n";

if (!empty($answerResult['explanation'])) {
    echo "Feedback educativo recibido correctamente.\n";
} else {
    echo "Falta feedback educativo.\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "TEST DE FLUJO COMPLETADO\n";
echo "═══════════════════════════════════════════════════════════\n";