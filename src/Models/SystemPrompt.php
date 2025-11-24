<?php
declare(strict_types=1);

namespace Src\Models;

final class SystemPrompt {
  public function __construct(
    public readonly int $id,
    public readonly string $promptText,
    public readonly float $temperature,
    public readonly bool $isActive
  ) {}
}
