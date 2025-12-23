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
 * Excel export functionality for question bank report.
 *
 * @package    report_questionbank
 * @copyright  2025 Awakelab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_questionbank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');

/**
 * Class for exporting questions to Excel format.
 */
class excel_export {
    
    /**
     * Export questions to Excel file.
     *
     * @param array $questions Array of question objects
     * @param string $coursename Name of the course for filename
     */
    public function export_questions($questions, $coursename) {
        global $DB;
        
        // Clean the course name for use in filename.
        $filename = clean_filename('questions_' . $coursename . '_' . date('Y-m-d')) . '.xlsx';
        
        // Create new workbook.
        $workbook = new \MoodleExcelWorkbook($filename);
        
        // Add worksheet.
        $worksheet = $workbook->add_worksheet(get_string('title', 'report_questionbank'));
        
        // Define formats.
        $formatheader = $workbook->add_format();
        $formatheader->set_bold(1);
        $formatheader->set_size(12);
        $formatheader->set_color('white');
        $formatheader->set_bg_color('#0f6cbf');
        $formatheader->set_align('left');
        $formatheader->set_align('vcenter');
        
        $formatbold = $workbook->add_format();
        $formatbold->set_bold(1);
        
        $formattext = $workbook->add_format();
        $formattext->set_text_wrap(1);
        $formattext->set_align('left');
        $formattext->set_align('top');
        
        // Set column widths.
        $worksheet->set_column(0, 0, 8);  // ID
        $worksheet->set_column(1, 1, 30); // Question Name
        $worksheet->set_column(2, 2, 40); // Question Text
        $worksheet->set_column(3, 3, 15); // Question Type
        $worksheet->set_column(4, 4, 20); // Category
        $worksheet->set_column(5, 5, 50); // Answer
        $worksheet->set_column(6, 6, 12); // Is Correct
        $worksheet->set_column(7, 7, 10); // Puntaje
        
        // Write header row.
        $row = 0;
        $worksheet->write($row, 0, 'ID', $formatheader);
        $worksheet->write($row, 1, get_string('questionname', 'report_questionbank'), $formatheader);
        $worksheet->write($row, 2, get_string('questiontext', 'report_questionbank'), $formatheader);
        $worksheet->write($row, 3, get_string('questiontype', 'report_questionbank'), $formatheader);
        $worksheet->write($row, 4, get_string('category', 'report_questionbank'), $formatheader);
        $worksheet->write($row, 5, get_string('answers', 'report_questionbank'), $formatheader);
        $worksheet->write($row, 6, get_string('correctanswer', 'report_questionbank'), $formatheader);
        $worksheet->write($row, 7, 'Puntaje', $formatheader);
        
        $row++;
        
        // Write data rows.
        foreach ($questions as $question) {
            // Get answers for this question.
            $answers = $DB->get_records('question_answers', array('question' => $question->id));
            
            if (!empty($answers)) {
                foreach ($answers as $answer) {
                    $worksheet->write($row, 0, $question->id, $formattext);
                    $worksheet->write($row, 1, $this->clean_text($question->name), $formattext);
                    $worksheet->write($row, 2, $this->clean_text($question->questiontext), $formattext);
                    $worksheet->write($row, 3, $question->qtype, $formattext);
                    $worksheet->write($row, 4, $this->clean_text($question->categoryname), $formattext);
                    $worksheet->write($row, 5, $this->clean_text($answer->answer), $formattext);
                    $worksheet->write($row, 6, ($answer->fraction > 0) ? get_string('yes') : get_string('no'), $formattext);
                    $worksheet->write($row, 7, $answer->fraction, $formattext);
                    $row++;
                }
            } else {
                // Question without answers.
                $worksheet->write($row, 0, $question->id, $formattext);
                $worksheet->write($row, 1, $this->clean_text($question->name), $formattext);
                $worksheet->write($row, 2, $this->clean_text($question->questiontext), $formattext);
                $worksheet->write($row, 3, $question->qtype, $formattext);
                $worksheet->write($row, 4, $this->clean_text($question->categoryname), $formattext);
                $worksheet->write($row, 5, '', $formattext);
                $worksheet->write($row, 6, '', $formattext);
                $worksheet->write($row, 7, '', $formattext);
                $row++;
            }
        }
        
        // Close workbook and send to browser.
        $workbook->close();
    }
    
    /**
     * Clean text for Excel export (remove HTML tags and extra whitespace).
     *
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    private function clean_text($text) {
        // Remove HTML tags.
        $text = strip_tags($text);
        // Decode HTML entities.
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Remove extra whitespace.
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim whitespace.
        $text = trim($text);
        return $text;
    }
}
