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
 * @copyright  2025 Awakelab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Load plugin version information
require_once($CFG->dirroot . '/report/questionbank/version.php');

// Get the course ID from the URL parameter.
$courseid = required_param('id', PARAM_INT);
$categoryids = optional_param_array('categoryids', array(), PARAM_INT);
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

if (!empty($categoryids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
    $sql .= " AND qc.id $insql";
    $params = array_merge($params, $inparams);
}

$sql .= " ORDER BY qc.name, q.name";

$questions = $DB->get_records_sql($sql, $params);

// Handle Excel download.
if ($download === 'excel') {
    require_once($CFG->dirroot . '/report/questionbank/classes/excel_export.php');
    $exporter = new \report_questionbank\excel_export();
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
    width: 30%;
}
.question-bank-table th:nth-child(2),
.question-bank-table td:nth-child(2) {
    width: 10%;
}
.question-bank-table th:nth-child(3),
.question-bank-table td:nth-child(3) {
    width: 20%;
}
.question-bank-table th:nth-child(4),
.question-bank-table td:nth-child(4) {
    width: 40%;
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

// Display version label
echo '<div style="text-align: right; margin-bottom: 10px; color: #666; font-size: 12px;">';
echo 'Versión: ' . $plugin->release;
echo '</div>';

// Advanced options toggle
echo '<div style="margin-bottom: 20px;">';
echo '<label style="cursor: pointer; user-select: none;">';
echo '<input type="checkbox" id="advancedOptionsToggle" onclick="toggleAdvancedOptions(this)" style="margin-right: 8px;">';
echo '<span style="font-weight: bold;">Opciones Avanzadas</span>';
echo '</label>';
echo '</div>';

echo '<script type="text/javascript">';
echo 'function toggleAdvancedOptions(checkbox) {';
echo '    var filterSection = document.getElementById("filterSection");';
echo '    var excelButton = document.getElementById("excelButton");';
echo '    if (checkbox.checked) {';
echo '        if (filterSection) filterSection.style.display = "block";';
echo '        if (excelButton) excelButton.style.display = "inline-block";';
echo '    } else {';
echo '        if (filterSection) filterSection.style.display = "none";';
echo '        if (excelButton) excelButton.style.display = "none";';
echo '    }';
echo '}';
echo '</script>';

// Category filter.
echo '<div class="question-bank-filters" id="filterSection" style="display: none;">';
echo '<form method="get" action="index.php" id="categoryFilterForm">';
echo '<input type="hidden" name="id" value="' . $courseid . '" />';
echo '<div style="margin-bottom: 20px;">';
echo '<label style="font-weight: bold; display: block; margin-bottom: 12px; font-size: 16px;">' . get_string('filterbycategory', 'report_questionbank') . ':</label>';
echo '<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">';

// If no categories selected, select all by default
$all_selected = empty($categoryids);

foreach ($categories as $cat) {
    $is_selected = ($all_selected || in_array($cat->id, $categoryids));
    $tag_class = $is_selected ? 'category-tag active' : 'category-tag';
    echo '<div class="' . $tag_class . '" data-catid="' . $cat->id . '" style="padding: 8px 16px; border-radius: 20px; cursor: pointer; user-select: none; transition: all 0.2s; font-weight: 500;">';
    echo format_string($cat->name);
    echo '</div>';
}
echo '</div>';
echo '<div style="display: flex; gap: 10px;">';
echo '<button type="button" id="selectAllBtn" class="btn btn-outline-secondary">Seleccionar todas las unidades</button>';
echo '<button type="submit" class="btn btn-outline-primary">Aplicar filtro</button>';
echo '</div>';
echo '</div>';
echo '</form>';
echo '</div>';

echo '<style>';
echo '.category-tag {';
echo '    background-color: #e0e0e0;';
echo '    color: #666;';
echo '    border: 2px solid #e0e0e0;';
echo '}';
echo '.category-tag:hover {';
echo '    background-color: #d0d0d0;';
echo '    border-color: #d0d0d0;';
echo '}';
echo '.category-tag.active {';
echo '    background-color: #0f6cbf;';
echo '    color: white;';
echo '    border-color: #0f6cbf;';
echo '}';
echo '.category-tag.active:hover {';
echo '    background-color: #0a5291;';
echo '    border-color: #0a5291;';
echo '}';
echo '</style>';

// Download button.
if (!empty($questions)) {
    $categoryparams = '';
    foreach ($categoryids as $catid) {
        $categoryparams .= '&categoryids[]=' . $catid;
    }
    echo '<div class="download-button-container">';
    echo '<a href="index.php?id=' . $courseid . $categoryparams . '&download=pdf" class="btn btn-primary" style="margin-right: 10px;">';
    echo get_string('downloadpdf', 'report_questionbank');
    echo '</a>';
    echo '<a href="index.php?id=' . $courseid . $categoryparams . '&download=excel" class="btn btn-secondary" id="excelButton" style="display: none;">';
    echo get_string('downloadexcel', 'report_questionbank');
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
    echo '<th>' . get_string('questiontext', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('questiontype', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('category', 'report_questionbank') . '</th>';
    echo '<th>' . get_string('answers', 'report_questionbank') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($questions as $question) {
        echo '<tr>';
        echo '<td>' . strip_tags($question->questiontext) . '</td>';
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

echo '<script type="text/javascript">';
echo '(function() {';
echo '    var tags = document.querySelectorAll(".category-tag");';
echo '    var selectAllBtn = document.getElementById("selectAllBtn");';
echo '    var form = document.getElementById("categoryFilterForm");';
echo '    ';
echo '    if (selectAllBtn && tags.length > 0) {';
echo '        tags.forEach(function(tag) {';
echo '            tag.addEventListener("click", function() {';
echo '                tag.classList.toggle("active");';
echo '            });';
echo '        });';
echo '        ';
echo '        selectAllBtn.addEventListener("click", function() {';
echo '            var allActive = Array.from(tags).every(function(t) { return t.classList.contains("active"); });';
echo '            tags.forEach(function(tag) {';
echo '                if (allActive) {';
echo '                    tag.classList.remove("active");';
echo '                } else {';
echo '                    tag.classList.add("active");';
echo '                }';
echo '            });';
echo '        });';
echo '    }';
echo '    ';
echo '    if (form) {';
echo '        form.addEventListener("submit", function(e) {';
echo '            var activeTags = Array.from(tags).filter(function(t) {';
echo '                return t.classList.contains("active");';
echo '            });';
echo '            ';
echo '            activeTags.forEach(function(tag) {';
echo '                var input = document.createElement("input");';
echo '                input.type = "hidden";';
echo '                input.name = "categoryids[]";';
echo '                input.value = tag.getAttribute("data-catid");';
echo '                form.appendChild(input);';
echo '            });';
echo '        });';
echo '    }';
echo '})();';
echo '</script>';

echo $OUTPUT->footer();
