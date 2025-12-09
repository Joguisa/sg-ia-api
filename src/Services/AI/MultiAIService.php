<?php

declare(strict_types=1);

namespace Src\Services\AI;

use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;

final class MultiAIService implements GenerativeAIInterface
{
    private array $providers = [];
    private int $currentProviderIndex = 0;
    private array $providerErrors = [];

    public function __construct(
        array $providerConfigs,
        private ?SystemPromptRepositoryInterface $prompts = null
    ) {
        $this->initializeProviders($providerConfigs);
    }

    private function initializeProviders(array $configs): void
    {
        // Orden de preferencia: Gemini -> Groq -> DeepSeek -> Fireworks
        if ($configs['gemini']['enabled'] && !empty($configs['gemini']['api_key'])) {
            $this->providers[] = new GeminiAIService($configs['gemini']['api_key'], $this->prompts);
        }

        if ($configs['groq']['enabled'] && !empty($configs['groq']['api_key'])) {
            $this->providers[] = new GroqAIService($configs['groq']['api_key'], $this->prompts);
        }
        // Agregar DeepSeekAIService y FireworksAIService similares
        if ($configs['deepseek']['enabled'] && !empty($configs['deepseek']['api_key'])) {
            $this->providers[] = new DeepSeekAIService($configs['deepseek']['api_key'], $this->prompts);
        }

        // FireworksAIService
        if ($configs['fireworks']['enabled'] && !empty($configs['fireworks']['api_key'])) {
            $this->providers[] = new FireworksAIService($configs['fireworks']['api_key'], $this->prompts);
        }

    }

    public function generateQuestion(string $topic, int $difficulty): array
    {
        $attempts = 0;
        $maxAttempts = count($this->providers);

        while ($attempts < $maxAttempts) {
            $provider = $this->providers[$this->currentProviderIndex];
            $providerName = get_class($provider);

            try {
                return $provider->generateQuestion($topic, $difficulty);
            } catch (\RuntimeException $e) {
                $this->providerErrors[$providerName] = $e->getMessage();
                error_log("Provider {$providerName} failed: " . $e->getMessage());

                // Si es límite de uso, rotar al siguiente proveedor
                if (
                    strpos($e->getMessage(), 'RATE_LIMIT_EXCEEDED') !== false ||
                    strpos($e->getMessage(), '429') !== false
                ) {
                    $this->rotateProvider();
                    $attempts++;
                    continue;
                }

                throw $e; // Otros errores se propagan
            }
        }

        throw new \RuntimeException('Todos los proveedores de IA han fallado: ' . json_encode($this->providerErrors));
    }

    public function validateAnswer(string $question, string $answer): array
    {
        $attempts = 0;
        $maxAttempts = count($this->providers);

        while ($attempts < $maxAttempts) {
            $provider = $this->providers[$this->currentProviderIndex];

            try {
                return $provider->validateAnswer($question, $answer);
            } catch (\RuntimeException $e) {
                if (strpos($e->getMessage(), 'RATE_LIMIT_EXCEEDED') !== false) {
                    $this->rotateProvider();
                    $attempts++;
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('Todos los proveedores fallaron en validación');
    }

    private function rotateProvider(): void
    {
        $this->currentProviderIndex = ($this->currentProviderIndex + 1) % count($this->providers);
        error_log("Rotando a proveedor índice: {$this->currentProviderIndex}");
    }

    public function getActiveProvider(): string
    {
        if (empty($this->providers)) {
            return 'none';
        }
        return get_class($this->providers[$this->currentProviderIndex]);
    }
}
