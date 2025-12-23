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
 * Main entry point for the question bank report.
 *
 * @package    report_questionbank
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Get the course ID from the URL parameter.
$courseid = required_param('id', PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

// Get the course and ensure user is logged in.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

// Check capabilities.
$context = context_course::instance($courseid);
require_capability('report/questionbank:view', $context);

// Set up the page.
$PAGE->set_url('/report/questionbank/index.php', array('id' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title(get_string('title', 'report_questionbank'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

// Add CSS.
$PAGE->requires->css('/report/questionbank/styles.css');

// Get question categories for this course.
$categories = $DB->get_records_sql(
    "SELECT qc.* 
     FROM {question_categories} qc
     WHERE qc.contextid = :contextid
     ORDER BY qc.name",
    array('contextid' => $context->id)
);

// Build query to get questions - compatible with Moodle 4.0+
$sql = "SELECT q.id, q.name, q.questiontext, q.qtype, qc.name as categoryname, qbe.questioncategoryid
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        WHERE qc.contextid = :contextid
        AND qv.version = (SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = qbe.id)";

$params = array('contextid' => $context->id);

if ($categoryid > 0) {
    $sql .= " AND qc.id = :categoryid";
    $params['categoryid'] = $categoryid;
}

$sql .= " ORDER BY qc.name, q.name";

$questions = $DB->get_records_sql($sql, $params);

// Handle CSV download.
if ($download === 'csv') {
    require_once($CFG->dirroot . '/report/questionbank/classes/csv_export.php');
    $exporter = new \report_questionbank\csv_export();
    $exporter->export_questions($questions, $course->shortname);
    exit;
}

// Handle PDF download.
if ($download === 'pdf') {
    require_once($CFG->dirroot . '/report/questionbank/classes/pdf_export.php');
    $exporter = new \report_questionbank\pdf_export();
    $exporter->export_questions($questions, $course->shortname);
    exit;
}

// Output page.
echo $OUTPUT->header();

// Add inline CSS
echo '<style>
.question-bank-filters {
    margin: 20px 0;
    padding: 15px;
    background-color: #f5f5f5;
    border-radius: 5px;
    display: block;
    clear: both;
}
.question-bank-filters label {
    font-weight: bold;
    margin-right: 10px;
}
.question-bank-filters select {
    padding: 5px 10px;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 14px;
}
.download-button-container {
    margin: 20px 0;
    text-align: right;
    display: block;
    clear: both;
}
.question-bank-table-container {
    margin: 20px 0;
    overflow-x: auto;
    display: block;
    clear: both;
    width: 100%;
}
.question-bank-table {
    width: auto !important;
    min-width: 100%;
    border-collapse: collapse;
    display: block !important;
}
.question-bank-table thead {
    display: block !important;
    width: 100%;
}
.question-bank-table tbody {
    display: block !important;
    width: 100%;
}
.question-bank-table tr {
    display: table !important;
    width: 100%;
    table-layout: fixed;
}
.question-bank-table th,
.question-bank-table td {
    display: table-cell !important;
    word-wrap: break-word;
}
.question-bank-table th:nth-child(1),
.question-bank-table td:nth-child(1) {
    width: 15%;
}
.question-bank-table th:nth-child(2),
.question-bank-table td:nth-child(2) {
    width: 25%;
}
.question-bank-table th:nth-child(3),
.question-bank-table td:nth-child(3) {
    width: 10%;
}
.question-bank-table th:nth-child(4),
.question-bank-table td:nth-child(4) {
    width: 15%;
}
.question-bank-table th:nth-child(5),
.question-bank-table td:nth-child(5) {
    width: 35%;
}
.question-bank-table th {
    background-color: #0f6cbf;
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #0a5291;
}
.question-bank-table td {
    padding: 10px;
    border: 1px solid #ddd;
    vertical-align: top;
    background-color: #fff;
}
.question-bank-table tbody tr:hover {
    background-color: #f5f5f5;
}
.question-bank-table tbody tr:hover td {
    background-color: #f5f5f5;
}
.question-answers-list {
    margin: 0;
    padding-left: 20px;
    list-style-type: disc;
}
.question-answers-list li {
    margin: 5px 0;
}
</style>';

echo $OUTPUT->heading(get_string('title', 'report_questionbank'));

// Category filter.
echo '<div class="question-bank-filters">';
echo '<form method="get" action="index.php">';
echo '<input type="hidden" name="id" value="' . $courseid . '" />';
echo '<label for="categoryid">' . get_string('filterbycategory', 'report_questionbank') . ': </label>';
echo '<select name="categoryid" id="categoryid" onchange="this.form.submit()">';
echo '<option value="0">' . get_string('allcategories', 'report_questionbank') . '</option>';
foreach ($categories as $cat) {
    $selected = ($cat->id == $categoryid) ? 'selected' : '';
    echo '<option value="' . $cat->id . '" ' . $selected . '>' . format_string($cat->name) . '</option>';
}
echo '</select>';
echo '</form>';
echo '</div>';

// Download button.
if (!empty($questions)) {
    echo '<div class="download-button-container">';
    echo '<a href="index.php?id=' . $courseid . '&categoryid=' . $categoryid . '&download=csv" class="btn btn-primary" style="margin-right: 10px;">';
    echo get_string('downloadcsv', 'report_questionbank');
    echo '</a>';
    echo '<a href="index.php?id=' . $courseid . '&categoryid=' . $categoryid . '&download=pdf" class="btn btn-secondary">';
    echo get_string('downloadpdf', 'report_questionbank');
    echo '</a>';
    echo '</div>';
}

// Display questions.
if (empty($questions)) {
    echo '<p>' . get_string('noquestions', 'report_questionbank') . '</p>';
} else {
    echo '<div class="question-bank-table-container">';
    echo '<table class="generaltable question-bank-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('questionname', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('questiontext', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('questiontype', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('category', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('answers', 'report_questionbank') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($questions as $question) {
        echo '<tr>';
        echo '<td>' . format_string($question->name) . '</td>';
        echo '<td>' . format_text($question->questiontext, FORMAT_HTML) . '</td>';
        echo '<td>' . format_string($question->qtype) . '</td>';
        echo '<td>' . format_string($question->categoryname) . '</td>';
        
        // Get answers for this question.
        $answers = $DB->get_records('question_answers', array('question' => $question->id));
        echo '<td>';
        if (!empty($answers)) {
            echo '<ul class="question-answers-list">';
            foreach ($answers as $answer) {
                $correct = ($answer->fraction > 0) ? ' (✓)' : '';
                echo '<li>' . format_text($answer->answer, FORMAT_HTML) . $correct . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<em>No answers</em>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

echo $OUTPUT->footer();
