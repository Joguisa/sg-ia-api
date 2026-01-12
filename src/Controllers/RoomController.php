<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Services\RoomService;
use Src\Services\ExportService;
use Src\Services\ValidationService;
use Src\Utils\Response;
use Src\Utils\Translations;
use Src\Utils\LanguageDetector;

final class RoomController {
  public function __construct(
    private RoomService $roomService,
    private ExportService $exportService
  ) {}

  /**
   * Crea una nueva sala de juego.
   *
   * Endpoint: POST /admin/rooms
   * Body: {
   *   "name": "Sala 1",
   *   "description": "Descripción opcional",
   *   "filter_categories": [1, 2] | null,
   *   "filter_difficulties": [1, 2, 3] | null,
   *   "max_players": 50
   * }
   */
  public function create(array $params): void {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    ValidationService::requireFields($data, ['name']);

    $name = trim($data['name']);
    if (empty($name)) {
      Response::json(['ok' => false, 'error' => 'El nombre de la sala es requerido'], 400);
      return;
    }

    if (strlen($name) > 100) {
      Response::json(['ok' => false, 'error' => 'El nombre no puede exceder 100 caracteres'], 400);
      return;
    }

    // Obtener admin_id del JWT (asumiendo que está en $_REQUEST o headers)
    $adminId = (int)($_REQUEST['admin_id'] ?? $params['admin_id'] ?? 1);

    try {
      $room = $this->roomService->createRoom(
        $name,
        $adminId,
        $data['description'] ?? null,
        $data['filter_categories'] ?? null,
        $data['filter_difficulties'] ?? null,
        (int)($data['max_players'] ?? 50),
        $data['language'] ?? 'es'
      );

      Response::json([
        'ok' => true,
        'message' => 'Sala creada exitosamente',
        'room' => $room
      ], 201);
    } catch (\InvalidArgumentException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\RangeError $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al crear sala: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Lista todas las salas.
   *
   * Endpoint: GET /admin/rooms
   */
  public function list(array $params): void {
    try {
      $rooms = $this->roomService->getAllRooms();
      Response::json([
        'ok' => true,
        'rooms' => $rooms,
        'total' => count($rooms)
      ]);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al obtener salas: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene una sala por ID.
   *
   * Endpoint: GET /admin/rooms/{id}
   */
  public function get(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      $room = $this->roomService->getRoom($roomId);

      if (!$room) {
        Response::json(['ok' => false, 'error' => 'Sala no encontrada'], 404);
        return;
      }

      Response::json([
        'ok' => true,
        'room' => $room
      ]);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al obtener sala: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Actualiza una sala.
   *
   * Endpoint: PUT /admin/rooms/{id}
   * Body: {
   *   "name": "Nuevo nombre",
   *   "description": "Nueva descripción",
   *   "filter_categories": [1, 2],
   *   "filter_difficulties": [1, 2, 3],
   *   "max_players": 100
   * }
   */
  public function update(array $params): void {
    $lang = LanguageDetector::detect();
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_room_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data)) {
      Response::json(['ok' => false, 'error' => Translations::get('no_data_provided', $lang)], 400);
      return;
    }

    try {
      $success = $this->roomService->updateRoom($roomId, $data);

      if ($success) {
        $room = $this->roomService->getRoom($roomId);
        Response::json([
          'ok' => true,
          'message' => Translations::get('room_updated', $lang),
          'room' => $room
        ]);
      } else {
        Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_room', $lang)], 500);
      }
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_room', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Cambia el estado de una sala.
   *
   * Endpoint: PATCH /admin/rooms/{id}/status
   * Body: { "status": "active" | "paused" | "closed" }
   */
  public function updateStatus(array $params): void {
    $lang = LanguageDetector::detect();
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_room_id', $lang)], 400);
      return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!isset($data['status'])) {
      Response::json(['ok' => false, 'error' => Translations::get('status_required', $lang)], 400);
      return;
    }

    $validStatuses = ['active', 'paused', 'closed'];
    if (!in_array($data['status'], $validStatuses)) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_status', $lang)], 400);
      return;
    }

    try {
      $success = $this->roomService->updateRoomStatus($roomId, $data['status']);

      if ($success) {
        $room = $this->roomService->getRoom($roomId);
        Response::json([
          'ok' => true,
          'message' => Translations::get('room_status_updated', $lang),
          'room' => $room
        ]);
      } else {
        Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_status', $lang)], 500);
      }
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\InvalidArgumentException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_update_status', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Elimina una sala.
   *
   * Endpoint: DELETE /admin/rooms/{id}
   */
  public function delete(array $params): void {
    $lang = LanguageDetector::detect();
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_room_id', $lang)], 400);
      return;
    }

    try {
      $success = $this->roomService->deleteRoom($roomId);

      if ($success) {
        Response::json([
          'ok' => true,
          'message' => Translations::get('room_deleted', $lang)
        ]);
      } else {
        Response::json(['ok' => false, 'error' => Translations::get('failed_to_delete_room', $lang)], 500);
      }
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_delete_room', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene los jugadores activos en una sala.
   *
   * Endpoint: GET /admin/rooms/{id}/players
   */
  public function getPlayers(array $params): void {
    $lang = LanguageDetector::detect();
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_room_id', $lang)], 400);
      return;
    }

    try {
      $players = $this->roomService->getActivePlayers($roomId);

      Response::json([
        'ok' => true,
        'room_id' => $roomId,
        'players' => $players,
        'total' => count($players)
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_fetch_players', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Valida un código de sala (endpoint público).
   *
   * Endpoint: GET /rooms/validate/{code}
   */
  public function validateCode(array $params): void {
    $code = $params['code'] ?? '';

    if (empty($code) || strlen($code) !== 6) {
      Response::json(['ok' => false, 'error' => 'Código de sala inválido'], 400);
      return;
    }

    try {
      $result = $this->roomService->validateRoomCode(strtoupper($code));

      Response::json([
        'ok' => true,
        'valid' => $result['valid'],
        'reason' => $result['reason'] ?? null,
        'room' => $result['room'] ?? null
      ]);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al validar código: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene estadísticas de una sala.
   *
   * Endpoint: GET /admin/rooms/{id}/stats
   */
  public function getStats(array $params): void {
    $lang = LanguageDetector::detect();
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => Translations::get('invalid_room_id', $lang)], 400);
      return;
    }

    try {
      $stats = $this->roomService->getRoomStats($roomId);

      Response::json([
        'ok' => true,
        'data' => $stats
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => Translations::get('failed_to_fetch_stats', $lang) . ': ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene estadísticas de jugadores en la sala.
   *
   * Endpoint: GET /admin/rooms/{id}/stats/players
   */
  public function getPlayerStats(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      $stats = $this->roomService->getRoomPlayerStats($roomId);

      Response::json([
        'ok' => true,
        'room_id' => $roomId,
        'players' => $stats,
        'total' => count($stats)
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al obtener estadísticas: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene estadísticas de preguntas en la sala.
   *
   * Endpoint: GET /admin/rooms/{id}/stats/questions
   */
  public function getQuestionStats(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      $stats = $this->roomService->getRoomQuestionStats($roomId);

      Response::json([
        'ok' => true,
        'room_id' => $roomId,
        'questions' => $stats,
        'total' => count($stats)
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al obtener estadísticas: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene estadísticas por categoría en la sala.
   *
   * Endpoint: GET /admin/rooms/{id}/stats/categories
   */
  public function getCategoryStats(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      $stats = $this->roomService->getRoomCategoryStats($roomId);

      Response::json([
        'ok' => true,
        'room_id' => $roomId,
        'categories' => $stats,
        'total' => count($stats)
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al obtener estadísticas: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Obtiene el análisis de preguntas (Top 5 difíciles y fáciles).
   *
   * Endpoint: GET /admin/rooms/{id}/stats/analysis
   */
  public function getQuestionAnalysis(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      $topHardest = $this->roomService->getTopHardestQuestions($roomId, 5);
      $topEasiest = $this->roomService->getTopEasiestQuestions($roomId, 5);

      Response::json([
        'ok' => true,
        'room_id' => $roomId,
        'top_hardest' => $topHardest,
        'top_easiest' => $topEasiest
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al obtener análisis: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Exporta reporte de sala a PDF.
   *
   * Endpoint: GET /admin/rooms/{id}/export/pdf
   */
  public function exportPdf(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      // Get all data needed for the report
      $room = $this->roomService->getRoom($roomId);
      if (!$room) {
        Response::json(['ok' => false, 'error' => 'Sala no encontrada'], 404);
        return;
      }

      $stats = $this->roomService->getRoomStats($roomId);
      $playerStats = $this->roomService->getRoomPlayerStats($roomId);
      $questionStats = $this->roomService->getRoomQuestionStats($roomId);
      $categoryStats = $this->roomService->getRoomCategoryStats($roomId);

      // Get question analysis (Top 5 hardest and easiest)
      $questionAnalysis = [
        'top_hardest' => $this->roomService->getTopHardestQuestions($roomId),
        'top_easiest' => $this->roomService->getTopEasiestQuestions($roomId)
      ];

      // Generate PDF
      $language = $room['language'] ?? 'es';
      $pdfContent = $this->exportService->generateRoomPdf(
        $room,
        $stats,
        $playerStats,
        $questionStats,
        $categoryStats,
        $questionAnalysis,
        $language
      );

      // Send PDF response
      $filename = 'sala_' . ($room['room_code'] ?? 'reporte') . '_' . date('Ymd') . '.pdf';

      header('Content-Type: application/pdf');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Content-Length: ' . strlen($pdfContent));
      header('Cache-Control: private, max-age=0, must-revalidate');
      header('Pragma: public');

      echo $pdfContent;
      exit;

    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Exporta reporte de sala a Excel.
   *
   * Endpoint: GET /admin/rooms/{id}/export/excel
   */
  public function exportExcel(array $params): void {
    $roomId = (int)($params['id'] ?? 0);

    if ($roomId <= 0) {
      Response::json(['ok' => false, 'error' => 'ID de sala inválido'], 400);
      return;
    }

    try {
      // Get all data needed for the report
      $room = $this->roomService->getRoom($roomId);
      if (!$room) {
        Response::json(['ok' => false, 'error' => 'Sala no encontrada'], 404);
        return;
      }

      $stats = $this->roomService->getRoomStats($roomId);
      $playerStats = $this->roomService->getRoomPlayerStats($roomId);
      $questionStats = $this->roomService->getRoomQuestionStats($roomId);
      $categoryStats = $this->roomService->getRoomCategoryStats($roomId);

      // Get question analysis (Top 5 hardest and easiest)
      $questionAnalysis = [
        'top_hardest' => $this->roomService->getTopHardestQuestions($roomId),
        'top_easiest' => $this->roomService->getTopEasiestQuestions($roomId)
      ];

      // Generate Excel
      $language = $room['language'] ?? 'es';
      $excelContent = $this->exportService->generateRoomExcel(
        $room,
        $stats,
        $playerStats,
        $questionStats,
        $categoryStats,
        $questionAnalysis,
        $language
      );

      // Send Excel response
      $filename = 'sala_' . ($room['room_code'] ?? 'reporte') . '_' . date('Ymd') . '.xlsx';

      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Content-Length: ' . strlen($excelContent));
      header('Cache-Control: private, max-age=0, must-revalidate');
      header('Pragma: public');

      echo $excelContent;
      exit;

    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
      Response::json(['ok' => false, 'error' => 'Error al generar Excel: ' . $e->getMessage()], 500);
    }
  }
}
