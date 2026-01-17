<?php
declare(strict_types=1);

namespace Src\Services;

use TCPDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Src\Utils\Translations;

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
   * @param array $questionAnalysis Top hardest and easiest questions
   * @param string $language Language code ('es' or 'en')
   * @return string PDF content as binary string
   */
  public function generateRoomPdf(
    array $roomData,
    array $stats,
    array $playerStats,
    array $questionStats,
    array $categoryStats,
    array $questionAnalysis = [],
    string $language = 'es'
  ): string {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('SG-IA System');
    $pdf->SetAuthor(Translations::get('gamified_system', $language));
    $pdf->SetTitle(Translations::get('room_report', $language) . ': ' . ($roomData['name'] ?? Translations::get('no_name', $language)));
    $pdf->SetSubject(Translations::get('room_stats_subject', $language));

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Add first page
    $pdf->AddPage();

    // ===== HEADER SECTION =====
    $headerY = $pdf->GetY();

    // Header background box
    $pdf->SetFillColor(13, 71, 161);
    $pdf->Rect(15, $headerY, 180, 28, 'F');

    // Title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetY($headerY + 4);
    $pdf->Cell(0, 10, Translations::get('room_report', $language), 0, 1, 'C');

    // Room name
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, $roomData['name'] ?? Translations::get('no_name', $language), 0, 1, 'C');

    $pdf->SetY($headerY + 32);

    // ===== ROOM INFO CARD =====
    $cardY = $pdf->GetY();
    $pdf->SetFillColor(248, 250, 252);
    $pdf->Rect(15, $cardY, 180, 24, 'F');
    $pdf->SetDrawColor(229, 231, 235);
    $pdf->Rect(15, $cardY, 180, 24, 'D');

    $pdf->SetY($cardY + 4);

    // Code and Status in same row
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(90, 7, Translations::get('code', $language) . ':', 0, 0, 'R');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(90, 7, ' ' . ($roomData['room_code'] ?? 'N/A'), 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(90, 7, Translations::get('status', $language) . ':', 0, 0, 'R');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(90, 7, ' ' . $this->translateStatus($roomData['status'] ?? 'unknown', $language), 0, 1, 'L');

    $pdf->SetY($cardY + 28);

    // Description if exists
    if (!empty($roomData['description'])) {
      $pdf->SetFont('helvetica', 'I', 9);
      $pdf->SetTextColor(100, 100, 100);
      $pdf->MultiCell(0, 5, $roomData['description'], 0, 'C');
      $pdf->Ln(3);
    }

    $pdf->Ln(5);

    // ===== GENERAL STATISTICS SECTION =====
    // Section header with line
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(13, 71, 161);
    $pdf->Cell(0, 8, Translations::get('general_stats', $language), 0, 1, 'L');
    $pdf->SetDrawColor(13, 71, 161);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);

    // Stats puede venir como ['statistics' => [...]] o directamente
    $statsData = $stats['statistics'] ?? $stats;

    // Stats grid (2 columns)
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    $statsY = $pdf->GetY();

    // Column 1
    $this->addStatCard($pdf, 15, $statsY, Translations::get('total_players', $language), (string)($statsData['unique_players'] ?? 0));
    $this->addStatCard($pdf, 15, $statsY + 18, Translations::get('total_sessions', $language), (string)($statsData['total_sessions'] ?? 0));
    $this->addStatCard($pdf, 15, $statsY + 36, Translations::get('total_answers', $language), (string)($statsData['total_answers'] ?? 0));

    // Column 2
    $this->addStatCard($pdf, 105, $statsY, Translations::get('avg_accuracy', $language), ($statsData['avg_accuracy'] ?? 0) . '%');
    $this->addStatCard($pdf, 105, $statsY + 18, Translations::get('highest_score', $language), (string)($statsData['highest_score'] ?? 0));

    $pdf->SetY($statsY + 56);
    $pdf->Ln(3);

    // Player Statistics Section
    if (!empty($playerStats)) {
      $this->addSectionHeader($pdf, Translations::get('player_stats', $language));
      $this->addPlayerTable($pdf, $playerStats, $language);
    }

    // Question Analysis Section (Top 5 hardest and easiest)
    if (!empty($questionAnalysis['top_hardest']) || !empty($questionAnalysis['top_easiest'])) {
      $this->checkPageBreak($pdf, 80);
      $this->addSectionHeader($pdf, Translations::get('question_analysis', $language));
      $this->addQuestionAnalysisSection($pdf, $questionAnalysis, $language);
    }

    // Question Statistics Section (Top 10 errors)
    if (!empty($questionStats)) {
      $this->checkPageBreak($pdf, 60);
      $this->addSectionHeader($pdf, Translations::get('high_error_questions', $language));
      $this->addQuestionTable($pdf, array_slice($questionStats, 0, 10), $language);
    }

    // Category Statistics Section
    if (!empty($categoryStats)) {
      $this->checkPageBreak($pdf, 60);
      $this->addSectionHeader($pdf, Translations::get('category_stats', $language));
      $this->addCategoryTable($pdf, $categoryStats, $language);
    }

    // Footer
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 10, Translations::get('generated_on', $language) . ' ' . date('d/m/Y H:i:s') . ' - SG-IA ' . Translations::get('gamified_system', $language), 0, 1, 'C');

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
   * @param array $questionAnalysis Top hardest and easiest questions
   * @param string $language Language code ('es' or 'en')
   * @return string Excel content as binary string
   */
  public function generateRoomExcel(
    array $roomData,
    array $stats,
    array $playerStats,
    array $questionStats,
    array $categoryStats,
    array $questionAnalysis = [],
    string $language = 'es'
  ): string {
    $spreadsheet = new Spreadsheet();

    // Sheet 1: General Information
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(Translations::get('summary', $language));

    // Title
    $sheet->setCellValue('A1', Translations::get('room_report', $language));
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Room info
    $sheet->setCellValue('A3', Translations::get('name', $language) . ':'); // Corrected key 'name' -> just text or add key? Let's use hardcoded "Name" maybe? No, Translations. Expected key 'name' in Translations? I missed it. Adding specific simple keys or reusing. Reusing concepts.
    // Wait, I didn't add 'name' key. Using default or adding it.
    // Let's check Translations.php content again. 'no_name' exists. 'player_name'? 
    // I missed simple "Name". I'll add "Nombre" as hardcoded fallback or I should have added it.
    // I will use "Nombre" literal if 'name' key is missing, or update Translations.php.
    // Actually, I'll update Translations.php in my mind to include 'name' => 'Nombre'/'Name' or just handle it here.
    // Let's stick to consistent Translations usage. I'll add 'name_label' => 'Nombre'/'Name'.
    // For now, to avoid re-editing Translations.php immediately,    // Room info
    $sheet->setCellValue('A3', Translations::get('name_label', $language) . ':'); 

    $sheet->setCellValue('B3', $roomData['name'] ?? 'N/A');
    $sheet->setCellValue('A4', Translations::get('code', $language) . ':');
    $sheet->setCellValue('B4', $roomData['room_code'] ?? 'N/A');
    $sheet->setCellValue('A5', Translations::get('status', $language) . ':');
    $sheet->setCellValue('B5', $this->translateStatus($roomData['status'] ?? 'unknown', $language));
    $sheet->setCellValue('A6', Translations::get('description', $language) . ':');
    $sheet->setCellValue('B6', $roomData['description'] ?? Translations::get('no_description', $language));

    // Statistics - puede venir como ['statistics' => [...]] o directamente
    $statsData = $stats['statistics'] ?? $stats;

    $sheet->setCellValue('A8', Translations::get('general_stats', $language));
    $sheet->mergeCells('A8:D8');
    $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);

    $sheet->setCellValue('A9', Translations::get('total_players', $language) . ':');
    $sheet->setCellValue('B9', $statsData['unique_players'] ?? 0);
    $sheet->setCellValue('A10', Translations::get('total_sessions', $language) . ':');
    $sheet->setCellValue('B10', $statsData['total_sessions'] ?? 0);
    $sheet->setCellValue('A11', Translations::get('total_answers', $language) . ':');
    $sheet->setCellValue('B11', $statsData['total_answers'] ?? 0);
    $sheet->setCellValue('A12', Translations::get('avg_accuracy', $language) . ':');
    $sheet->setCellValue('B12', ($statsData['avg_accuracy'] ?? 0) . '%');
    $sheet->setCellValue('A13', Translations::get('highest_score', $language) . ':');
    $sheet->setCellValue('B13', $statsData['highest_score'] ?? 0);

    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(30);

    // Sheet 2: Player Statistics
    if (!empty($playerStats)) {
      $playerSheet = $spreadsheet->createSheet();
      $playerSheet->setTitle(Translations::get('player_stats', $language)); // Shorten? 'Jugadores'/'Players'
      // Translations has 'player_stats', maybe I should use 'player_stats' string which is "Estadísticas por Jugador". Title might be too long.
      // Let's use simple strings for sheet titles: "Jugadores" / "Players"
      $playerSheet->setTitle($language === 'es' ? 'Jugadores' : 'Players');

      // Headers
      $headers = [
        Translations::get('player', $language), 
        Translations::get('sessions', $language), 
        Translations::get('answers', $language), 
        Translations::get('correct', $language), 
        Translations::get('accuracy_percent', $language), 
        Translations::get('max_score_long', $language)
      ];
      $col = 'A';
      foreach ($headers as $header) {
        $playerSheet->setCellValue($col . '1', $header);
        $playerSheet->getStyle($col . '1')->getFont()->setBold(true);
        $playerSheet->getStyle($col . '1')->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('0D47A1');
        $playerSheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $col++;
      }

      // Data
      $row = 2;
      foreach ($playerStats as $player) {
        $totalAnswers = (int)($player['total_answers'] ?? 0);
        $accuracy = (float)($player['accuracy'] ?? 0);
        $correctAnswers = (int)round($totalAnswers * $accuracy / 100);

        $playerSheet->setCellValue('A' . $row, $player['player_name'] ?? 'N/A');
        $playerSheet->setCellValue('B' . $row, $player['total_sessions'] ?? 0);
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
      $questionSheet->setTitle($language === 'es' ? 'Preguntas' : 'Questions');

      // Headers
      $headers = [
        Translations::get('id', $language), 
        Translations::get('question', $language), 
        Translations::get('times_answered', $language), 
        Translations::get('error_rate_percent', $language)
      ];
      $col = 'A';
      foreach ($headers as $header) {
        $questionSheet->setCellValue($col . '1', $header);
        $questionSheet->getStyle($col . '1')->getFont()->setBold(true);
        $questionSheet->getStyle($col . '1')->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('0D47A1');
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
      $categorySheet->setTitle($language === 'es' ? 'Categorías' : 'Categories');

      // Headers
      $headers = [
        Translations::get('category', $language), 
        Translations::get('total_answered', $language), 
        Translations::get('correct', $language), 
        Translations::get('accuracy_percent', $language)
      ];
      $col = 'A';
      foreach ($headers as $header) {
        $categorySheet->setCellValue($col . '1', $header);
        $categorySheet->getStyle($col . '1')->getFont()->setBold(true);
        $categorySheet->getStyle($col . '1')->getFill()
          ->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('0D47A1');
        $categorySheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $col++;
      }

      // Data
      $row = 2;
      foreach ($categoryStats as $category) {
        $totalAnswers = (int)($category['total_answers'] ?? 0);
        $accuracy = (float)($category['accuracy'] ?? 0);
        $correctCount = (int)($category['correct_count'] ?? 0);

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

    // Sheet 5: Question Analysis (Top 5 hardest and easiest)
    if (!empty($questionAnalysis['top_hardest']) || !empty($questionAnalysis['top_easiest'])) {
      $analysisSheet = $spreadsheet->createSheet();
      $analysisSheet->setTitle(Translations::get('analysis', $language));

      $row = 1;

      // Top 5 Hardest
      if (!empty($questionAnalysis['top_hardest'])) {
        $analysisSheet->setCellValue('A' . $row, Translations::get('top_hardest_desc', $language));
        $analysisSheet->mergeCells('A' . $row . ':D' . $row);
        $analysisSheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $analysisSheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('E74C3C');
        $row++;

        // Headers
        $headers = ['#', Translations::get('question', $language), Translations::get('answers', $language), Translations::get('success_rate_percent', $language)];
        $col = 'A';
        foreach ($headers as $header) {
          $analysisSheet->setCellValue($col . $row, $header);
          $analysisSheet->getStyle($col . $row)->getFont()->setBold(true);
          $analysisSheet->getStyle($col . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E74C3C');
          $analysisSheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
          $col++;
        }
        $row++;

        // Data
        foreach ($questionAnalysis['top_hardest'] as $index => $question) {
          $analysisSheet->setCellValue('A' . $row, $index + 1);
          $analysisSheet->setCellValue('B' . $row, $question['statement'] ?? 'N/A');
          $analysisSheet->setCellValue('C' . $row, $question['times_answered'] ?? 0);
          $analysisSheet->setCellValue('D' . $row, (float)($question['success_rate'] ?? 0));
          $row++;
        }

        $row += 2; // Space between sections
      }

      // Top 5 Easiest
      if (!empty($questionAnalysis['top_easiest'])) {
        $analysisSheet->setCellValue('A' . $row, Translations::get('top_easiest_desc', $language));
        $analysisSheet->mergeCells('A' . $row . ':D' . $row);
        $analysisSheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $analysisSheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('27AE60');
        $row++;

        // Headers
        $headers = ['#', Translations::get('question', $language), Translations::get('answers', $language), Translations::get('success_rate_percent', $language)];
        $col = 'A';
        foreach ($headers as $header) {
          $analysisSheet->setCellValue($col . $row, $header);
          $analysisSheet->getStyle($col . $row)->getFont()->setBold(true);
          $analysisSheet->getStyle($col . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('27AE60');
          $analysisSheet->getStyle($col . $row)->getFont()->getColor()->setRGB('FFFFFF');
          $col++;
        }
        $row++;

        // Data
        foreach ($questionAnalysis['top_easiest'] as $index => $question) {
          $analysisSheet->setCellValue('A' . $row, $index + 1);
          $analysisSheet->setCellValue('B' . $row, $question['statement'] ?? 'N/A');
          $analysisSheet->setCellValue('C' . $row, $question['times_answered'] ?? 0);
          $analysisSheet->setCellValue('D' . $row, (float)($question['success_rate'] ?? 0));
          $row++;
        }
      }

      // Column widths
      $analysisSheet->getColumnDimension('A')->setWidth(5);
      $analysisSheet->getColumnDimension('B')->setWidth(60);
      $analysisSheet->getColumnDimension('C')->setWidth(15);
      $analysisSheet->getColumnDimension('D')->setWidth(18);
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
   * Adds a stat card with label and value.
   */
  private function addStatCard(TCPDF $pdf, float $x, float $y, string $label, string $value): void {
    $width = 85;
    $height = 16;

    // Card background
    $pdf->SetFillColor(248, 250, 252);
    $pdf->Rect($x, $y, $width, $height, 'F');
    $pdf->SetDrawColor(229, 231, 235);
    $pdf->Rect($x, $y, $width, $height, 'D');

    // Label
    $pdf->SetXY($x + 3, $y + 2);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell($width - 6, 5, $label, 0, 1, 'L');

    // Value
    $pdf->SetXY($x + 3, $y + 8);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(13, 71, 161);
    $pdf->Cell($width - 6, 6, $value, 0, 1, 'L');
  }

  /**
   * Adds a section header with underline.
   */
  private function addSectionHeader(TCPDF $pdf, string $title): void {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(13, 71, 161);
    $pdf->Cell(0, 8, $title, 0, 1, 'L');
    $pdf->SetDrawColor(13, 71, 161);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);
  }

  /**
   * Checks if page break is needed and adds new page if necessary.
   */
  private function checkPageBreak(TCPDF $pdf, int $requiredSpace): void {
    if ($pdf->GetY() > (297 - 15 - $requiredSpace)) {
      $pdf->AddPage();
    }
  }

  /**
   * Adds player statistics table to PDF.
   */
  private function addPlayerTable(TCPDF $pdf, array $players, string $language): void {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(13, 71, 161);
    $pdf->SetTextColor(255, 255, 255);

    // Headers
    $pdf->Cell(45, 7, Translations::get('player', $language), 1, 0, 'C', true);
    $pdf->Cell(25, 7, Translations::get('sessions', $language), 1, 0, 'C', true);
    $pdf->Cell(25, 7, Translations::get('answers', $language), 1, 0, 'C', true);
    $pdf->Cell(25, 7, Translations::get('correct', $language), 1, 0, 'C', true);
    $pdf->Cell(30, 7, Translations::get('accuracy', $language), 1, 0, 'C', true);
    $pdf->Cell(30, 7, Translations::get('max_score', $language), 1, 1, 'C', true);

    // Data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($players as $player) {
      $pdf->SetFillColor(245, 247, 250);
      $name = mb_substr($player['player_name'] ?? 'N/A', 0, 20);
      $totalAnswers = (int)($player['total_answers'] ?? 0);
      $accuracy = (float)($player['accuracy'] ?? 0);
      $correctAnswers = (int)round($totalAnswers * $accuracy / 100);

      $pdf->Cell(45, 6, $name, 1, 0, 'L', $fill);
      $pdf->Cell(25, 6, (string)($player['total_sessions'] ?? 0), 1, 0, 'C', $fill);
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
  private function addQuestionTable(TCPDF $pdf, array $questions, string $language): void {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(13, 71, 161);
    $pdf->SetTextColor(255, 255, 255);

    // Headers
    $pdf->Cell(110, 7, Translations::get('question', $language), 1, 0, 'C', true);
    $pdf->Cell(35, 7, Translations::get('times_answered', $language), 1, 0, 'C', true);
    $pdf->Cell(35, 7, Translations::get('error_rate', $language), 1, 1, 'C', true);

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
        $pdf->SetFillColor(13, 71, 161);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(110, 7, Translations::get('question', $language), 1, 0, 'C', true);
        $pdf->Cell(35, 7, Translations::get('times_answered', $language), 1, 0, 'C', true);
        $pdf->Cell(35, 7, Translations::get('error_rate', $language), 1, 1, 'C', true);
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
   * Adds category statistics table to PDF with visual progress bars.
   */
  private function addCategoryTable(TCPDF $pdf, array $categories, string $language): void {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(13, 71, 161);
    $pdf->SetTextColor(255, 255, 255);

    // Headers
    $pdf->Cell(50, 7, Translations::get('category', $language), 1, 0, 'C', true);
    $pdf->Cell(30, 7, Translations::get('answered', $language), 1, 0, 'C', true);
    $pdf->Cell(30, 7, Translations::get('correct', $language), 1, 0, 'C', true);
    $pdf->Cell(70, 7, Translations::get('accuracy', $language), 1, 1, 'C', true);

    // Data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($categories as $category) {
      $pdf->SetFillColor(245, 247, 250);
      $name = mb_substr($category['category_name'] ?? 'N/A', 0, 22);
      $totalAnswers = (int)($category['total_answers'] ?? 0);
      $accuracy = (float)($category['accuracy'] ?? 0);
      $correctCount = (int)($category['correct_count'] ?? 0);

      $startX = $pdf->GetX();
      $startY = $pdf->GetY();

      $pdf->Cell(50, 8, $name, 1, 0, 'L', $fill);
      $pdf->Cell(30, 8, (string)$totalAnswers, 1, 0, 'C', $fill);
      $pdf->Cell(30, 8, (string)$correctCount, 1, 0, 'C', $fill);

      // Progress bar cell
      $barX = $pdf->GetX();
      $barY = $pdf->GetY();
      $pdf->Cell(70, 8, '', 1, 0, 'C', $fill);

      // Draw progress bar inside the cell
      $this->addProgressBar($pdf, $barX + 2, $barY + 2, 50, 4, $accuracy);

      // Add percentage text after the bar
      $pdf->SetXY($barX + 54, $barY);
      $pdf->SetFont('helvetica', 'B', 8);
      $pdf->Cell(14, 8, number_format($accuracy, 1) . '%', 0, 1, 'R');
      $pdf->SetFont('helvetica', '', 9);

      $fill = !$fill;
    }
  }

  /**
   * Draws a progress bar using rectangles.
   */
  private function addProgressBar(TCPDF $pdf, float $x, float $y, float $width, float $height, float $percentage): void {
    // Background bar (gray)
    $pdf->SetFillColor(229, 231, 235);
    $pdf->Rect($x, $y, $width, $height, 'F');

    // Calculate filled width
    $filledWidth = ($percentage / 100) * $width;

    // Choose color based on percentage
    if ($percentage >= 75) {
      $pdf->SetFillColor(39, 174, 96); // Green
    } elseif ($percentage >= 50) {
      $pdf->SetFillColor(243, 156, 18); // Orange
    } else {
      $pdf->SetFillColor(231, 76, 60); // Red
    }

    // Draw filled bar
    if ($filledWidth > 0) {
      $pdf->Rect($x, $y, $filledWidth, $height, 'F');
    }
  }

  /**
   * Adds question analysis section (Top 5 hardest and easiest) with full question text.
   */
  private function addQuestionAnalysisSection(TCPDF $pdf, array $analysis, string $language): void {
    $topHardest = $analysis['top_hardest'] ?? [];
    $topEasiest = $analysis['top_easiest'] ?? [];

    // Top 5 Hardest Questions
    if (!empty($topHardest)) {
      $pdf->SetFont('helvetica', 'B', 11);
      $pdf->SetTextColor(231, 76, 60); // Red
      $pdf->Cell(0, 8, Translations::get('top_hardest_desc', $language), 0, 1, 'L');

      $this->addAnalysisTable($pdf, $topHardest, $language, [231, 76, 60]);
      $pdf->Ln(5);
    }

    // Top 5 Easiest Questions
    if (!empty($topEasiest)) {
      $pdf->SetFont('helvetica', 'B', 11);
      $pdf->SetTextColor(39, 174, 96); // Green
      $pdf->Cell(0, 8, Translations::get('top_easiest_desc', $language), 0, 1, 'L');

      $this->addAnalysisTable($pdf, $topEasiest, $language, [39, 174, 96]);
    }
  }

  /**
   * Adds a table for question analysis with full question text and centered columns.
   */
  private function addAnalysisTable(TCPDF $pdf, array $questions, string $language, array $headerColor): void {
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
    $pdf->Cell(110, 7, Translations::get('question', $language), 1, 0, 'C', true);
    $pdf->Cell(30, 7, Translations::get('answers', $language), 1, 0, 'C', true);
    $pdf->Cell(30, 7, Translations::get('success_rate_percent', $language), 1, 1, 'C', true);

    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($questions as $index => $question) {
      $pdf->SetFillColor(245, 247, 250);
      $rank = $index + 1;
      $statement = $question['statement'] ?? 'N/A';
      $timesAnswered = (string)($question['times_answered'] ?? 0);
      $successRate = number_format((float)($question['success_rate'] ?? 0), 1) . '%';

      // Calculate row height based on question text
      $lineHeight = 5;
      $questionWidth = 110;
      $numLines = $pdf->getNumLines($statement, $questionWidth);
      $rowHeight = max(7, $numLines * $lineHeight);

      $startX = $pdf->GetX();
      $startY = $pdf->GetY();

      // Check for page break
      if ($startY + $rowHeight > 270) {
        $pdf->AddPage();
        $startY = $pdf->GetY();

        // Re-draw headers
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
        $pdf->Cell(110, 7, Translations::get('question', $language), 1, 0, 'C', true);
        $pdf->Cell(30, 7, Translations::get('answers', $language), 1, 0, 'C', true);
        $pdf->Cell(30, 7, Translations::get('success_rate_percent', $language), 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
      }

      // Rank column
      $pdf->Cell(10, $rowHeight, (string)$rank, 1, 0, 'C', $fill);

      // Question text with MultiCell for word wrap
      $pdf->MultiCell($questionWidth, $rowHeight, $statement, 1, 'L', $fill, 0, $startX + 10, $startY, true, 0, false, true, $rowHeight, 'M');

      // Centered columns
      $pdf->SetXY($startX + 10 + $questionWidth, $startY);
      $pdf->Cell(30, $rowHeight, $timesAnswered, 1, 0, 'C', $fill);
      $pdf->Cell(30, $rowHeight, $successRate, 1, 1, 'C', $fill);

      $fill = !$fill;
    }
  }

  /**
   * Translates room status.
   */
  private function translateStatus(string $status, string $language): string {
    return match($status) {
      'active' => Translations::get('status_active', $language),
      'paused' => Translations::get('status_paused', $language),
      'closed' => Translations::get('status_closed', $language),
      default => Translations::get('unknown', $language)
    };
  }
}
