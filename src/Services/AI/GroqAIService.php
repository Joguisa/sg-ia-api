<?php

declare(strict_types=1);

namespace Src\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;
use Src\Services\SSL\CertificateManager;
use Throwable;

final class GroqAIService implements GenerativeAIInterface
{
    private Client $client;
    private string $apiKey;
    private string $apiEndpoint = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        string $apiKey,
        private ?SystemPromptRepositoryInterface $prompts = null
    ) {
        $this->apiKey = $apiKey;
        $this->client = $this->createHttpClient();
    }

    private function createHttpClient(): Client
    {
        $config = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ]
        ];

        try {
            $cacertPath = CertificateManager::getCertificatePath();
            if (CertificateManager::isValidCertificate($cacertPath)) {
                $config['verify'] = $cacertPath;
            }
        } catch (Throwable $e) {
            error_log("SSL Certificate configuration failed: " . $e->getMessage());
        }

        return new Client($config);
    }

    public function generateQuestion(string $topic, int $difficulty): array
    {
        $prompt = $this->buildSystemPrompt($topic, $difficulty);
        $temperature = $this->getTemperature();

        try {
            $response = $this->client->post($this->apiEndpoint, [
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un experto oncólogo generador de preguntas educativas.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => 2048,
                    'response_format' => ['type' => 'json_object']
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['choices'][0]['message']['content'])) {
                error_log("Unexpected Groq response: " . json_encode($body));
                throw new \RuntimeException('Respuesta inesperada de Groq API');
            }

            $groqText = $body['choices'][0]['message']['content'];
            return $this->parseAIResponse($groqText);
        } catch (GuzzleException $e) {
            if ($this->isRateLimitError($e)) {
                throw new \RuntimeException('RATE_LIMIT_EXCEEDED: ' . $e->getMessage());
            }
            throw new \RuntimeException('Error en solicitud a Groq API: ' . $e->getMessage());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Error procesando respuesta de Groq: ' . $e->getMessage());
        }
    }

    public function validateAnswer(string $question, string $answer): array
    {
        $prompt = $this->buildValidationPrompt($question, $answer);

        try {
            $response = $this->client->post($this->apiEndpoint, [
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 500,
                    'response_format' => ['type' => 'json_object']
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['choices'][0]['message']['content'])) {
                throw new \RuntimeException('Respuesta inesperada de Groq API');
            }

            $groqText = $body['choices'][0]['message']['content'];
            return $this->parseValidationResponse($groqText);
        } catch (GuzzleException $e) {
            if ($this->isRateLimitError($e)) {
                throw new \RuntimeException('RATE_LIMIT_EXCEEDED: ' . $e->getMessage());
            }
            throw new \RuntimeException('Error en validación con Groq API: ' . $e->getMessage());
        }
    }

    private function isRateLimitError(GuzzleException $e): bool
    {
        return strpos($e->getMessage(), '429') !== false ||
            strpos($e->getMessage(), 'rate limit') !== false;
    }

    private function buildSystemPrompt(string $topic, int $difficulty): string
    {
        $difficultyDesc = match ($difficulty) {
            1 => 'muy básico (conocimientos fundamentales)',
            2 => 'básico (conceptos clave)',
            3 => 'intermedio (aplicación clínica)',
            4 => 'avanzado (diagnóstico diferencial)',
            5 => 'experto (casos complejos y guías internacionales)',
            default => 'intermedio'
        };

        $templatePrompt = $this->getPromptTemplate();

        return str_replace(
            ['{topic}', '{difficulty}', '{difficulty_desc}'],
            [$topic, (string)$difficulty, $difficultyDesc],
            $templatePrompt
        );
    }

    private function getPromptTemplate(): string
    {
        if (!$this->prompts) {
            return $this->getDefaultPromptTemplate();
        }

        $prompt = $this->prompts->getActive();
        if (!$prompt) {
            return $this->getDefaultPromptTemplate();
        }

        return $prompt->promptText;
    }

    private function getTemperature(): float
    {
        if (!$this->prompts) {
            return 0.7;
        }

        $prompt = $this->prompts->getActive();
        if (!$prompt) {
            return 0.7;
        }

        return $prompt->temperature;
    }

    private function getDefaultPromptTemplate(): string
    {
        return <<<'EOT'
        Eres un experto oncólogo y educador sanitario especializado en Cáncer de Colon.
        Genera EXACTAMENTE 1 pregunta de opción múltiple sobre {topic} para nivel de dificultad {difficulty} ({difficulty_desc}).

        Contexto educativo: Alfabetización sobre Cáncer de Colon en Ecuador
        Estándares: Basado en Guías MSP Ecuador y OMS

        INSTRUCCIONES CRÍTICAS:
        1. Genera SOLO un JSON válido, sin markdown ni comentarios
        2. Estructura EXACTA: { "statement": "...", "options": [{"text": "...", "is_correct": bool}], "explanation_correct": "...", "explanation_incorrect": "...", "source_ref": "..." }
        3. Incluye exactamente 4 opciones
        4. Una sola opción debe ser correcta (is_correct: true)
        5. El enunciado debe ser claro y conciso (100-300 caracteres)
        6. Opciones balanceadas, ninguna obviamente incorrecta
        7. Genera DOS explicaciones diferentes:
           - explanation_correct: Retroalimentación positiva y refuerzo del concepto cuando el estudiante responde correctamente (50-100 palabras)
           - explanation_incorrect: Explicación educativa general sobre por qué la respuesta correcta es la adecuada, útil para quien se equivocó (50-100 palabras)
        8. source_ref: referencia a "Guías MSP Ecuador", "OMS", o literatura médica

        JSON VÁLIDO ESTRICTO (sin markdown):
        EOT;
    }

    private function buildValidationPrompt(string $question, string $answer): string
    {
        return "Eres un experto oncólogo evaluando respuestas sobre Cáncer de Colon.
                Pregunta: $question
                Respuesta del estudiante: $answer

                Evalúa si la respuesta es correcta o incorrecta. Proporciona un JSON ESTRICTO sin markdown.
                Estructura: { 'isCorrect': bool, 'explanation': string (máx 100 caracteres) }";
    }

    private function parseAIResponse(string $aiText): array
    {
        $json = null;

        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $aiText, $matches)) {
            $json = json_decode($matches[1], true);
        }

        if (!$json) {
            if (preg_match('/\{[\s\S]*\}/m', $aiText, $matches)) {
                $json = json_decode($matches[0], true);
            }
        }

        if (!$json || !isset($json['statement']) || !isset($json['options'])) {
            throw new \RuntimeException('JSON de Groq tiene estructura inválida');
        }

        if (!is_string($json['statement']) || empty($json['statement'])) {
            throw new \RuntimeException('statement debe ser un string no vacío');
        }

        if (!is_array($json['options']) || count($json['options']) !== 4) {
            throw new \RuntimeException('options debe ser un array con exactamente 4 elementos');
        }

        // Validar que existan ambas explicaciones
        if (!isset($json['explanation_correct']) || !isset($json['explanation_incorrect'])) {
            throw new \RuntimeException('Deben existir ambas explicaciones: explanation_correct y explanation_incorrect');
        }

        if (empty($json['explanation_correct']) || empty($json['explanation_incorrect'])) {
            throw new \RuntimeException('Las explicaciones no pueden estar vacías');
        }

        $correctCount = 0;
        $correctIndex = -1;

        foreach ($json['options'] as $idx => $opt) {
            if (!isset($opt['text'], $opt['is_correct'])) {
                throw new \RuntimeException('Cada opción debe tener text e is_correct');
            }
            if ($opt['is_correct']) {
                $correctCount++;
                $correctIndex = $idx;
            }
        }

        if ($correctCount !== 1) {
            throw new \RuntimeException('Debe haber exactamente 1 opción correcta');
        }

        return [
            'statement' => trim($json['statement']),
            'options' => $json['options'],
            'correctOption' => $correctIndex,
            'explanation_correct' => trim($json['explanation_correct']),
            'explanation_incorrect' => trim($json['explanation_incorrect']),
            'source_ref' => $json['source_ref'] ?? 'Groq AI'
        ];
    }

    private function parseValidationResponse(string $aiText): array
    {
        preg_match('/\{[\s\S]*\}/m', $aiText, $matches);

        if (empty($matches)) {
            throw new \RuntimeException('No se encontró JSON válido en respuesta de validación');
        }

        $json = json_decode($matches[0], true);

        if (!$json || !isset($json['isCorrect'], $json['explanation'])) {
            throw new \RuntimeException('Respuesta de validación tiene estructura inválida');
        }

        return [
            'isCorrect' => (bool)$json['isCorrect'],
            'explanation' => trim((string)$json['explanation'])
        ];
    }
}
