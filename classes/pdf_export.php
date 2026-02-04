<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PDF export functionality for question bank report.
 *
 * @package    report_questionbank
 * @copyright  2025 Awakelab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_questionbank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

/**
 * Class for exporting questions to PDF format.
 */
class pdf_export extends \pdf {
    
    /**
     * Export questions to PDF file.
     *
     * @param array $questions Array of question objects
     * @param string $coursename Name of the course for filename
    * @param int $finalexamcategoryid Category ID to use as final exam unit
     */
    public function export_questions($questions, $coursename, $finalexamcategoryid = 0) {
        global $DB;
        
        // Clean the course name for use in filename.
        $filename = clean_filename('questions_' . $coursename . '_' . date('Y-m-d')) . '.pdf';
        
        // Set document information
        $this->SetCreator('Moodle Question Bank Report');
        $this->SetAuthor('Moodle');
        $this->SetTitle('Informe del Banco de Preguntas - ' . $coursename);
        $this->SetSubject('Informe del Banco de Preguntas');
        
        // Set default monospaced font
        $this->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins - increased padding
        $this->SetMargins(30, 30, 30);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(18);
        
        // Set auto page breaks
        $this->SetAutoPageBreak(true, 30);
        
        // 1. Cover page - Title
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 32);
        $this->Ln(80);
        $this->Cell(0, 20, 'Informe del Banco de Preguntas', 0, 1, 'C');
        $this->SetFont('helvetica', '', 18);
        $this->Ln(10);
        $this->Cell(0, 15, $this->clean_text($coursename), 0, 1, 'C');
        
        // 2. EXAMEN FINAL cover page and content - always generate, source depends on selection
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 20);
        $this->Ln(80);
        $this->Cell(0, 15, 'EXAMEN FINAL', 0, 1, 'C');
        
        // 2b. EXAMEN FINAL content page
        $this->AddPage();
        
        // Description text
        $this->SetFont('helvetica', '', 11);
        $description = 'Las preguntas del curso se estructuran en bancos de preguntas que contienen un número superior al de ítems incluidos en cada test. Esta organización tiene como objetivo que, en caso de que el alumnado rehaga el cuestionario, se le presenten preguntas diferentes en cada intento. Asimismo, de estos mismos bancos de preguntas se extraen los ítems que conforman el test final del curso.';
        $this->MultiCell(0, 5, $description, 0, 'L');
        $this->Ln(10);
        
        // Determine which questions to sample for final exam
        $sample_questions_pool = array();
        if ($finalexamcategoryid > 0) {
            // Use only questions from the selected final exam category
            foreach ($questions as $question) {
                if ($question->questioncategoryid == $finalexamcategoryid) {
                    $sample_questions_pool[] = $question;
                }
            }
        } else {
            // Use all questions if no specific category selected
            $sample_questions_pool = $questions;
        }
        
        // Random sample of 5-10 questions (handle empty pool)
        if (empty($sample_questions_pool)) {
            $sample_questions = array();
            $sample_count = 0;
        } else {
            $sample_count = min(max(5, min(10, count($sample_questions_pool))), count($sample_questions_pool));
            $sample_questions = $this->get_random_questions($sample_questions_pool, $sample_count);
        }
        
        // Sample header with same style as unit headers (flush with question background, padded, multi-line)
        if ($sample_count > 0) {
            $headerwidth = $this->getPageWidth() - $this->lMargin - $this->rMargin;
            $this->SetX($this->lMargin);
            $this->SetFont('helvetica', 'B', 14);
            $this->SetFillColor(15, 108, 191);
            $this->SetTextColor(255, 255, 255);
            $this->setCellPaddings(8, 4, 8, 4);
            $this->MultiCell($headerwidth, 0, 'Muestra de ' . $sample_count . ' preguntas del banco:', 0, 'L', true, 1, '', '', true, 0, false, true, 0, 'T');
            $this->setCellPaddings(0, 0, 0, 0);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(10);
            
            foreach ($sample_questions as $question) {
                $this->display_question($question, $DB);
            }
        } else {
            // No questions available for sampling
            $this->SetFont('helvetica', '', 11);
            $this->MultiCell(0, 5, 'No hay preguntas disponibles en la unidad seleccionada.', 0, 'L');
            $this->Ln(10);
        }
        
        // 3. EVALUACIÓN PARCIAL cover page
        $this->AddPage();
        $this->SetFont('helvetica', 'B', 20);
        $this->Ln(80);
        $this->Cell(0, 15, 'EVALUACIÓN PARCIAL', 0, 1, 'C');
        
        // 4. Questions grouped by unit
        // Group questions by category
        $grouped_questions = array();
        foreach ($questions as $question) {
            // Skip the selected final exam category - it should not appear in units section
            if ($finalexamcategoryid > 0 && $question->questioncategoryid == $finalexamcategoryid) {
                continue;
            }
            $category = $question->categoryname;
            if (!isset($grouped_questions[$category])) {
                $grouped_questions[$category] = array();
            }
            $grouped_questions[$category][] = $question;
        }
        
        // Display each unit with max 5 questions
        $unit_number = 1;
        foreach ($grouped_questions as $unit => $unit_questions) {
            $this->AddPage();
            
            // Unit header flush with question background (padded, multi-line)
            $headerwidth = $this->getPageWidth() - $this->lMargin - $this->rMargin;
            $this->SetX($this->lMargin);
            $this->SetFont('helvetica', 'B', 14);
            $this->SetFillColor(15, 108, 191);
            $this->SetTextColor(255, 255, 255);
            $this->setCellPaddings(8, 4, 8, 4);
            $this->MultiCell($headerwidth, 0, 'Unidad ' . $unit_number . ': ' . $this->clean_text($unit), 0, 'L', true, 1, '', '', true, 0, false, true, 0, 'T');
            $this->setCellPaddings(0, 0, 0, 0);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(10);
            $unit_number++;
            
            // Display max 5 questions from this unit
            $questions_count = min(5, count($unit_questions));
            for ($i = 0; $i < $questions_count; $i++) {
                $this->display_question($unit_questions[$i], $DB);
            }
        }
        
        // Output PDF
        $this->Output($filename, 'D');
    }
    
    /**
     * Display a single question with its answers.
     *
     * @param object $question Question object
     * @param object $DB Database object
     */
    private function display_question($question, $DB) {
        // Get answers for this question
        $answers = $DB->get_records('question_answers', array('question' => $question->id));

        // Layout settings for breathing room
        $block_left = $this->lMargin;
        $block_right = $this->rMargin;
        $padding_left = 12;   // slightly tighter left/right padding
        $padding_right = 12;
        $padding_top = 10;    // reduced top padding
        $padding_bottom = 12; // reduced bottom padding
        $content_width = $this->getPageWidth() - $block_left - $block_right - $padding_left - $padding_right;

        // Check if we need a page break FIRST by measuring
        $test_y = $this->GetY();
        $this->startTransaction();
        
        // Render a test version to measure
        $this->SetXY($block_left + $padding_left, $test_y + $padding_top);
        $this->SetFont('helvetica', 'B', 12);
        $this->MultiCell($content_width, 6, $this->clean_text($question->questiontext), 0, 'L');
        $this->Ln(8);
        
        if (!empty($answers)) {
            $this->SetFont('helvetica', '', 10);
            foreach ($answers as $answer) {
                $correct = ($answer->fraction > 0) ? ' [CORRECTA]' : '';
                $this->SetX($block_left + $padding_left);
                $this->Cell(10, 6, '', 0, 0);
                $this->MultiCell($content_width - 10, 6, $this->clean_text($answer->answer) . $correct, 0, 'L');
            }
        }
        
        $measured_end_y = $this->GetY();
        $measured_page = $this->getPage();
        $this->rollbackTransaction(true);
        
        // Calculate needed height
        $needed_height = ($measured_end_y - $test_y) + $padding_bottom;
        $available_space = $this->getPageHeight() - $test_y - $this->bMargin;
        
        // Add page if needed
        if ($measured_page !== $this->getPage() || $needed_height > $available_space) {
            $this->AddPage();
        }
        
        // Now render for real
        $start_y = $this->GetY();
        $start_x = $block_left + $padding_left;
        
        // Position and render question text
        $this->SetXY($start_x, $start_y + $padding_top);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(0, 0, 0); // black question text
        $this->MultiCell($content_width, 6, $this->clean_text($question->questiontext), 0, 'L');
        $this->Ln(8);
        
        // Render answers
        if (!empty($answers)) {
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('helvetica', '', 10);
            foreach ($answers as $answer) {
                $correct = ($answer->fraction > 0) ? ' [CORRECTA]' : '';
                $this->SetX($start_x);
                $circle_y = $this->GetY() + 3;
                $this->Circle($start_x + 3, $circle_y, 1.8, 0, 360, 'D');
                $this->Cell(10, 6, '', 0, 0);
                $this->MultiCell($content_width - 10, 6, $this->clean_text($answer->answer) . $correct, 0, 'L');
            }
        }
        
        $end_y = $this->GetY();
        $actual_height = ($end_y - $start_y) + $padding_bottom;
        
        // Draw OPAQUE background rectangle behind the content
        $block_width = $this->getPageWidth() - $block_left - $block_right;
        $this->SetFillColor(220, 235, 250);
        $this->Rect($block_left, $start_y, $block_width, $actual_height, 'F');
        
        // Re-render text on top of background
        $this->SetXY($start_x, $start_y + $padding_top);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell($content_width, 6, $this->clean_text($question->questiontext), 0, 'L');
        $this->Ln(8);
        
        if (!empty($answers)) {
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('helvetica', '', 10);
            foreach ($answers as $answer) {
                $correct = ($answer->fraction > 0) ? ' [CORRECTA]' : '';
                $this->SetX($start_x);
                $circle_y = $this->GetY() + 3;
                $this->Circle($start_x + 3, $circle_y, 1.8, 0, 360, 'D');
                $this->Cell(10, 6, '', 0, 0);
                $this->MultiCell($content_width - 10, 6, $this->clean_text($answer->answer) . $correct, 0, 'L');
            }
        }
        
        // Move cursor to end of block
        $this->SetY($start_y + $actual_height);
        $this->Ln(10);
    }
    
    /**
     * Get a random selection of questions.
     *
     * @param array $questions Array of all questions
     * @param int $count Number of questions to select
     * @return array Random selection of questions
     */
    private function get_random_questions($questions, $count) {
        $keys = array_rand($questions, $count);
        
        // array_rand returns a single key if count is 1
        if ($count === 1) {
            return array($questions[$keys]);
        }
        
        $result = array();
        foreach ($keys as $key) {
            $result[] = $questions[$key];
        }
        return $result;
    }
    
    /**
     * Clean text for PDF export (remove HTML tags and extra whitespace).
     *
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    private function clean_text($text) {
        // Remove HTML tags
        $text = strip_tags($text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim whitespace
        $text = trim($text);
        return $text;
    }
    
    /**
     * Page header.
     */
    public function Header() {
        // Draw page border (4px blue outline) inset for larger margin
        $this->SetLineStyle(array('width' => 1.5, 'color' => array(15, 108, 191)));
        $this->Rect(10, 10, $this->getPageWidth() - 20, $this->getPageHeight() - 20, 'D');
    }
    
    /**
     * Page footer.
     */
    public function Footer() {
        // Position at 20 mm from bottom to avoid border overlap
        $this->SetY(-20);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}
