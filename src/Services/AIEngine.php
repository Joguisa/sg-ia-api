<?php
namespace Src\Services;

final class AIEngine {
  // Regla inicial simple: subir dificultad si acierta rápido, bajar si falla o tarda.
  public function nextDifficulty(float $current, bool $correct, float $timeSec): float {
    $delta = $correct ? ($timeSec < 6 ? 0.25 : 0.10) : -0.25;
    $next = max(1.00, min(5.00, $current + $delta));
    return round($next, 2);
  }

  public function scoreDelta(bool $correct, float $timeSec): int {
    if (!$correct) return 0;
    return $timeSec < 6 ? 15 : 10;
  }
}
