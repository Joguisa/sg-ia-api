<?php
// Cargar el autoloader de Composer para poder usar las clases de src/
require_once __DIR__ . '/../vendor/autoload.php';

use Src\Services\AIEngine;

// Definición de colores para consola (para que se vea bonito)
const GREEN = "\033[32m";
const RED = "\033[31m";
const RESET = "\033[0m";

echo "---------------------------------------------------\n";
echo "INICIANDO PRUEBAS DEL MOTOR ADAPTATIVO (IA)\n";
echo "---------------------------------------------------\n";

try {
    $ai = new AIEngine();
} catch (Error $e) {
    die(RED . "Error Crítico: No se pudo instanciar AIEngine. Verifica que el namespace sea correcto en el archivo AIEngine.php.\n" . $e->getMessage() . RESET);
}

$testsPassed = 0;
$totalTests = 0;

/**
 * Función auxiliar para ejecutar cada caso de prueba
 */
function runTest($name, $currentDiff, $isCorrect, $time, $expectedDiff, $ai) {
    global $testsPassed, $totalTests;
    $totalTests++;

    $actualDiff = $ai->nextDifficulty($currentDiff, $isCorrect, $time);

    echo "Caso #{$totalTests}: {$name}\n";
    echo "   Entrada: Nivel {$currentDiff} | " . ($isCorrect ? 'Correcta' : 'Incorrecta') . " | {$time}s\n";

    // Comparamos usando una pequeña tolerancia para flotantes, aunque round() ayuda
    if ($actualDiff === $expectedDiff) {
        echo "   " . GREEN . "PASS" . RESET . " -> Resultado: {$actualDiff}\n\n";
        $testsPassed++;
    } else {
        echo "   " . RED . "FAIL" . RESET . " -> Esperado: {$expectedDiff}, Obtenido: {$actualDiff}\n\n";
    }
}

// --- ESCENARIOS DE PRUEBA (Basados en las reglas de negocio) ---

// 1. Respuesta "Genio" (Rápida < 3s) -> Debe sumar +0.50
runTest("Maestría Rápida", 2.00, true, 2.5, 2.50, $ai);

// 2. Respuesta "Normal" (3-6s) -> Debe sumar +0.25
runTest("Respuesta Estándar", 2.00, true, 4.0, 2.25, $ai);

// 3. Respuesta "Dudosa" (> 6s) -> Debe sumar +0.10
runTest("Respuesta Lenta", 2.00, true, 8.0, 2.10, $ai);

// 4. Respuesta Incorrecta -> Debe restar -0.25
runTest("Error del Jugador", 2.00, false, 5.0, 1.75, $ai);

// 5. Límite Superior -> No debe pasar de 5.00
// 4.8 + 0.5 = 5.3, pero el techo es 5.00
runTest("Techo de Dificultad", 4.80, true, 1.0, 5.00, $ai);

// 6. Límite Inferior -> No debe bajar de 1.00
// 1.1 - 0.25 = 0.85, pero el piso es 1.00
runTest("Piso de Dificultad", 1.10, false, 5.0, 1.00, $ai);

echo "---------------------------------------------------\n";
if ($testsPassed === $totalTests) {
    echo GREEN . "RESULTADO FINAL: {$testsPassed}/{$totalTests} PRUEBAS EXITOSAS" . RESET . "\n";
} else {
    echo RED . "ALERTA: FALLARON " . ($totalTests - $testsPassed) . " PRUEBAS" . RESET . "\n";
}
echo "---------------------------------------------------\n";
