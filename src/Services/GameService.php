<?php
namespace Src\Services;

use Src\Repositories\Interfaces\{SessionRepositoryInterface,QuestionRepositoryInterface,AnswerRepositoryInterface};
use Src\Repositories\Interfaces\PlayerRepositoryInterface;
use Src\Services\AI\GenerativeAIInterface;

final class GameService {
  public function __construct(
    private SessionRepositoryInterface $sessions,
    private QuestionRepositoryInterface $questions,
    private AnswerRepositoryInterface $answers,
    private PlayerRepositoryInterface $players,
    private AIEngine $ai,
    private ?GenerativeAIInterface $generativeAi = null
  ) {}

  public function startSession(int $playerId, float $startDifficulty = 1.0): array {
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
   * Obtiene la siguiente pregunta para el jugador.
   * Si no hay preguntas existentes para esa dificultad, genera una con IA.
   *
   * @param int $categoryId ID de la categoría
   * @param int $difficulty Dificultad requerida (1-5)
   * @return array|null Array con datos de la pregunta o null si no se puede generar
   */
  public function nextQuestion(int $categoryId, int $difficulty): ?array {
    if ($difficulty < 1 || $difficulty > 5) {
      throw new \RangeError("Dificultad debe estar entre 1 y 5");
    }

    // Buscar pregunta existente y verificada
    $q = $this->questions->getActiveByDifficulty($categoryId, $difficulty);

    if ($q) {
      return [
        'id' => $q->id,
        'statement' => $q->statement,
        'difficulty' => $q->difficulty
      ];
    }

    // Si no hay preguntas y no tenemos IA configurada, retornar null
    if (!$this->generativeAi) {
      return null;
    }

    // Generar pregunta con IA
    return $this->generateAndSaveQuestion($categoryId, $difficulty);
  }

  /**
   * Genera una pregunta usando IA Generativa y la persiste en BD.
   *
   * @param int $categoryId ID de la categoría
   * @param int $difficulty Nivel de dificultad
   * @return array|null Datos de la pregunta generada o null si falla
   */
  private function generateAndSaveQuestion(int $categoryId, int $difficulty): ?array {
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

      // Guardar explicación
      $this->questions->saveExplanation(
        $questionId,
        $generatedData['explanation'],
        $generatedData['source_ref'] ?? null
      );

      return [
        'id' => $questionId,
        'statement' => $generatedData['statement'],
        'difficulty' => $difficulty,
        'is_ai_generated' => true,
        'admin_verified' => false
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
  private function getCategoryName(int $categoryId): string {
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
   */
  public function submitAnswer(
    int $sessionId,
    int $questionId,
    ?int $optionId,
    bool $isCorrect,
    float $timeSec
  ): array {
    // Obtener sesión y dificultad ACTUAL de BD
    $session = $this->sessions->get($sessionId);
    if (!$session) throw new \RuntimeException('Sesión no existe');

    $currentDiff = $session->currentDifficulty;

    // Registrar respuesta con dificultad actual
    $this->answers->register($sessionId, $questionId, $optionId, $isCorrect, $timeSec, $currentDiff);

    // Calcular cambios
    $delta = $this->ai->scoreDelta($isCorrect, $timeSec);
    $nextDiff = $this->ai->nextDifficulty($currentDiff, $isCorrect, $timeSec);

    // Actualizar sesión
    $score = $session->score + $delta;
    $lives = $isCorrect ? $session->lives : max(0, $session->lives - 1);
    $status = $lives === 0 ? 'game_over' : 'active';

    $this->sessions->updateProgress($sessionId, $score, $lives, $status, $nextDiff);

    return [
      'score' => $score,
      'lives' => $lives,
      'status' => $status,
      'next_difficulty' => $nextDiff
    ];
  }
}
