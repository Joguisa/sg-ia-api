<?php

namespace Src\Services;

use Src\Repositories\Interfaces\{SessionRepositoryInterface, QuestionRepositoryInterface, AnswerRepositoryInterface};
use Src\Repositories\Interfaces\PlayerRepositoryInterface;
use Src\Repositories\Interfaces\QuestionBatchRepositoryInterface;
use Src\Repositories\Interfaces\RoomRepositoryInterface;
use Src\Services\AI\GenerativeAIInterface;

final class GameService
{
  public function __construct(
    private SessionRepositoryInterface $sessions,
    private QuestionRepositoryInterface $questions,
    private AnswerRepositoryInterface $answers,
    private PlayerRepositoryInterface $players,
    private AIEngine $ai,
    private ?GenerativeAIInterface $generativeAi = null,
    private ?QuestionBatchRepositoryInterface $batchRepo = null,
    private ?RoomRepositoryInterface $roomRepo = null
  ) {}

  /**
   * Cuenta preguntas respondidas por nivel de dificultad en una sesión
   * Mapea DECIMAL difficulty_at_answer a INT usando ROUND()
   *
   * @param int $sessionId ID de la sesión
   * @return array Array asociativo [nivel => cantidad] ej: [1 => 4, 2 => 3, 3 => 5]
   */
  private function getQuestionsPerLevel(int $sessionId): array
  {
    $pdo = $this->questions->getPdo();

    $sql = "SELECT
              ROUND(difficulty_at_answer) as level,
              COUNT(*) as count
            FROM player_answers
            WHERE session_id = :session_id
            GROUP BY ROUND(difficulty_at_answer)";

    $st = $pdo->prepare($sql);
    $st->execute([':session_id' => $sessionId]);
    $results = $st->fetchAll(\PDO::FETCH_ASSOC);

    $counts = [];
    foreach ($results as $row) {
      $counts[(int)$row['level']] = (int)$row['count'];
    }

    return $counts;
  }

  /**
   * Calcula qué niveles de dificultad están bloqueados para una sesión
   * Un nivel se bloquea cuando: preguntas_en_nivel >= FLOOR(max_questions / 5)
   *
   * @param int $sessionId ID de la sesión
   * @param int $maxQuestions Máximo de preguntas configurado
   * @return array Array de niveles bloqueados ej: [1, 3, 5]
   */
  private function getLockedLevels(int $sessionId, int $maxQuestions): array
  {
    $questionsPerLevel = $this->getQuestionsPerLevel($sessionId);
    $questionsPerLevelLimit = (int)floor($maxQuestions / 5);

    error_log("Questions per level: " . json_encode($questionsPerLevel));
    error_log("Questions per level limit: $questionsPerLevelLimit");

    $locked = [];
    foreach ($questionsPerLevel as $level => $count) {
      if ($count >= $questionsPerLevelLimit) {
        $locked[] = $level;
      }
    }

    return $locked;
  }

  /**
   * Obtiene el total de preguntas respondidas en una sesión
   *
   * @param int $sessionId ID de la sesión
   * @return int Cantidad total de preguntas respondidas
   */
  private function getTotalQuestionsAnswered(int $sessionId): int
  {
    $pdo = $this->questions->getPdo();

    $sql = "SELECT COUNT(*) as total
            FROM player_answers
            WHERE session_id = :session_id";

    $st = $pdo->prepare($sql);
    $st->execute([':session_id' => $sessionId]);
    $result = $st->fetch();

    return $result ? (int)$result['total'] : 0;
  }

  /**
   * Obtiene la configuración de máximo de preguntas por juego desde system_prompts
   *
   * @return int Máximo de preguntas (5-100), default 15
   */
  private function getMaxQuestionsConfig(): int
  {
    $pdo = $this->questions->getPdo();

    $sql = "SELECT max_questions_per_game
            FROM system_prompts
            WHERE is_active = 1
            LIMIT 1";

    $st = $pdo->prepare($sql);
    $st->execute();
    $result = $st->fetch();

    return $result ? (int)$result['max_questions_per_game'] : 15;
  }

  /**
   * Obtiene el servicio de IA generativa
   *
   * @return GenerativeAIInterface|null
   */
  public function getGenerativeAI(): ?GenerativeAIInterface
  {
    return $this->generativeAi;
  }

  /**
   * Inicia una nueva sesión de juego.
   *
   * @param int $playerId ID del jugador
   * @param float $startDifficulty Dificultad inicial (1.0-5.0)
   * @param string|null $roomCode Código de sala opcional
   * @return array Datos de la sesión iniciada
   */
  public function startSession(int $playerId, float $startDifficulty = 1.0, ?string $roomCode = null): array
  {
    if ($startDifficulty < 1.0 || $startDifficulty > 5.0) {
      throw new \RangeError("Dificultad inicial debe estar entre 1.0 y 5.0");
    }

    $player = $this->players->find($playerId);
    if (!$player) throw new \RuntimeException('Jugador no existe');

    $roomId = null;
    $roomData = null;

    // Si se proporciona código de sala, validar y obtener room_id
    if ($roomCode !== null && $this->roomRepo !== null) {
      $room = $this->roomRepo->getByCode(strtoupper($roomCode));

      if (!$room) {
        throw new \RuntimeException('Código de sala no válido');
      }

      if ($room->status !== 'active') {
        throw new \RuntimeException('La sala no está activa');
      }

      // Verificar que no exceda el máximo de jugadores
      $activePlayers = $this->roomRepo->countActivePlayers($room->id);
      if ($activePlayers >= $room->maxPlayers) {
        throw new \RuntimeException('La sala está llena');
      }

      $roomId = $room->id;
      $roomData = [
        'id' => $room->id,
        'room_code' => $room->roomCode,
        'name' => $room->name,
        'filter_categories' => $room->filterCategories,
        'filter_difficulties' => $room->filterDifficulties
      ];
    }

    $gs = $this->sessions->start($playerId, $startDifficulty, $roomId);

    $response = [
      'session_id' => $gs->id,
      'current_difficulty' => $gs->currentDifficulty,
      'status' => $gs->status
    ];

    if ($roomData !== null) {
      $response['room'] = $roomData;
    }

    return $response;
  }

  /**
   * Obtiene los filtros de sala para una sesión.
   * Retorna null si la sesión no tiene sala asociada.
   */
  private function getRoomFilters(int $sessionId): ?array
  {
    $session = $this->sessions->get($sessionId);
    if (!$session || !$session->roomId || !$this->roomRepo) {
      return null;
    }

    $room = $this->roomRepo->get($session->roomId);
    if (!$room) {
      return null;
    }

    return [
      'categories' => $room->filterCategories,
      'difficulties' => $room->filterDifficulties
    ];
  }

  /**
   * Obtiene la siguiente pregunta verificada para el jugador.
   * Solo retorna preguntas que han sido verificadas por el administrador.
   * Excluye preguntas ya respondidas en la sesión actual.
   * NO genera preguntas en tiempo real - el admin debe generarlas previamente.
   *
   * @param int|null $categoryId ID de la categoría (NULL = todas las categorías)
   * @param int $difficulty Dificultad requerida (1-5)
   * @param int $sessionId ID de la sesión actual
   * @return array|null Array con datos de la pregunta o null si no hay preguntas verificadas disponibles
   */
  public function nextQuestion(?int $categoryId, int $difficulty, int $sessionId): ?array
  {
    if ($difficulty < 1 || $difficulty > 5) {
      throw new \RangeError("Dificultad debe estar entre 1 y 5");
    }

    // SEGURIDAD: Validar que la sesión esté activa
    $session = $this->sessions->get($sessionId);
    if (!$session) {
      throw new \RuntimeException('Sesión no existe');
    }

    // Si la sesión está terminada (game_over o completed), no devolver más preguntas
    if ($session->status === 'game_over' || $session->status === 'completed') {
      return null;
    }

    // NUEVO: Verificar límite de preguntas
    $maxQuestions = $this->getMaxQuestionsConfig();
    $totalAnswered = $this->getTotalQuestionsAnswered($sessionId);

    if ($totalAnswered >= $maxQuestions) {
      // Marcar sesión como 'completed' (diferente a 'game_over')
      $this->sessions->updateProgress(
        $sessionId,
        $session->score,
        $session->lives,
        'completed',
        $session->currentDifficulty
      );
      return null;
    }

    // NUEVO: Calcular niveles bloqueados
    $lockedLevels = $this->getLockedLevels($sessionId, $maxQuestions);

    // Obtener filtros de sala si la sesión está asociada a una
    $roomFilters = $this->getRoomFilters($sessionId);

    // DEBUG: Log para entender qué está pasando
    $catLog = $categoryId ? "Category: $categoryId" : "Category: ALL";
    error_log("GameService::nextQuestion - Session: $sessionId, Difficulty: $difficulty, $catLog");
    error_log("Total answered: $totalAnswered / $maxQuestions");
    error_log("Locked levels: " . json_encode($lockedLevels));
    if ($roomFilters) {
      error_log("Room filters applied: " . json_encode($roomFilters));
    }

    // Buscar pregunta excluyendo respondidas Y niveles bloqueados, aplicando filtros de sala
    $q = $this->questions->getRandomExcludingAnsweredAndLockedLevels(
      $categoryId,
      $difficulty,
      $sessionId,
      $lockedLevels,
      $roomFilters
    );

    error_log("Question found: " . ($q ? "ID {$q->id}" : "NULL"));

    if ($q) {
      // Obtener las opciones
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
        ], $options),
        // NUEVO: Metadata de progreso para el frontend
        'progress' => [
          'total_answered' => $totalAnswered,
          'max_questions' => $maxQuestions,
          'locked_levels' => $lockedLevels
        ]
      ];
    }

    // No hay preguntas disponibles (sin niveles bloqueados o todas respondidas)
    return null;
  }

  /**
   * Genera una pregunta usando IA Generativa y la persiste en BD.
   *
   * @param int $categoryId ID de la categoría
   * @param int $difficulty Nivel de dificultad
   * @param int|null $batchId ID del batch al que pertenece la pregunta (opcional)
   * @param string $language Idioma de la pregunta ('es' o 'en')
   * @return array|null Datos de la pregunta generada o null si falla
   */
  public function generateAndSaveQuestion(int $categoryId, int $difficulty, ?int $batchId = null, string $language = 'es'): ?array
  {
    try {
      // Obtener nombre de la categoría (asumiendo que existe, si no lanzará excepción)
      $categoryName = $this->getCategoryName($categoryId);

      // Generar pregunta con IA
      $generatedData = $this->generativeAi->generateQuestion($categoryName, $difficulty, $language);

      // Capturar información del proveedor de IA
      $providerUsed = $this->generativeAi->getActiveProviderName();
      $hadFailover = $this->generativeAi->hadFailover();

      // Crear pregunta en BD marcada como generada por IA y no verificada por admin
      if ($batchId !== null) {
        // Usar createWithBatch si se proporciona batchId
        $questionId = $this->questions->createWithBatch([
          'statement' => $generatedData['statement'],
          'difficulty' => $difficulty,
          'category_id' => $categoryId,
          'source_id' => null
        ], $batchId);
      } else {
        // Usar create normal si no hay batchId
        $questionId = $this->questions->create([
          'statement' => $generatedData['statement'],
          'difficulty' => $difficulty,
          'category_id' => $categoryId,
          'is_active' => 1,
          'is_ai_generated' => true,
          'admin_verified' => false
        ]);
      }

      // Actualizar batch con proveedor usado (si se proporcionó batch y repositorio disponible)
      if ($batchId !== null && $this->batchRepo !== null && $providerUsed) {
        $this->batchRepo->updateAiProvider($batchId, $providerUsed);
      }

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
