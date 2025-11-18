<?php
namespace Src\Services;

final class AIEngine {

  /**
   * Calcula la nueva dificultad basada en respuesta y tiempo.
   *
   * Regla de negocio:
   * - Respuesta correcta + tiempo rápido (< 3s) → +0.50
   * - Respuesta correcta + tiempo moderado (3-6s) → +0.25
   * - Respuesta correcta + tiempo lento (> 6s) → +0.10
   * - Respuesta incorrecta → -0.25
   *
   * Límites: [1.00, 5.00]
   */
  public function nextDifficulty(float $current, bool $correct, float $timeSec): float {
    if ($correct) {
      if ($timeSec < 3) {
        $delta = 0.50; // Respuesta muy rápida y correcta
      } elseif ($timeSec < 6) {
        $delta = 0.25; // Respuesta en tiempo moderado
      } else {
        $delta = 0.10; // Respuesta lenta pero correcta
      }
    } else {
      $delta = -0.25; // Respuesta incorrecta (siempre decremento)
    }

    $next = max(1.00, min(5.00, $current + $delta));
    return round($next, 2);
  }

  public function scoreDelta(bool $correct, float $timeSec): int {
    if (!$correct) return 0;

    // Mayor puntuación si se responde rápido
    if ($timeSec < 3) return 20;
    if ($timeSec < 6) return 15;
    return 10;
  }
}
