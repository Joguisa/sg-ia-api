<?php

namespace Src\Services;

use Src\Repositories\Interfaces\{SessionRepositoryInterface, QuestionRepositoryInterface, AnswerRepositoryInterface};
use Src\Repositories\Interfaces\PlayerRepositoryInterface;
use Src\Services\AI\GenerativeAIInterface;

final class GameService
{
  public function __construct(
    private SessionRepositoryInterface $sessions,
    private QuestionRepositoryInterface $questions,
    private AnswerRepositoryInterface $answers,
    private PlayerRepositoryInterface $players,
    private AIEngine $ai,
    private ?GenerativeAIInterface $generativeAi = null
  ) {}

  public function startSession(int $playerId, float $startDifficulty = 1.0): array
  {
    if ($startDifficulty < 1.0 || $startDifficulty > 5.0) {
      throw new \RangeError("Dificultad inicial debe estar entre 1.0 y 5.0");
    }

    $player = $this->players->find($playerId);
    if (!$player) throw new \RuntimeException('Jugador no existe');

    $gs = $this->sessions->start($playerId, $startDifficulty);
    return [
      'session_id' => $gs->id,
      'current_difficulty' => $gs->currentDifficulty,
      'status' => $gs->status
    ];
  }

  /**
   * Obtiene la siguiente pregunta verificada para el jugador.
   * Solo retorna preguntas que han sido verificadas por el administrador.
   * Excluye preguntas ya respondidas en la sesión actual.
   * NO genera preguntas en tiempo real - el admin debe generarlas previamente.
   *
   * @param int $categoryId ID de la categoría
   * @param int $difficulty Dificultad requerida (1-5)
   * @param int $sessionId ID de la sesión actual
   * @return array|null Array con datos de la pregunta o null si no hay preguntas verificadas disponibles
   */
  public function nextQuestion(int $categoryId, int $difficulty, int $sessionId): ?array
  {
    if ($difficulty < 1 || $difficulty > 5) {
      throw new \RangeError("Dificultad debe estar entre 1 y 5");
    }

    // SEGURIDAD: Validar que la sesión esté activa
    $session = $this->sessions->get($sessionId);
    if (!$session) {
      throw new \RuntimeException('Sesión no existe');
    }

    // Si la sesión está terminada (game_over), no devolver más preguntas
    if ($session->status === 'game_over') {
      return null;
    }

    // Buscar pregunta existente y verificada excluyendo las ya respondidas
    $q = $this->questions->getRandomByDifficultyExcludingAnswered($categoryId, $difficulty, $sessionId);

    if ($q) {
      // AGREGAR: Obtener las opciones
      $options = $this->questions->getOptionsByQuestionId($q->id);

      return [
        'id' => $q->id,
        'statement' => $q->statement,
        'difficulty' => $q->difficulty,
        'category_id' => $q->categoryId,
        'options' => array_map(fn($opt) => [
          'id' => (int)$opt['id'],
          'text' => $opt['text'],
          'is_correct' => (bool)$opt['is_correct']
        ], $options)
      ];
    }

    // CAMBIO: No generar preguntas en tiempo real durante el juego
    // Solo el admin puede generar y debe verificarlas antes de que aparezcan
    return null;
  }

  /**
   * Genera una pregunta usando IA Generativa y la persiste en BD.
   *
   * @param int $categoryId ID de la categoría
   * @param int $difficulty Nivel de dificultad
   * @return array|null Datos de la pregunta generada o null si falla
   */
  public function generateAndSaveQuestion(int $categoryId, int $difficulty): ?array
  {
    try {
      // Obtener nombre de la categoría (asumiendo que existe, si no lanzará excepción)
      $categoryName = $this->getCategoryName($categoryId);

      // Generar pregunta con Gemini
      $generatedData = $this->generativeAi->generateQuestion($categoryName, $difficulty);

      // Crear pregunta en BD marcada como generada por IA y no verificada por admin
      $questionId = $this->questions->create([
        'statement' => $generatedData['statement'],
        'difficulty' => $difficulty,
        'category_id' => $categoryId,
        'is_active' => 1,
        'is_ai_generated' => true,
        'admin_verified' => false
      ]);

      // Guardar opciones
      $this->questions->saveOptions($questionId, $generatedData['options']);

      // Guardar ambas explicaciones
      $this->questions->saveExplanation(
        $questionId,
        $generatedData['explanation_correct'],
        $generatedData['source_ref'] ?? null,
        'correct'
      );

      $this->questions->saveExplanation(
        $questionId,
        $generatedData['explanation_incorrect'],
        $generatedData['source_ref'] ?? null,
        'incorrect'
      );

      // Obtener las opciones recién guardadas de la BD
      $savedOptions = $this->questions->getOptionsByQuestionId($questionId);

      return [
        'id' => $questionId,
        'statement' => $generatedData['statement'],
        'difficulty' => $difficulty,
        'category_id' => $categoryId,
        'is_ai_generated' => true,
        'admin_verified' => false,
        'options' => array_map(fn($opt) => [
          'id' => (int)$opt['id'],
          'text' => $opt['text'],
          'is_correct' => (bool)$opt['is_correct']
        ], $savedOptions)
      ];
    } catch (\Throwable $e) {
      // Log del error (implementar según tu sistema de logging)
      error_log("Error generando pregunta con IA: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Obtiene el nombre de una categoría por ID.
   * Simplificado: asume que existe la tabla y columna.
   *
   * @param int $categoryId
   * @return string Nombre de la categoría
   * @throws \RuntimeException Si la categoría no existe
   */
  private function getCategoryName(int $categoryId): string
  {
    // Esta es una implementación básica. Idealmente tendría un RepositoryInterface
    // para categories, pero por ahora usamos una query directa.
    $pdo = $this->questions->getPdo() ?? null;

    if (!$pdo) {
      throw new \RuntimeException("No se puede acceder a la base de datos para obtener la categoría");
    }

    $st = $pdo->prepare("SELECT name FROM question_categories WHERE id = :id");
    $st->execute([':id' => $categoryId]);
    $r = $st->fetch();

    if (!$r) {
      throw new \RuntimeException("Categoría $categoryId no existe");
    }

    return $r['name'];
  }

  /**
   * REFACTORIZADO:
   * - Obtiene dificultad ACTUAL de BD, no del cliente
   * - Actualiza current_difficulty en BD
   * - Valida que sesión exista
   * - Incluye feedback educativo (explicación y opción correcta)
   * - SEGURIDAD: Calcula is_correct en el servidor, no confía en el cliente
   */
  public function submitAnswer(
    int $sessionId,
    int $questionId,
    ?int $optionId,
    float $timeSec
  ): array {
    // Obtener sesión y dificultad ACTUAL de BD
    $session = $this->sessions->get($sessionId);
    if (!$session) throw new \RuntimeException('Sesión no existe');

    $currentDiff = $session->currentDifficulty;

    // SEGURIDAD: Calcular is_correct en el servidor comparando con BD
    $correctOptionId = $this->questions->getCorrectOptionId($questionId);
    $isCorrect = ($optionId !== null && $optionId === $correctOptionId);

    // Registrar respuesta con dificultad actual
    try {
      $this->answers->register($sessionId, $questionId, $optionId, $isCorrect, $timeSec, $currentDiff);
    } catch (\PDOException $e) {
      // Si es un error de clave duplicada, proporcionar mensaje más específico
      if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        throw new \RuntimeException("Esta pregunta ya fue respondida en esta sesión. Error de duplicación detectado.");
      }
      // Re-lanzar cualquier otro error de BD
      throw $e;
    }

    // Calcular cambios
    $delta = $this->ai->scoreDelta($isCorrect, $timeSec);
    $nextDiff = $this->ai->nextDifficulty($currentDiff, $isCorrect, $timeSec);

    // Actualizar sesión
    $score = $session->score + $delta;
    $lives = $isCorrect ? $session->lives : max(0, $session->lives - 1);
    $status = $lives === 0 ? 'game_over' : 'active';

    $this->sessions->updateProgress($sessionId, $score, $lives, $status, $nextDiff);

    // Obtener feedback educativo según si respondió correctamente o no
    $explanationType = $isCorrect ? 'correct' : 'incorrect';
    $explanation = $this->questions->getExplanationByType($questionId, $explanationType);

    // Fallback si no existe explicación del tipo solicitado
    if (!$explanation) {
      $explanation = $isCorrect
        ? "¡Respuesta correcta! Has demostrado comprensión de este concepto."
        : "Respuesta incorrecta. Revisa el concepto en la siguiente pregunta.";
    }

    return [
      'is_correct' => $isCorrect,
      'score' => $score,
      'lives' => $lives,
      'status' => $status,
      'next_difficulty' => $nextDiff,
      'explanation' => $explanation,
      'correct_option_id' => $correctOptionId
    ];
  }
}
