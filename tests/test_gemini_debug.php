<?php
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Src\Services\SSL\CertificateManager;
use Dotenv\Dotenv;

echo "Test de Debug Gemini API\n";
echo str_repeat("-", 60) . "\n";

try {
    $envPath = __DIR__ . '/../';
    if (file_exists($envPath . '.env') && class_exists(Dotenv::class)) {
        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->load();
    }
} catch (Exception $e) {
    echo "Advertencia: " . $e->getMessage() . "\n";
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');

if (!$apiKey) {
    die("Error: No GEMINI_API_KEY\n");
}

echo "API Key: " . substr($apiKey, 0, 10) . "...\n\n";

$config = [
    'timeout' => 30,
    'connect_timeout' => 10
];

try {
    $cacertPath = CertificateManager::getCertificatePath();
    echo "Certificado SSL: $cacertPath\n";
    if (file_exists($cacertPath)) {
        $config['verify'] = $cacertPath;
        echo "Certificado encontrado: " . filesize($cacertPath) . " bytes\n";
    }
} catch (Throwable $e) {
    echo "Advertencia SSL: " . $e->getMessage() . "\n";
}

$client = new Client($config);

$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
$prompt = 'Eres un experto médico. Responde en JSON: {"message": "test"}';

echo "\nEndpoint: $endpoint\n";
echo "Prompt: $prompt\n\n";

try {
    $response = $client->post($endpoint, [
        'query' => ['key' => $apiKey],
        'json' => [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]
    ]);

    echo "Status Code: " . $response->getStatusCode() . "\n";

    $body = $response->getBody()->getContents();
    echo "\nRespuesta (cruda):\n";
    echo $body . "\n\n";

    $decoded = json_decode($body, true);
    echo "Respuesta (JSON decodificado):\n";
    var_dump($decoded);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
