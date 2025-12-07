<?php
/**
 * Script de prueba para el endpoint POST /api/logs/error
 *
 * Uso: php test_error_log.php
 */

$apiUrl = 'http://localhost/api/logs/error';

// Datos de prueba
$testData = [
    'message' => 'Test error from frontend',
    'status' => 404,
    'status_text' => 'Not Found',
    'url' => 'http://localhost:4200/api/test'
];

echo "=== TEST: POST /api/logs/error ===\n";
echo "URL: $apiUrl\n";
echo "Payload: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Hacer la petición
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n\n";

// Validar respuesta
$decoded = json_decode($response, true);
if ($decoded && isset($decoded['ok']) && $decoded['ok'] === true) {
    echo "TEST PASSED: Endpoint responded successfully\n";
} else {
    echo "TEST FAILED: Unexpected response\n";
}

echo "\n=== Verifica la BD con: ===\n";
echo "SELECT * FROM error_logs ORDER BY created_at DESC LIMIT 5;\n";
