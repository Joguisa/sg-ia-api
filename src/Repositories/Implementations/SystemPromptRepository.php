<?php

declare(strict_types=1);

namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Models\SystemPrompt;
use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;

final class SystemPromptRepository implements SystemPromptRepositoryInterface
{
  public function __construct(private Connection $db) {}

  public function getActive(): ?SystemPrompt
  {
    $sql = "SELECT id, prompt_text, temperature, is_active, preferred_ai_provider, max_questions_per_game
            FROM system_prompts
            WHERE is_active = 1
            LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute();
    $result = $st->fetch();

    if (!$result) {
      return null;
    }

    return new SystemPrompt(
      (int)$result['id'],
      $result['prompt_text'],
      (float)$result['temperature'],
      (bool)$result['is_active'],
      $result['preferred_ai_provider'] ?? 'auto',
      (int)($result['max_questions_per_game'] ?? 15)
    );
  }

  public function update(string $text, float $temperature): bool
  {
    $sql = "UPDATE system_prompts
            SET prompt_text = :text, temperature = :temperature, updated_at = CURRENT_TIMESTAMP
            WHERE is_active = 1
            LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);

    return $st->execute([
      ':text' => $text,
      ':temperature' => $temperature
    ]);
  }

  public function updateWithProvider(string $text, float $temperature, string $preferredProvider, int $maxQuestions = 15): bool
  {
    $validProviders = ['auto', 'gemini', 'groq', 'deepseek', 'fireworks'];

    if (!in_array($preferredProvider, $validProviders)) {
      throw new \InvalidArgumentException("Proveedor debe ser uno de: " . implode(', ', $validProviders));
    }

    if ($maxQuestions < 5 || $maxQuestions > 100) {
      throw new \InvalidArgumentException("Max questions debe estar entre 5 y 100");
    }

    $sql = "UPDATE system_prompts
            SET prompt_text = :text,
                temperature = :temperature,
                preferred_ai_provider = :provider,
                max_questions_per_game = :max_questions,
                updated_at = CURRENT_TIMESTAMP
            WHERE is_active = 1
            LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);

    return $st->execute([
      ':text' => $text,
      ':temperature' => $temperature,
      ':provider' => $preferredProvider,
      ':max_questions' => $maxQuestions
    ]);
  }

  public function getPreferredProvider(): string
  {
    $sql = "SELECT preferred_ai_provider FROM system_prompts WHERE is_active = 1 LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);
    $st->execute();
    $result = $st->fetch();

    return $result ? ($result['preferred_ai_provider'] ?? 'auto') : 'auto';
  }

  public function updatePreferredProvider(string $provider): bool
  {
    $validProviders = ['auto', 'gemini', 'groq', 'deepseek', 'fireworks'];

    if (!in_array($provider, $validProviders)) {
      throw new \InvalidArgumentException("Proveedor debe ser uno de: " . implode(', ', $validProviders));
    }

    $sql = "UPDATE system_prompts
            SET preferred_ai_provider = :provider, updated_at = CURRENT_TIMESTAMP
            WHERE is_active = 1
            LIMIT 1";
    $st = $this->db->pdo()->prepare($sql);

    return $st->execute([':provider' => $provider]);
  }
}
