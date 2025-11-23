<?php
// 1. Ajuste de ruta: Subimos un nivel (../) para encontrar el vendor
require_once __DIR__ . '/../vendor/autoload.php';

use Src\Services\AI\GeminiAIService;
use Dotenv\Dotenv;

echo "---------------------------------------------------\n";
echo "PRUEBA DE CONEXIÓN GEMINI AI (Desde carpeta tests)\n";
echo "---------------------------------------------------\n";

// 2. Cargar variables de entorno desde la RAÍZ del proyecto (../)
try {
    $envPath = __DIR__ . '/../';
    if (file_exists($envPath . '.env')) {
        // Solo intentamos cargar Dotenv si la clase existe (ya instalada)
        if (class_exists(Dotenv::class)) {
            $dotenv = Dotenv::createImmutable($envPath);
            $dotenv->load();
        }
    }
} catch (Exception $e) {
    echo "Advertencia: " . $e->getMessage() . "\n";
}

// 3. Obtener la clave (prioridad: variable cargada > variable de sistema)
$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');

if (!$apiKey) {
    die("Error Crítico: No se encontró GEMINI_API_KEY en el entorno.\n   Asegúrate de haber creado el archivo .env en la raíz del proyecto.\n");
}

echo "API Key detectada: " . substr($apiKey, 0, 5) . "...\n";
echo "Intentando conectar con Gemini...\n";

try {
    $ai = new GeminiAIService($apiKey);

    echo "Intentando generar pregunta...\n";
    $result = $ai->generateQuestion('Prevención del Cáncer de Colon', 3);
    
    echo "\n ¡CONEXIÓN EXITOSA! Gemini ha respondido:\n";
    echo "---------------------------------------------------\n";
    echo "Pregunta Generada: " . $result['statement'] . "\n";
    echo "\nOpciones:\n";
    foreach ($result['options'] as $index => $opt) {
        $marker = $opt['is_correct'] ? '[CORRECTA]' : '[ ]';
        echo " " . ($index + 1) . ". $marker " . $opt['text'] . "\n";
    }
    echo "\nExplicación: " . $result['explanation'] . "\n";
    echo "Fuente: " . ($result['source_ref'] ?? 'N/A') . "\n";
    echo "---------------------------------------------------\n";

} catch (Exception $e) {
    echo "\n FALLÓ LA CONEXIÓN:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    
    if (str_contains($e->getMessage(), '401')) {
        echo "Pista: Verifica que tu API Key sea correcta y esté activa en Google Cloud Console.\n";
    }
}