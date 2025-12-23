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
 * CSV export functionality for question bank report.
 *
 * @package    report_questionbank
 * @copyright  2025 Awakelab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_questionbank;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting questions to CSV format.
 */
class csv_export {
    
    /**
     * Export questions to CSV file.
     *
     * @param array $questions Array of question objects
     * @param string $coursename Name of the course for filename
     */
    public function export_questions($questions, $coursename) {
        global $DB;
        
        // Clean the course name for use in filename.
        $filename = clean_filename('questions_' . $coursename . '_' . date('Y-m-d')) . '.csv';
        
        // Set headers for CSV download.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream.
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (helps Excel open the file correctly).
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write CSV header.
        fputcsv($output, array(
            'Question ID',
            'Question Name',
            'Question Text',
            'Question Type',
            'Category',
            'Answer',
            'Is Correct',
            'Fraction'
        ));
        
        // Write data rows.
        foreach ($questions as $question) {
            // Get answers for this question.
            $answers = $DB->get_records('question_answers', array('question' => $question->id));
            
            if (!empty($answers)) {
                foreach ($answers as $answer) {
                    fputcsv($output, array(
                        $question->id,
                        $this->clean_text($question->name),
                        $this->clean_text($question->questiontext),
                        $question->qtype,
                        $this->clean_text($question->categoryname),
                        $this->clean_text($answer->answer),
                        ($answer->fraction > 0) ? 'Yes' : 'No',
                        $answer->fraction
                    ));
                }
            } else {
                // Question without answers.
                fputcsv($output, array(
                    $question->id,
                    $this->clean_text($question->name),
                    $this->clean_text($question->questiontext),
                    $question->qtype,
                    $this->clean_text($question->categoryname),
                    '',
                    '',
                    ''
                ));
            }
        }
        
        fclose($output);
    }
    
    /**
     * Clean text for CSV export (remove HTML tags and extra whitespace).
     *
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    private function clean_text($text) {
        // Remove HTML tags.
        $text = strip_tags($text);
        // Remove extra whitespace.
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim whitespace.
        $text = trim($text);
        return $text;
    }
}
