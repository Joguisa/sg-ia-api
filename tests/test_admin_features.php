<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Src\Database\Connection;
use Src\Services\AI\GeminiAIService;
use Src\Repositories\Implementations\SystemPromptRepository;
use Dotenv\Dotenv;

echo "═══════════════════════════════════════════════════════════\n";
echo " PRUEBA DE CARACTERÍSTICAS ADMIN (IA + BD)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Cargar Entorno
$envPath = __DIR__ . '/../';
if (file_exists($envPath . '.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// 2. Conectar a Base de Datos
$config = require __DIR__ . '/../config/database.php';
$conn = new Connection($config);
$pdo = $conn->pdo();

// 3. Inicializar Repositorio de Prompts
$promptRepo = new SystemPromptRepository($conn);
$activePrompt = $promptRepo->getActive();

if (!$activePrompt) {
    die("❌ Error: No hay ningún prompt activo en la tabla 'system_prompts'. Ejecuta la migración 004.\n");
}

echo "✅ Prompt Activo encontrado en BD (ID: {$activePrompt->id})\n";
echo "   Temperatura configurada: {$activePrompt->temperature}\n";
echo "   Fragmento del texto: \"" . substr($activePrompt->promptText, 0, 50) . "...\"\n\n";

// 4. Inicializar Servicio IA (Con inyección del Repo)
$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
$aiService = new GeminiAIService($apiKey, $promptRepo);

// 5. Prueba de Generación
echo "🔄 Solicitando a Gemini una pregunta usando el prompt de la BD...\n";

try {
    // Simulamos pedir una pregunta de dificultad 5 (Experto)
    $data = $aiService->generateQuestion('Factores de Riesgo', 5);
    
    echo "\n✅ ¡ÉXITO! La IA respondió correctamente usando la configuración dinámica:\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "Pregunta: {$data['statement']}\n";
    echo "Opciones:\n";
    foreach ($data['options'] as $opt) {
        echo " [" . ($opt['is_correct'] ? 'x' : ' ') . "] {$opt['text']}\n";
    }
    echo "─────────────────────────────────────────────────────────\n";
    echo "Fuente: {$data['source_ref']}\n";

} catch (Exception $e) {
    echo "\n❌ Error en la generación: " . $e->getMessage() . "\n";
}