<?php
/**
 * Test Auth Flow
 * Valida: Login, Token JWT y Protección de Rutas Admin
 */
declare(strict_types=1);

// Configuración
$baseUrl = 'http://localhost:8000';
$adminEmail = 'admin@sg-ia.com';
$adminPassword = 'admin123';

// Colores para consola
$red = "\033[31m";
$green = "\033[32m";
$reset = "\033[0m";

echo "═══════════════════════════════════════════════════════════\n";
echo " PRUEBA DE SEGURIDAD (JWT & RUTAS)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Función helper para peticiones
function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => json_decode($response, true)];
}

// 1. Intento de acceso SIN token (Debe fallar)
echo "1. Probando acceso protegido SIN token...\n";
$res = makeRequest('PUT', "$baseUrl/admin/questions/1", ['statement' => 'hack']);
if ($res['code'] === 401) {
    echo "{$green} Bloqueado correctamente (401){$reset}\n";
} else {
    echo "{$red} FALLO: Código {$res['code']} inesperado{$reset}\n";
}

// 2. Login (Obtener Token)
echo "\n2. Iniciando sesión como Admin...\n";
$login = makeRequest('POST', "$baseUrl/auth/login", ['email' => $adminEmail, 'password' => $adminPassword]);

$token = null;
if ($login['code'] === 200 && isset($login['body']['token'])) {
    $token = $login['body']['token'];
    echo "{$green} Login exitoso. Token recibido.{$reset}\n";
    echo "   Token: " . substr($token, 0, 15) . "...\n";
} else {
    die("{$red} FALLO: No se pudo iniciar sesión.{$reset}\n");
}

// 3. Acceso CON token (Debe funcionar)
echo "\n3. Probando acceso protegido CON token...\n";
// Intentamos verificar la pregunta 1 (existente en seed)
$verify = makeRequest('PATCH', "$baseUrl/admin/questions/1/verify", ['verified' => true], $token);

if ($verify['code'] === 200) {
    echo "{$green} Acceso autorizado correctamente (200){$reset}\n";
} else {
    echo "{$red} FALLO: Código {$verify['code']} - " . json_encode($verify['body']) . "{$reset}\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";