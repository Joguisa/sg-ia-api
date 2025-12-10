<?php
namespace Src\Services\AI;

/**
 * GenerativeAIInterface
 *
 * Define el contrato para servicios de Inteligencia Artificial Generativa.
 * Actualmente preparado para implementación con Google Gemini API.
 *
 * Métodos:
 * - generateQuestion: Genera una pregunta automáticamente basada en tema y dificultad
 * - validateAnswer: Valida y evalúa la respuesta de un jugador
 */
interface GenerativeAIInterface {
  /**
   * Genera una pregunta automáticamente
   *
   * @param string $topic Tema o categoría de la pregunta
   * @param int $difficulty Nivel de dificultad (1-5)
   * @return array Estructura: [
   *   'statement' => string,
   *   'options' => array,
   *   'correctOption' => int,
   *   'explanation_correct' => string,   // Explicación para respuesta correcta
   *   'explanation_incorrect' => string, // Explicación para respuesta incorrecta
   *   'source_ref' => string
   * ]
   */
  public function generateQuestion(string $topic, int $difficulty): array;

  /**
   * Valida y evalúa una respuesta del jugador
   *
   * @param string $question Enunciado de la pregunta
   * @param string $answer Respuesta proporcionada por el jugador
   * @return array Estructura: ['isCorrect' => bool, 'explanation' => string]
   */
  public function validateAnswer(string $question, string $answer): array;
}
