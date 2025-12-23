<?php
declare(strict_types=1);

namespace Src\Repositories\Interfaces;

use Src\Models\SystemPrompt;

interface SystemPromptRepositoryInterface {
  public function getActive(): ?SystemPrompt;
  public function update(string $text, float $temperature): bool;
  public function updateWithProvider(string $text, float $temperature, string $preferredProvider): bool;
  public function getPreferredProvider(): string;
  public function updatePreferredProvider(string $provider): bool;
}
