<?php
declare(strict_types=1);

namespace Src\Repositories\Implementations;

use Src\Database\Connection;
use Src\Models\SystemPrompt;
use Src\Repositories\Interfaces\SystemPromptRepositoryInterface;

final class SystemPromptRepository implements SystemPromptRepositoryInterface {
  public function __construct(private Connection $db) {}

  public function getActive(): ?SystemPrompt {
    $sql = "SELECT id, prompt_text, temperature, is_active
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
      (bool)$result['is_active']
    );
  }

  public function update(string $text, float $temperature): bool {
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
}
