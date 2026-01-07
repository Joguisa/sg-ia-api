<?php
declare(strict_types=1);

namespace Src\Services;

use TCPDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Service for exporting room reports to PDF and Excel formats.
 */
final class ExportService {

  /**
   * Generates a PDF report for a room.
   *
   * @param array $roomData Room information
   * @param array $stats General statistics
   * @param array $playerStats Player statistics
   * @param array $questionStats Question statistics
   * @param array $categoryStats Category statistics
   * @return string PDF content as binary string
   */
  public function generateRoomPdf(
    array $roomData,
    array $stats,
    array $playerStats,
    array $questionStats,
    array $categoryStats
  ): string {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('SG-IA System');
    $pdf->SetAuthor('Sistema Gamificado de Aprendizaje');
    $pdf->SetTitle('Reporte de Sala: ' . ($roomData['name'] ?? 'Sin nombre'));
    $pdf->SetSubject('Estadísticas de sala de juego');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Add first page
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(102, 126, 234);
    $pdf->Cell(0, 15, 'Reporte de Sala', 0, 1, 'C');

    // Room info
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, $roomData['name'] ?? 'Sin nombre', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, 'Código: ' . ($roomData['room_code'] ?? 'N/A'), 0, 1, 'C');
    $pdf->Cell(0, 8, 'Estado: ' . $this->translateStatus($roomData['status'] ?? 'unknown'), 0, 1, 'C');

    if (!empty($roomData['description'])) {
      $pdf->Ln(5);
      $pdf->SetFont('helvetica', 'I', 10);
      $pdf->MultiCell(0, 6, $roomData['description'], 0, 'C');
    }

    $pdf->Ln(10);

    // General Statistics Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(102, 126, 234);
    $pdf->Cell(0, 10, 'Estadísticas Generales', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);

    // Stats puede venir como ['statistics' => [...]] o directamente
    $statsData = $stats['statistics'] ?? $stats;

    $this->addStatRow($pdf, 'Total de Jugadores', $statsData['unique_players'] ?? 0);
    $this->addStatRow($pdf, 'Total de Sesiones', $statsData['total_sessions'] ?? 0);
    $this->addStatRow($pdf, 'Total de Respuestas', $statsData['total_answers'] ?? 0);
    $this->addStatRow($pdf, 'Precisión Promedio', ($statsData['avg_accuracy'] ?? 0) . '%');
    $this->addStatRow($pdf, 'Puntuación Máxima', $statsData['highest_score'] ?? 0);

    $pdf->Ln(10);

    // Player Statistics Section
    if (!empty($playerStats)) {
      $pdf->SetFont('helvetica', 'B', 14);
      $pdf->SetTextColor(102, 126, 234);
      $pdf->Cell(0, 10, 'Estadísticas por Jugador', 0, 1, 'L');

      $this->addPlayerTable($pdf, $playerStats);
      $pdf->Ln(5);
    }

    // Question Statistics Section (Top 10 errors)
    if (!empty($questionStats)) {
      // Check if we need a new page (if less than 60mm available)
      if ($pdf->GetY() > 220) {
        $pdf->AddPage();
      } else {
        $pdf->Ln(10);
      }

      $pdf->SetFont('helvetica', 'B', 14);
      $pdf->SetTextColor(102, 126, 234);
      $pdf->Cell(0, 10, 'Preguntas con Mayor Tasa de Error', 0, 1, 'L');

      $this->addQuestionTable($pdf, array_slice($questionStats, 0, 10));
      $pdf->Ln(5);
    }

    // Category Statistics Section
    if (!empty($categoryStats)) {
      // Check if we need a new page
      if ($pdf->GetY() > 220) {
        $pdf->AddPage();
      } else {
        $pdf->Ln(10);
      }

      $pdf->SetFont('helvetica', 'B', 14);
      $pdf->SetTextColor(102, 126, 234);
      $pdf->Cell(0, 10, 'Estadísticas por Categoría', 0, 1, 'L');

      $this->addCategoryTable($pdf, $categoryStats);
    }

    // Footer
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 10, 'Generado el ' . date('d/m/Y H:i:s') . ' - SG-IA Sistema Gamificado de Aprendizaje', 0, 1, 'C');

    return $pdf->Output('', 'S');
  }

  /**
   * Generates an Excel report for a room.
   *
   * @param array $roomData Room information
   * @param array $stats General statistics
   * @param array $playerStats Player statistics
   * @param array $questionStats Question statistics
   * @param array $categoryStats Category statistics
   * @return string Excel content as binary string
   */
  public function generateRoomExcel(
    array $roomData,
    array $stats,
    array $playerStats,
    array $questionStats,
    array $categoryStats
  ): string {
    $spreadsheet = new Spreadsheet();

    // Sheet 1: General Information
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Resumen');

    // Title
    $sheet->setCellValue('A1', 'Reporte de Sala');
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Room info
    $sheet->setCellValue('A3', 'Nombre:');
    $sheet->setCellValue('B3', $roomData['name'] ?? 'N/A');
    $sheet->setCellValue('A4', 'Código:');
    $sheet->setCellValue('B4', $roomData['room_code'] ?? 'N/A');
    $sheet->setCellValue('A5', 'Estado:');
    $sheet->setCellValue('B5', $this->translateStatus($roomData['status'] ?? 'unknown'));
    $sheet->setCellValue('A6', 'Descripción:');
    $sheet->setCellValue('B6', $roomData['description'] ?? 'Sin descripción');

    // Statistics - puede venir como ['statistics' => [...]] o directamente
    $statsData = $stats['statistics'] ?? $stats;

    $sheet->setCellValue('A8', 'Estadísticas Generales');
    $sheet->mergeCells('A8:D8');
    $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);

    $sheet->setCellValue('A9', 'Total Jugadores:');
    $sheet->setCellValue('B9', $statsData['unique_players'] ?? 0);
    $sheet->setCellValue('A10', 'Total Sesiones:');
    $sheet->setCellValue('B10', $statsData['total_sessions'] ?? 0);
    $sheet->setCellValue('A11', 'Total Respuestas:');
    $sheet->setCellValue('B11', $statsData['total_answers'] ?? 0);
    $sheet->setCellValue('A12', 'Precisión Promedio:');
    $sheet->setCellValue('B12', ($statsData['avg_accuracy'] ?? 0) . '%');
    $sheet->setCellValue('A13', 'Puntuación Máxima:');
    $sheet->setCellValue('B13', $statsData['highest_score'] ?? 0);

    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(30);

    // Sheet 2: Player Statistics
    if (!empty($playerStats)) {
      $playerSheet = $spreadsheet->createSheet();
      $playerSheet->setTitle('Jugadores');

      // Headers
      $headers = ['Jugador', 'Sesiones', 'Respuestas', 'Correctas', 'Precisión (%)', 'Puntuación Máx.'];
      $col = 'A';
      foreach ($headers as $header) {
        $playerSheet->setCellValue($col . '1', $header);
        $playerSheet->getStyle($col . '1')->getFont()->setBold(true);
        $playerSheet->getStyle($col . '1')->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('667EEA');
        $playerSheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $col++;
      }

      // Data
      $row = 2;
      foreach ($playerStats as $player) {
        $totalAnswers = (int)($player['total_answers'] ?? 0);
        $accuracy = (float)($player['accuracy_percent'] ?? 0);
        $correctAnswers = (int)round($totalAnswers * $accuracy / 100);

        $playerSheet->setCellValue('A' . $row, $player['player_name'] ?? 'N/A');
        $playerSheet->setCellValue('B' . $row, $player['sessions_played'] ?? 0);
        $playerSheet->setCellValue('C' . $row, $totalAnswers);
        $playerSheet->setCellValue('D' . $row, $correctAnswers);
        $playerSheet->setCellValue('E' . $row, $accuracy);
        $playerSheet->setCellValue('F' . $row, $player['high_score'] ?? 0);
        $row++;
      }

      // Auto-size columns
      foreach (range('A', 'F') as $col) {
        $playerSheet->getColumnDimension($col)->setAutoSize(true);
      }
    }

    // Sheet 3: Question Statistics
    if (!empty($questionStats)) {
      $questionSheet = $spreadsheet->createSheet();
      $questionSheet->setTitle('Preguntas');

      // Headers
      $headers = ['ID', 'Pregunta', 'Veces Respondida', 'Tasa de Error (%)'];
      $col = 'A';
      foreach ($headers as $header) {
        $questionSheet->setCellValue($col . '1', $header);
        $questionSheet->getStyle($col . '1')->getFont()->setBold(true);
        $questionSheet->getStyle($col . '1')->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('667EEA');
        $questionSheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $col++;
      }

      // Data
      $row = 2;
      foreach ($questionStats as $question) {
        $questionSheet->setCellValue('A' . $row, $question['question_id'] ?? 'N/A');
        $questionSheet->setCellValue('B' . $row, $question['statement'] ?? 'N/A');
        $questionSheet->setCellValue('C' . $row, $question['times_answered'] ?? 0);
        $questionSheet->setCellValue('D' . $row, $question['error_rate'] ?? 0);
        $row++;
      }

      $questionSheet->getColumnDimension('A')->setWidth(10);
      $questionSheet->getColumnDimension('B')->setWidth(60);
      $questionSheet->getColumnDimension('C')->setWidth(18);
      $questionSheet->getColumnDimension('D')->setWidth(18);
    }

    // Sheet 4: Category Statistics
    if (!empty($categoryStats)) {
      $categorySheet = $spreadsheet->createSheet();
      $categorySheet->setTitle('Categorías');

      // Headers
      $headers = ['Categoría', 'Total Respondidas', 'Correctas', 'Precisión (%)'];
      $col = 'A';
      foreach ($headers as $header) {
        $categorySheet->setCellValue($col . '1', $header);
        $categorySheet->getStyle($col . '1')->getFont()->setBold(true);
        $categorySheet->getStyle($col . '1')->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('667EEA');
        $categorySheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $col++;
      }

      // Data
      $row = 2;
      foreach ($categoryStats as $category) {
        $totalAnswers = (int)($category['total_answers'] ?? 0);
        $accuracy = (float)($category['accuracy_percent'] ?? 0);
        $correctCount = (int)round($totalAnswers * $accuracy / 100);

        $categorySheet->setCellValue('A' . $row, $category['category_name'] ?? 'N/A');
        $categorySheet->setCellValue('B' . $row, $totalAnswers);
        $categorySheet->setCellValue('C' . $row, $correctCount);
        $categorySheet->setCellValue('D' . $row, $accuracy);
        $row++;
      }

      foreach (range('A', 'D') as $col) {
        $categorySheet->getColumnDimension($col)->setAutoSize(true);
      }
    }

    // Set first sheet as active
    $spreadsheet->setActiveSheetIndex(0);

    // Write to string
    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $content = ob_get_clean();

    return $content;
  }

  /**
   * Adds a stat row to PDF.
   */
  private function addStatRow(TCPDF $pdf, string $label, $value): void {
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(70, 8, $label . ':', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(50, 8, (string)$value, 0, 1, 'L');
  }

  /**
   * Adds player statistics table to PDF.
   */
  private function addPlayerTable(TCPDF $pdf, array $players): void {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);

    // Headers
    $pdf->Cell(45, 7, 'Jugador', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Sesiones', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Respuestas', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Correctas', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Precisión', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Puntaje Máx.', 1, 1, 'C', true);

    // Data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($players as $player) {
      $pdf->SetFillColor(245, 247, 250);
      $name = mb_substr($player['player_name'] ?? 'N/A', 0, 20);
      $totalAnswers = (int)($player['total_answers'] ?? 0);
      $accuracy = (float)($player['accuracy_percent'] ?? 0);
      $correctAnswers = (int)round($totalAnswers * $accuracy / 100);

      $pdf->Cell(45, 6, $name, 1, 0, 'L', $fill);
      $pdf->Cell(25, 6, (string)($player['sessions_played'] ?? 0), 1, 0, 'C', $fill);
      $pdf->Cell(25, 6, (string)$totalAnswers, 1, 0, 'C', $fill);
      $pdf->Cell(25, 6, (string)$correctAnswers, 1, 0, 'C', $fill);
      $pdf->Cell(30, 6, $accuracy . '%', 1, 0, 'C', $fill);
      $pdf->Cell(30, 6, (string)($player['high_score'] ?? 0), 1, 1, 'C', $fill);
      $fill = !$fill;
    }
  }

  /**
   * Adds question statistics table to PDF.
   * Uses MultiCell to display full question text with word wrap.
   */
  private function addQuestionTable(TCPDF $pdf, array $questions): void {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);

    // Headers
    $pdf->Cell(110, 7, 'Pregunta', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Veces Respondida', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Tasa Error', 1, 1, 'C', true);

    // Data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($questions as $question) {
      $pdf->SetFillColor(245, 247, 250);
      $statement = $question['statement'] ?? 'N/A';
      $timesAnswered = (string)($question['times_answered'] ?? 0);
      $errorRate = ($question['error_rate'] ?? 0) . '%';

      // Calculate row height based on question text length
      $lineHeight = 5;
      $questionWidth = 110;
      $numLines = $pdf->getNumLines($statement, $questionWidth);
      $rowHeight = max(6, $numLines * $lineHeight);

      // Save current position
      $startX = $pdf->GetX();
      $startY = $pdf->GetY();

      // Check if we need a page break
      if ($startY + $rowHeight > 270) {
        $pdf->AddPage();
        $startY = $pdf->GetY();

        // Re-draw headers on new page
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(102, 126, 234);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(110, 7, 'Pregunta', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Veces Respondida', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Tasa Error', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $startY = $pdf->GetY();
      }

      // Draw question text with MultiCell (allows word wrap)
      $pdf->MultiCell($questionWidth, $rowHeight, $statement, 1, 'L', $fill, 0, $startX, $startY, true, 0, false, true, $rowHeight, 'M');

      // Draw other columns at the same row
      $pdf->SetXY($startX + $questionWidth, $startY);
      $pdf->Cell(35, $rowHeight, $timesAnswered, 1, 0, 'C', $fill);
      $pdf->Cell(35, $rowHeight, $errorRate, 1, 1, 'C', $fill);

      $fill = !$fill;
    }
  }

  /**
   * Adds category statistics table to PDF.
   */
  private function addCategoryTable(TCPDF $pdf, array $categories): void {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);

    // Headers
    $pdf->Cell(55, 7, 'Categoría', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Total Respondidas', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Correctas', 1, 0, 'C', true);
    $pdf->Cell(45, 7, 'Precisión', 1, 1, 'C', true);

    // Data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($categories as $category) {
      $pdf->SetFillColor(245, 247, 250);
      $name = mb_substr($category['category_name'] ?? 'N/A', 0, 25);
      $totalAnswers = (int)($category['total_answers'] ?? 0);
      $accuracy = (float)($category['accuracy_percent'] ?? 0);
      $correctCount = (int)round($totalAnswers * $accuracy / 100);

      $pdf->Cell(55, 6, $name, 1, 0, 'L', $fill);
      $pdf->Cell(40, 6, (string)$totalAnswers, 1, 0, 'C', $fill);
      $pdf->Cell(40, 6, (string)$correctCount, 1, 0, 'C', $fill);
      $pdf->Cell(45, 6, $accuracy . '%', 1, 1, 'C', $fill);
      $fill = !$fill;
    }
  }

  /**
   * Translates room status to Spanish.
   */
  private function translateStatus(string $status): string {
    return match($status) {
      'active' => 'Activa',
      'paused' => 'Pausada',
      'closed' => 'Cerrada',
      default => 'Desconocido'
    };
  }
}
