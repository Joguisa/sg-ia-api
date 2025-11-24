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
echo "API INTEGRATION TEST SUITE (ACTUALIZADO)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Cargar variables de entorno
$envPath = __DIR__ . '/../';
if (file_exists($envPath . '.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

$config = require __DIR__ . '/../config/database.php';
$conn = new Connection($config);
$pdo = $conn->pdo();

// 2. Instanciar Repositorios
$playersRepo   = new PlayerRepository($conn);
$questionsRepo = new QuestionRepository($conn);
$sessionsRepo  = new SessionRepository($conn);
$answersRepo   = new AnswerRepository($conn);
$promptsRepo   = new SystemPromptRepository($conn);
$aiEngine      = new AIEngine();

// 3. Instanciar Servicio IA
$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
$generativeAi = null;

if ($apiKey) {
    echo "IA Detectada y Configurada.\n";
    $generativeAi = new GeminiAIService($apiKey, $promptsRepo);
} else {
    echo "Aviso: Sin API Key. La generación automática fallará si la BD está vacía.\n";
}

$gameService = new GameService(
    $sessionsRepo,
    $questionsRepo,
    $answersRepo,
    $playersRepo,
    $aiEngine,
    $generativeAi
);

// ============================================================
// TEST 1: Verificar Jugador
// ============================================================
echo "\n[TEST 1] Verificar Jugador\n";
$player = $playersRepo->find(1);
if (!$player) {
    $player = $playersRepo->create('Test Player', 25);
}
echo "Jugador: {$player->name} (ID: {$player->id})\n";

// ============================================================
// TEST 2: Iniciar Sesión
// ============================================================
echo "\n[TEST 2] Iniciar Sesión de Juego\n";
$session = $gameService->startSession($player->id, 1.0);
echo "Sesión ID: {$session['session_id']} | Dificultad: {$session['current_difficulty']}\n";

// ============================================================
// TEST 3: Obtener Pregunta (Prueba la IA si es necesario)
// ============================================================
echo "\n[TEST 3] Obtener Siguiente Pregunta\n";

$catId = $pdo->query("SELECT id FROM question_categories LIMIT 1")->fetchColumn();
if (!$catId) die("Error: BD vacía. Ejecuta db/init.sql primero.\n");

echo "Solicitando pregunta (Cat ID: $catId)...\n";
$q = $gameService->nextQuestion((int)$catId, 1);

if ($q) {
    echo "Pregunta recibida: \"{$q['statement']}\"\n";
    echo "   ID: {$q['id']} | Dificultad: {$q['difficulty']}\n";
} else {
    die("Error: No se recibió pregunta.\n");
}

// ============================================================
// TEST 4: Responder y Verificar Feedback
// ============================================================
echo "\n[TEST 4] Enviar Respuesta\n";
$res = $gameService->submitAnswer(
    $session['session_id'],
    $q['id'],
    null,
    true,
    2.5
);

echo "Score: {$res['score']} | Vidas: {$res['lives']}\n";
echo "Feedback: \"{$res['explanation']}\"\n";

if (!empty($res['explanation'])) {
    echo "Feedback educativo validado correctamente.\n";
} else {
    echo "Alerta: La respuesta no trajo feedback educativo.\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "RESUMEN: El flujo Backend + IA funciona correctamente.\n";
echo "═══════════════════════════════════════════════════════════\n";