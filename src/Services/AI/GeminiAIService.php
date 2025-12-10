<?php

declare(strict_types=1);

namespace Src\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;
use Src\Services\SSL\CertificateManager;
use Throwable;

final class GeminiAIService implements GenerativeAIInterface
{
  private Client $client;
  private string $apiKey;
  private string $apiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

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
      'connect_timeout' => 10
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

  /**
   * Genera una pregunta automáticamente basada en tema y dificultad
   *
   * @param string $topic Tema o categoría (ej: "Cáncer de Colon")
   * @param int $difficulty Nivel de dificultad (1-5)
   * @return array Estructura: [
   *   'statement' => string,
   *   'options' => array,
   *   'correctOption' => int,
   *   'explanation_correct' => string,
   *   'explanation_incorrect' => string,
   *   'source_ref' => string
   * ]
   * @throws RuntimeException Si la API falla o retorna JSON inválido
   */
  public function generateQuestion(string $topic, int $difficulty): array
  {
    $prompt = $this->buildSystemPrompt($topic, $difficulty);
    $temperature = $this->getTemperature();

    try {
      $response = $this->client->post($this->apiEndpoint, [
        'query' => ['key' => $this->apiKey],
        'json' => [
          'contents' => [
            [
              'parts' => [
                ['text' => $prompt]
              ]
            ]
          ],
          'generationConfig' => [
            'temperature' => $temperature,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048
          ]
        ]
      ]);

      $responseBody = $response->getBody()->getContents();
      $body = json_decode($responseBody, true);

      if (!$body) {
        error_log("Invalid JSON from Gemini: " . substr($responseBody, 0, 500));
        throw new \RuntimeException('JSON inválido de respuesta de Gemini API');
      }

      if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Unexpected Gemini response structure: " . json_encode($body));
        throw new \RuntimeException('Respuesta inesperada de Gemini API');
      }

      $geminiText = $body['candidates'][0]['content']['parts'][0]['text'];
      return $this->parseGeminiResponse($geminiText);
    } catch (GuzzleException $e) {
      // Detectar específicamente el error de límite de uso (429 o RESOURCE_EXHAUSTED)
      $statusCode = $e->getCode();
      $message = $e->getMessage();

      if (
        $statusCode === 429 ||
        strpos($message, 'RESOURCE_EXHAUSTED') !== false ||
        strpos($message, 'quota') !== false ||
        strpos($message, 'rate limit') !== false
      ) {
        throw new \RuntimeException('RATE_LIMIT_EXCEEDED: ' . $message);
      }
      throw new \RuntimeException('Error en solicitud a Gemini API: ' . $e->getMessage());
    } catch (\Throwable $e) {
      throw new \RuntimeException('Error procesando respuesta de Gemini: ' . $e->getMessage());
    }
  }

  /**
   * Valida y evalúa una respuesta del jugador
   *
   * @param string $question Enunciado de la pregunta
   * @param string $answer Respuesta proporcionada por el jugador
   * @return array Estructura: ['isCorrect' => bool, 'explanation' => string]
   * @throws RuntimeException Si la API falla
   */
  public function validateAnswer(string $question, string $answer): array
  {
    $prompt = "Eres un experto oncólogo evaluando respuestas sobre Cáncer de Colon.
              Pregunta: $question
              Respuesta del estudiante: $answer

              Evalúa si la respuesta es correcta o incorrecta. Proporciona un JSON ESTRICTO sin markdown.
              Estructura: { 'isCorrect': bool, 'explanation': string (máx 100 caracteres) }";

    try {
      $response = $this->client->post($this->apiEndpoint, [
        'query' => ['key' => $this->apiKey],
        'json' => [
          'contents' => [
            [
              'parts' => [
                ['text' => $prompt]
              ]
            ]
          ],
          'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 500
          ]
        ]
      ]);

      $body = json_decode($response->getBody()->getContents(), true);

      if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        throw new \RuntimeException('Respuesta inesperada de Gemini API');
      }

      $geminiText = $body['candidates'][0]['content']['parts'][0]['text'];
      return $this->parseValidationResponse($geminiText);
    } catch (GuzzleException $e) {
      // Detectar específicamente el error de límite de uso (429 o RESOURCE_EXHAUSTED)
      $statusCode = $e->getCode();
      $message = $e->getMessage();

      if (
        $statusCode === 429 ||
        strpos($message, 'RESOURCE_EXHAUSTED') !== false ||
        strpos($message, 'quota') !== false ||
        strpos($message, 'rate limit') !== false
      ) {
        throw new \RuntimeException('RATE_LIMIT_EXCEEDED: ' . $message);
      }
      throw new \RuntimeException('Error en solicitud de validación a Gemini API: ' . $message);
    } catch (\Throwable $e) {
      throw new \RuntimeException('Error procesando validación de Gemini: ' . $e->getMessage());
    }
  }

  /**
   * Construye el prompt del sistema para generación de preguntas
   * Obtiene la template de BD y reemplaza placeholders
   *
   * @param string $topic Tema médico
   * @param int $difficulty Nivel (1-5)
   * @return string Prompt formateado
   */
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

  /**
   * Obtiene el template de prompt de la BD, con fallback a valor por defecto
   *
   * @return string Template del prompt
   */
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

  /**
   * Obtiene la temperatura de configuración, con fallback a 0.7
   *
   * @return float Temperatura para llamada a API
   */
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

  /**
   * Template por defecto (fallback si BD no disponible)
   *
   * @return string Template del prompt
   */
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

  /**
   * Parsea la respuesta JSON de Gemini para generación de preguntas
   *
   * @param string $geminiText Texto con JSON embebido
   * @return array Pregunta estructurada
   * @throws RuntimeException Si el JSON es inválido
   */
  private function parseGeminiResponse(string $geminiText): array
  {
    $json = null;

    if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $geminiText, $matches)) {
      $json = json_decode($matches[1], true);
    }

    if (!$json) {
      if (preg_match('/\{[\s\S]*\}/m', $geminiText, $matches)) {
        $json = json_decode($matches[0], true);
      }
    }

    if (!$json || !isset($json['statement']) || !isset($json['options'])) {
      throw new \RuntimeException('JSON de Gemini tiene estructura inválida');
    }

    // Validaciones
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
      'source_ref' => $json['source_ref'] ?? 'Gemini AI'
    ];
  }

  /**
   * Parsea la respuesta JSON de validación de respuestas
   *
   * @param string $geminiText Texto con JSON embebido
   * @return array ['isCorrect' => bool, 'explanation' => string]
   * @throws RuntimeException Si el JSON es inválido
   */
  private function parseValidationResponse(string $geminiText): array
  {
    preg_match('/\{[\s\S]*\}/m', $geminiText, $matches);

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
