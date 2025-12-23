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
 * @copyright  2025 Your Name
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
     */
    public function export_questions($questions, $coursename) {
        global $DB;
        
        // Clean the course name for use in filename.
        $filename = clean_filename('questions_' . $coursename . '_' . date('Y-m-d')) . '.pdf';
        
        // Set document information
        $this->SetCreator('Moodle Question Bank Report');
        $this->SetAuthor('Moodle');
        $this->SetTitle('Question Bank Report - ' . $coursename);
        $this->SetSubject('Question Bank Export');
        
        // Set default monospaced font
        $this->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $this->SetMargins(15, 15, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        
        // Set auto page breaks
        $this->SetAutoPageBreak(true, 15);
        
        // Add a page
        $this->AddPage();
        
        // Set font for title
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'Question Bank Report', 0, 1, 'C');
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, $coursename, 0, 1, 'C');
        $this->Ln(5);
        
        // Process each question
        foreach ($questions as $question) {
            // Get answers for this question
            $answers = $DB->get_records('question_answers', array('question' => $question->id));
            
            // Question header
            $this->SetFont('helvetica', 'B', 12);
            $this->SetFillColor(15, 108, 191);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 8, 'Question: ' . $this->clean_text($question->name), 0, 1, 'L', true);
            $this->SetTextColor(0, 0, 0);
            
            // Question details
            $this->SetFont('helvetica', '', 10);
            
            // Type and Category
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(40, 6, 'Type:', 0, 0);
            $this->SetFont('helvetica', '', 10);
            $this->Cell(60, 6, $question->qtype, 0, 0);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(40, 6, 'Category:', 0, 0);
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 6, $this->clean_text($question->categoryname), 0, 1);
            
            // Question text
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, 'Question Text:', 0, 1);
            $this->SetFont('helvetica', '', 10);
            $this->MultiCell(0, 6, $this->clean_text($question->questiontext), 0, 'L');
            
            // Answers
            if (!empty($answers)) {
                $this->SetFont('helvetica', 'B', 10);
                $this->Cell(0, 6, 'Answers:', 0, 1);
                $this->SetFont('helvetica', '', 10);
                
                foreach ($answers as $answer) {
                    $correct = ($answer->fraction > 0) ? ' [CORRECT]' : '';
                    $this->Cell(10, 6, '•', 0, 0);
                    $this->MultiCell(0, 6, $this->clean_text($answer->answer) . $correct, 0, 'L');
                }
            } else {
                $this->SetFont('helvetica', 'I', 10);
                $this->Cell(0, 6, 'No answers', 0, 1);
            }
            
            $this->Ln(5);
            
            // Add a line separator
            $this->SetDrawColor(200, 200, 200);
            $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
            $this->Ln(5);
        }
        
        // Output PDF
        $this->Output($filename, 'D');
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
        // No header for this report
    }
    
    /**
     * Page footer.
     */
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}
