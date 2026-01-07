<?php

declare(strict_types=1);

namespace Src\Services\AI;

use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;

final class MultiAIService implements GenerativeAIInterface
{
    private array $providers = [];
    private int $currentProviderIndex = 0;
    private array $providerErrors = [];

    private array $providerMap = [
        'gemini' => 'Src\\Services\\AI\\GeminiAIService',
        'groq' => 'Src\\Services\\AI\\GroqAIService',
        'deepseek' => 'Src\\Services\\AI\\DeepSeekAIService',
        'fireworks' => 'Src\\Services\\AI\\FireworksAIService'
    ];
    private string $preferredProvider = 'auto';
    private bool $wasFailover = false;

    public function __construct(
        array $providerConfigs,
        private ?SystemPromptRepositoryInterface $prompts = null,
        ?string $preferredProvider = null
    ) {
        $this->preferredProvider = $preferredProvider ?? 'auto';
        $this->initializeProviders($providerConfigs);

        if ($this->preferredProvider !== 'auto') {
            $this->prioritizeProvider($this->preferredProvider);
        }
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

    private function prioritizeProvider(string $providerName): void
    {
        $targetClass = $this->providerMap[$providerName] ?? null;
        if (!$targetClass) {
            return;
        }

        // Reorder array to put preferred provider first
        $reordered = [];
        foreach ($this->providers as $provider) {
            if (get_class($provider) === $targetClass) {
                array_unshift($reordered, $provider);
            } else {
                $reordered[] = $provider;
            }
        }
        $this->providers = $reordered;
        $this->currentProviderIndex = 0;
    }

    public function generateQuestion(string $topic, int $difficulty, string $language = 'es'): array
    {
        $initialIndex = $this->currentProviderIndex;
        $this->wasFailover = false;

        $attempts = 0;
        $maxAttempts = count($this->providers);

        while ($attempts < $maxAttempts) {
            $provider = $this->providers[$this->currentProviderIndex];
            $providerName = get_class($provider);

            try {
                $result = $provider->generateQuestion($topic, $difficulty, $language);

                // Detect if failover occurred
                if ($this->currentProviderIndex !== $initialIndex) {
                    $this->wasFailover = true;
                }

                return $result;
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

    public function getActiveProviderName(): string
    {
        $className = get_class($this->providers[$this->currentProviderIndex]);
        foreach ($this->providerMap as $name => $class) {
            if ($class === $className) {
                return $name;
            }
        }
        return 'unknown';
    }

    public function hadFailover(): bool
    {
        return $this->wasFailover;
    }

    public function getAvailableProviders(): array
    {
        $providers = [];
        foreach ($this->providers as $provider) {
            $className = get_class($provider);
            foreach ($this->providerMap as $name => $class) {
                if ($class === $className) {
                    $providers[] = ['name' => $name, 'available' => true];
                }
            }
        }
        return $providers;
    }
}
