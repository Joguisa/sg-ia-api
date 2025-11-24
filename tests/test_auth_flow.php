<?php
/**
 * Test Auth Flow
 *
 * Tests para validar el sistema de autenticación JWT:
 * 1. Intenta acceder a ruta admin sin token (Debe fallar con 401)
 * 2. Hace login correcto (Debe recibir token)
 * 3. Accede a ruta admin CON token (Debe funcionar con 200)
 */

declare(strict_types=1);

// Configuración
$baseUrl = getenv('API_URL') ?: 'http://localhost:8000';
$adminEmail = 'admin@sg-ia.com';
$adminPassword = 'admin123';

// Colores para output
$colors = [
  'reset' => "\033[0m",
  'red' => "\033[91m",
  'green' => "\033[92m",
  'yellow' => "\033[93m",
  'blue' => "\033[94m",
];

function testLog(string $message, string $color = 'blue'): void {
  global $colors;
  echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
}

function testResult(string $testName, bool $passed): void {
  global $colors;
  $status = $passed ? '✓ PASS' : '✗ FAIL';
  $color = $passed ? 'green' : 'red';
  echo $colors[$color] . "[$status] $testName" . $colors['reset'] . PHP_EOL;
}

// ============================================================
// TEST 1: Acceder a ruta admin SIN token (Debe fallar 401)
// ============================================================
testLog("\n=== TEST 1: Acceder a ruta admin SIN token ===", 'yellow');

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => "{$baseUrl}/admin/questions/1",
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode(['statement' => 'Test']),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

testLog("HTTP Code: {$httpCode}", 'blue');
testLog("Response: {$response}", 'blue');

$test1Passed = $httpCode === 401;
testResult("Acceso sin token rechazado", $test1Passed);

// ============================================================
// TEST 2: Login correcto (Debe recibir token)
// ============================================================
testLog("\n=== TEST 2: Login correcto ===", 'yellow');

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => "{$baseUrl}/auth/login",
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode([
    'email' => $adminEmail,
    'password' => $adminPassword
  ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

testLog("HTTP Code: {$httpCode}", 'blue');
testLog("Response: {$response}", 'blue');

$loginData = json_decode($response, true);
$token = $loginData['token'] ?? null;

$test2Passed = $httpCode === 200 && !empty($token);
testResult("Login exitoso y token generado", $test2Passed);

if (!$test2Passed) {
  testLog("Error: No se pudo obtener el token", 'red');
  exit(1);
}

testLog("Token recibido: " . substr($token, 0, 50) . "...", 'green');

// ============================================================
// TEST 3: Acceder a ruta admin CON token (Debe funcionar 200)
// ============================================================
testLog("\n=== TEST 3: Acceder a ruta admin CON token ===", 'yellow');

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => "{$baseUrl}/admin/questions/1",
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    "Authorization: Bearer {$token}"
  ],
  CURLOPT_POSTFIELDS => json_encode([
    'statement' => 'Pregunta actualizada de prueba',
    'difficulty' => 2
  ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

testLog("HTTP Code: {$httpCode}", 'blue');
testLog("Response: {$response}", 'blue');

$test3Passed = $httpCode === 200;
testResult("Acceso con token autorizado", $test3Passed);

// ============================================================
// TEST 4: Login con credenciales incorrectas (Debe fallar 401)
// ============================================================
testLog("\n=== TEST 4: Login con credenciales incorrectas ===", 'yellow');

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => "{$baseUrl}/auth/login",
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode([
    'email' => $adminEmail,
    'password' => 'wrongpassword'
  ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

testLog("HTTP Code: {$httpCode}", 'blue');
testLog("Response: {$response}", 'blue');

$test4Passed = $httpCode === 401;
testResult("Login rechazado con credenciales incorrectas", $test4Passed);

// ============================================================
// TEST 5: Validar token inválido (Debe fallar 401)
// ============================================================
testLog("\n=== TEST 5: Token inválido ===", 'yellow');

$invalidToken = "invalid.token.here";

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => "{$baseUrl}/admin/questions/1",
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    "Authorization: Bearer {$invalidToken}"
  ],
  CURLOPT_POSTFIELDS => json_encode(['statement' => 'Test']),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

testLog("HTTP Code: {$httpCode}", 'blue');
testLog("Response: {$response}", 'blue');

$test5Passed = $httpCode === 401;
testResult("Token inválido rechazado", $test5Passed);

// ============================================================
// RESUMEN
// ============================================================
testLog("\n=== RESUMEN DE PRUEBAS ===", 'yellow');

$allPassed = $test1Passed && $test2Passed && $test3Passed && $test4Passed && $test5Passed;
$totalTests = 5;
$passedTests = array_sum([
  $test1Passed,
  $test2Passed,
  $test3Passed,
  $test4Passed,
  $test5Passed
]);

testLog("Pruebas pasadas: {$passedTests}/{$totalTests}", $allPassed ? 'green' : 'red');

if ($allPassed) {
  testLog("✓ ¡TODOS LOS TESTS PASARON!", 'green');
  exit(0);
} else {
  testLog("✗ Algunos tests fallaron", 'red');
  exit(1);
}
