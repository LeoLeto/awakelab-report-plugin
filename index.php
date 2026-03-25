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
$finalexamcategoryid = optional_param('finalexamcategoryid', 0, PARAM_INT);
$mode = optional_param('mode', 'questionbank', PARAM_ALPHA);
$quizids = optional_param_array('quizids', array(), PARAM_INT);
$categoryorder = optional_param_array('categoryorder', array(), PARAM_INT);

// Validate mode parameter.
if (!in_array($mode, array('questionbank', 'quiz'))) {
    $mode = 'questionbank';
}

// If a unit is selected as final exam, ensure it's included in the categories
if ($finalexamcategoryid > 0 && !in_array($finalexamcategoryid, $categoryids)) {
    $categoryids[] = $finalexamcategoryid;
}

// Get the course and ensure user is logged in.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

// Check capabilities.
$context = context_course::instance($courseid);
require_capability('report/questionbank:view', $context);

// Additional security: Block pure students (those without any teacher-level roles).
// This prevents students from accessing sensitive answer data even if role overrides exist.
$userroles = get_user_roles($context, $USER->id);
$hasteacherrole = false;

foreach ($userroles as $role) {
    if (in_array($role->shortname, array('teacher', 'editingteacher', 'manager'))) {
        $hasteacherrole = true;
        break;
    }
}

// If user doesn't have any teacher-level role, block access
if (!$hasteacherrole) {
    // Double-check with capabilities
    if (!has_capability('moodle/course:viewhiddenactivities', $context) && 
        !has_capability('moodle/course:update', $context)) {
        print_error('nopermissions', 'error', $CFG->wwwroot . '/course/view.php?id=' . $courseid);
    }
}

// Set up the page.
$PAGE->set_url('/report/questionbank/index.php', array('id' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title(get_string('title', 'report_questionbank'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

// Add CSS.
$PAGE->requires->css('/report/questionbank/styles.css');

// Get question categories for this course.
// Exclude the root "Top" category by filtering parent = 0
$categories = $DB->get_records_sql(
    "SELECT qc.* 
     FROM {question_categories} qc
     WHERE qc.contextid = :contextid
     AND qc.parent != 0
     ORDER BY qc.sortorder",
    array('contextid' => $context->id)
);

// Apply custom category order if provided.
if (!empty($categoryorder)) {
    $ordered_categories = array();
    foreach ($categoryorder as $catid) {
        if (isset($categories[$catid])) {
            $ordered_categories[$catid] = $categories[$catid];
        }
    }
    foreach ($categories as $catid => $cat) {
        if (!isset($ordered_categories[$catid])) {
            $ordered_categories[$catid] = $cat;
        }
    }
    $categories = $ordered_categories;
}

// Get quizzes for this course.
$quizzes_sql = "SELECT q.id, q.name, cm.section, cm.id as cmid
                FROM {quiz} q
                JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                WHERE q.course = :courseid
                ORDER BY cm.section, cm.id";
$quizzes = $DB->get_records_sql($quizzes_sql, array('courseid' => $courseid));

// Fetch questions based on mode.
$questions = array();
$quiz_questions_grouped = array();

if ($mode === 'quiz') {
    // Quiz mode: fetch questions from quiz slots.
    // If no quizzes explicitly selected, default to all quizzes.
    if (empty($quizids) && !empty($quizzes)) {
        $quizids = array_keys($quizzes);
    }
    if (!empty($quizids)) {
        foreach ($quizids as $quizid) {
            if (!isset($quizzes[$quizid])) {
                continue;
            }
            $quiz = $quizzes[$quizid];
            $quiz_qs = array();
            $seen_ids = array();

            // Get fixed (non-random) questions from quiz slots.
            $fixed_sql = "SELECT q.id, q.name, q.questiontext, q.qtype,
                                 qc.name as categoryname, qbe.questioncategoryid
                          FROM {quiz_slots} qs
                          JOIN {question_references} qr ON qr.itemid = qs.id
                              AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                          JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                          JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                          JOIN {question} q ON q.id = qv.questionid
                          JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                          WHERE qs.quizid = :quizid
                          AND qv.version = (
                              SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = qbe.id
                          )
                          ORDER BY qs.slot";
            $fixed = $DB->get_records_sql($fixed_sql, array('quizid' => $quizid));
            foreach ($fixed as $fq) {
                if (!isset($seen_ids[$fq->id])) {
                    $quiz_qs[] = $fq;
                    $seen_ids[$fq->id] = true;
                }
            }

            // Get random question pools from quiz slots.
            $random_sql = "SELECT qsr.filtercondition
                           FROM {quiz_slots} qs
                           JOIN {question_set_references} qsr ON qsr.itemid = qs.id
                               AND qsr.component = 'mod_quiz' AND qsr.questionarea = 'slot'
                           WHERE qs.quizid = :quizid";
            $randoms = $DB->get_records_sql($random_sql, array('quizid' => $quizid));
            foreach ($randoms as $rand) {
                $filter = @json_decode($rand->filtercondition, true);
                if (!empty($filter['questioncategoryid'])) {
                    $pool_catid = (int)$filter['questioncategoryid'];
                    $pool_sql = "SELECT q.id, q.name, q.questiontext, q.qtype,
                                        qc.name as categoryname, qbe.questioncategoryid
                                 FROM {question} q
                                 JOIN {question_versions} qv ON qv.questionid = q.id
                                 JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                 JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                 WHERE qbe.questioncategoryid = :catid
                                 AND qv.version = (
                                     SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = qbe.id
                                 )
                                 ORDER BY q.name";
                    $pool = $DB->get_records_sql($pool_sql, array('catid' => $pool_catid));
                    foreach ($pool as $pq) {
                        if (!isset($seen_ids[$pq->id])) {
                            $quiz_qs[] = $pq;
                            $seen_ids[$pq->id] = true;
                        }
                    }
                }
            }

            $quiz_questions_grouped[$quiz->name] = $quiz_qs;
            $questions = array_merge($questions, $quiz_qs);
        }
    }
} else {
    // Question bank mode: fetch questions from question bank.
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

    $sql .= " ORDER BY qc.sortorder, q.name";
    $questions = $DB->get_records_sql($sql, $params);
}

// Handle Excel download.
if ($download === 'excel') {
    require_once($CFG->dirroot . '/report/questionbank/classes/excel_export.php');
    $exporter = new \report_questionbank\excel_export();
    if ($mode === 'quiz') {
        $exporter->export_quiz_questions($quiz_questions_grouped, $course->shortname);
    } else {
        $exporter->export_questions($questions, $course->shortname);
    }
    exit;
}

// Handle PDF download.
if ($download === 'pdf') {
    require_once($CFG->dirroot . '/report/questionbank/classes/pdf_export.php');
    $exporter = new \report_questionbank\pdf_export();
    if ($mode === 'quiz') {
        $exporter->export_quiz_questions($quiz_questions_grouped, $course->fullname);
    } else {
        $exporter->export_questions($questions, $course->fullname, $finalexamcategoryid, $categoryorder);
    }
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
    width: 25%;
}
.question-bank-table th:nth-child(2),
.question-bank-table td:nth-child(2) {
    width: 11%;
}
.question-bank-table th:nth-child(3),
.question-bank-table td:nth-child(3) {
    width: 12%;
}
.question-bank-table th:nth-child(4),
.question-bank-table td:nth-child(4) {
    width: 52%;
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
    font-size: 14px !important;
    line-height: 1.4 !important;
}
.question-answers-list li * {
    font-size: 14px !important;
    line-height: 1.4 !important;
    margin: 0;
}
</style>';

echo $OUTPUT->heading(get_string('title', 'report_questionbank'));

// Display version label
echo '<div style="text-align: right; margin-bottom: 10px; color: #666; font-size: 12px;">';
echo 'Versión: ' . $plugin->release;
echo '</div>';

// Filter section.
echo '<div class="question-bank-filters" id="filterSection" style="display: block;">';
echo '<form method="get" action="index.php" id="categoryFilterForm">';
echo '<input type="hidden" name="id" value="' . $courseid . '" />';

// Mode toggle.
echo '<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">';
echo '<label style="font-weight: bold; display: inline; margin-right: 15px; font-size: 16px;">' . get_string('datasource', 'report_questionbank') . ':</label>';
echo '<label style="margin-right: 20px; font-weight: normal; cursor: pointer;">';
echo '<input type="radio" class="mode-radio" name="mode" value="questionbank" ' . ($mode === 'questionbank' ? 'checked' : '') . ' style="margin-right: 5px;">';
echo get_string('questionbankmode', 'report_questionbank');
echo '</label>';
echo '<label style="font-weight: normal; cursor: pointer;">';
echo '<input type="radio" class="mode-radio" name="mode" value="quiz" ' . ($mode === 'quiz' ? 'checked' : '') . ' style="margin-right: 5px;">';
echo get_string('quizmode', 'report_questionbank');
echo '</label>';
echo '</div>';

if ($mode === 'questionbank') {
    // Question bank mode: category selection with reorder arrows.
    echo '<div style="margin-bottom: 20px;">';
    echo '<label style="font-weight: bold; display: block; margin-bottom: 12px; font-size: 16px;">' . get_string('filterbycategory', 'report_questionbank') . ':</label>';

    // Hidden inputs for category order.
    echo '<div id="categoryOrderInputs">';
    foreach ($categories as $cat) {
        echo '<input type="hidden" name="categoryorder[]" value="' . $cat->id . '">';
    }
    echo '</div>';

    $all_selected = empty($categoryids);

    echo '<table class="generaltable" style="width: 100%; background-color: white; margin-bottom: 15px;">';
    echo '<thead>';
    echo '<tr>';
    echo '<th style="padding: 10px; text-align: center; width: 80px;">Orden</th>';
    echo '<th style="padding: 10px; text-align: left;">' . get_string('unitname', 'report_questionbank') . '</th>';
    echo '<th style="padding: 10px; text-align: center; width: 150px;">Incluir</th>';
    echo '<th style="padding: 10px; text-align: center; width: 200px;">' . get_string('selectasfinalexam', 'report_questionbank') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody id="categoryTbody">';

    // "Ninguno" row for final exam radio.
    echo '<tr>';
    echo '<td style="padding: 10px; text-align: center;">-</td>';
    echo '<td style="padding: 10px;"><em>Ninguno</em></td>';
    echo '<td style="padding: 10px; text-align: center;">-</td>';
    echo '<td style="padding: 10px; text-align: center;">';
    echo '<input type="radio" class="final-exam-radio" name="finalexamcategoryid" value="0" ' . ($finalexamcategoryid == 0 ? 'checked' : '') . '>';
    echo '</td>';
    echo '</tr>';

    foreach ($categories as $cat) {
        $is_selected = ($all_selected || in_array($cat->id, $categoryids));
        $is_final_exam = ($finalexamcategoryid > 0 && $cat->id == $finalexamcategoryid);
        $checkbox_checked = $is_selected || $is_final_exam;
        $checkbox_disabled = $is_final_exam ? 'disabled' : '';

        echo '<tr data-catid="' . $cat->id . '">';
        echo '<td style="padding: 10px; text-align: center; white-space: nowrap;">';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary move-up-btn" title="' . get_string('moveup', 'report_questionbank') . '" style="padding: 2px 6px;">&#9650;</button> ';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary move-down-btn" title="' . get_string('movedown', 'report_questionbank') . '" style="padding: 2px 6px;">&#9660;</button>';
        echo '</td>';
        echo '<td style="padding: 10px;">' . format_string($cat->name) . '</td>';
        echo '<td style="padding: 10px; text-align: center;">';
        echo '<input type="checkbox" class="category-checkbox" name="categoryids[]" value="' . $cat->id . '" ' . ($checkbox_checked ? 'checked' : '') . ' ' . $checkbox_disabled . '>';
        echo '</td>';
        echo '<td style="padding: 10px; text-align: center;">';
        echo '<input type="radio" class="final-exam-radio" name="finalexamcategoryid" value="' . $cat->id . '" ' . ($is_final_exam ? 'checked' : '') . '>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '<div style="display: flex; gap: 10px;">';
    echo '<button type="button" id="selectAllBtn" class="btn btn-outline-secondary">Seleccionar todas las unidades</button>';
    echo '</div>';
    echo '</div>';

} else {
    // Quiz mode: quiz selection.
    echo '<div style="margin-bottom: 20px;">';
    echo '<label style="font-weight: bold; display: block; margin-bottom: 12px; font-size: 16px;">' . get_string('selectquizzes', 'report_questionbank') . ':</label>';

    if (empty($quizzes)) {
        echo '<p>' . get_string('noquizzes', 'report_questionbank') . '</p>';
    } else {
        $all_quizzes_selected = empty($quizids);

        echo '<table class="generaltable" style="width: 100%; background-color: white; margin-bottom: 15px;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="padding: 10px; text-align: left;">' . get_string('quizname', 'report_questionbank') . '</th>';
        echo '<th style="padding: 10px; text-align: center; width: 200px;">' . get_string('questioncount', 'report_questionbank') . '</th>';
        echo '<th style="padding: 10px; text-align: center; width: 150px;">Incluir</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($quizzes as $quiz) {
            $slot_count_sql = "SELECT COUNT(*) as total FROM {quiz_slots} WHERE quizid = :quizid";
            $slot_count = $DB->get_record_sql($slot_count_sql, array('quizid' => $quiz->id));
            $total_questions = $slot_count ? $slot_count->total : 0;
            $is_quiz_selected = ($all_quizzes_selected || in_array($quiz->id, $quizids));

            echo '<tr>';
            echo '<td style="padding: 10px;">' . format_string($quiz->name) . '</td>';
            echo '<td style="padding: 10px; text-align: center; font-weight: bold;">' . $total_questions . '</td>';
            echo '<td style="padding: 10px; text-align: center;">';
            echo '<input type="checkbox" class="quiz-checkbox" name="quizids[]" value="' . $quiz->id . '" ' . ($is_quiz_selected ? 'checked' : '') . '>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div style="display: flex; gap: 10px;">';
        echo '<button type="button" id="selectAllQuizzesBtn" class="btn btn-outline-secondary">' . get_string('selectallquizzes', 'report_questionbank') . '</button>';
        echo '</div>';
    }

    echo '</div>';
}

echo '</form>';
echo '</div>';

// Download buttons.
if (!empty($questions)) {
    $downloadparams = 'id=' . $courseid . '&mode=' . urlencode($mode);
    if ($mode === 'quiz') {
        foreach ($quizids as $qid) {
            $downloadparams .= '&quizids[]=' . (int)$qid;
        }
    } else {
        foreach ($categoryids as $catid) {
            $downloadparams .= '&categoryids[]=' . (int)$catid;
        }
        foreach ($categories as $cat) {
            $downloadparams .= '&categoryorder[]=' . (int)$cat->id;
        }
        if ($finalexamcategoryid > 0) {
            $downloadparams .= '&finalexamcategoryid=' . (int)$finalexamcategoryid;
        }
    }

    echo '<div class="download-button-container">';
    echo '<a href="index.php?' . $downloadparams . '&download=pdf" class="btn btn-primary" style="margin-right: 10px;">';
    echo get_string('downloadpdf', 'report_questionbank');
    echo '</a>';
    echo '<a href="index.php?' . $downloadparams . '&download=excel" class="btn btn-secondary" id="excelButton" style="display: none !important;">';
    echo get_string('downloadexcel', 'report_questionbank');
    echo '</a>';
    echo '</div>';
}

// Display questions.
if ($mode === 'quiz') {
    // Quiz mode: display questions grouped by quiz.
    if (empty($quiz_questions_grouped)) {
        echo '<p>' . get_string('noquizselected', 'report_questionbank') . '</p>';
    } else {
        foreach ($quiz_questions_grouped as $quizname => $quiz_qs) {
            echo '<h4 style="margin-top: 20px; color: #0f6cbf;">' . format_string($quizname) . ' (' . count($quiz_qs) . ')</h4>';
            if (empty($quiz_qs)) {
                echo '<p><em>' . get_string('noquestions', 'report_questionbank') . '</em></p>';
                continue;
            }
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
            foreach ($quiz_qs as $question) {
                echo '<tr>';
                echo '<td>' . strip_tags($question->questiontext) . '</td>';
                echo '<td>' . format_string($question->qtype) . '</td>';
                echo '<td>' . format_string($question->categoryname) . '</td>';
                $answers = $DB->get_records('question_answers', array('question' => $question->id));
                echo '<td>';
                if (!empty($answers)) {
                    echo '<ul class="question-answers-list">';
                    foreach ($answers as $answer) {
                        $correct = ($answer->fraction > 0) ? ' (&check;)' : '';
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
    }
} else {
    // Question bank mode: display questions in custom category order.
    if (empty($questions)) {
        echo '<p>' . get_string('noquestions', 'report_questionbank') . '</p>';
    } else {
        // Group questions by category in the custom order.
        $grouped_by_cat = array();
        foreach ($questions as $question) {
            $catname = $question->categoryname;
            if (!isset($grouped_by_cat[$catname])) {
                $grouped_by_cat[$catname] = array();
            }
            $grouped_by_cat[$catname][] = $question;
        }

        // Build ordered category name list from $categories.
        $cat_name_order = array();
        foreach ($categories as $cat) {
            if (isset($grouped_by_cat[$cat->name]) && !in_array($cat->name, $cat_name_order)) {
                $cat_name_order[] = $cat->name;
            }
        }
        // Add any remaining categories not in the order.
        foreach ($grouped_by_cat as $catname => $qs) {
            if (!in_array($catname, $cat_name_order)) {
                $cat_name_order[] = $catname;
            }
        }

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

        foreach ($cat_name_order as $catname) {
            foreach ($grouped_by_cat[$catname] as $question) {
                echo '<tr>';
                echo '<td>' . strip_tags($question->questiontext) . '</td>';
                echo '<td>' . format_string($question->qtype) . '</td>';
                echo '<td>' . format_string($question->categoryname) . '</td>';
                $answers = $DB->get_records('question_answers', array('question' => $question->id));
                echo '<td>';
                if (!empty($answers)) {
                    echo '<ul class="question-answers-list">';
                    foreach ($answers as $answer) {
                        $correct = ($answer->fraction > 0) ? ' (&check;)' : '';
                        echo '<li>' . format_text($answer->answer, FORMAT_HTML) . $correct . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<em>No answers</em>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

// JavaScript placed at end of page so all DOM elements exist.
echo '<script type="text/javascript">';
echo '(function() {';
echo '    var form = document.getElementById("categoryFilterForm");';
echo '    if (!form) return;';
echo '';
echo '    function submitForm() {';
echo '        form.submit();';
echo '    }';
echo '';
echo '    /* Mode toggle. */';
echo '    var modeRadios = document.querySelectorAll(".mode-radio");';
echo '    for (var i = 0; i < modeRadios.length; i++) {';
echo '        modeRadios[i].addEventListener("change", function() {';
echo '            submitForm();';
echo '        });';
echo '    }';
echo '';
echo '    /* Final exam radios. */';
echo '    var finalExamRadios = document.querySelectorAll(".final-exam-radio");';
echo '    var categoryCheckboxes = document.querySelectorAll(".category-checkbox");';
echo '';
echo '    function updateIncludeCheckboxes() {';
echo '        for (var i = 0; i < categoryCheckboxes.length; i++) {';
echo '            var cb = categoryCheckboxes[i];';
echo '            var tr = cb.closest("tr");';
echo '            if (!tr) continue;';
echo '            var rowRadio = tr.querySelector(".final-exam-radio");';
echo '            if (rowRadio && rowRadio.checked) {';
echo '                cb.disabled = true;';
echo '                cb.checked = true;';
echo '                cb.style.opacity = "0.5";';
echo '                cb.style.cursor = "not-allowed";';
echo '            } else {';
echo '                cb.disabled = false;';
echo '                cb.style.opacity = "1";';
echo '                cb.style.cursor = "pointer";';
echo '            }';
echo '        }';
echo '    }';
echo '';
echo '    for (var i = 0; i < finalExamRadios.length; i++) {';
echo '        finalExamRadios[i].addEventListener("change", function() {';
echo '            updateIncludeCheckboxes();';
echo '            submitForm();';
echo '        });';
echo '    }';
echo '';
echo '    for (var i = 0; i < categoryCheckboxes.length; i++) {';
echo '        categoryCheckboxes[i].addEventListener("change", function() {';
echo '            if (!this.disabled) submitForm();';
echo '        });';
echo '    }';
echo '';
echo '    var selectAllBtn = document.getElementById("selectAllBtn");';
echo '    if (selectAllBtn) {';
echo '        selectAllBtn.addEventListener("click", function() {';
echo '            for (var j = 0; j < categoryCheckboxes.length; j++) {';
echo '                if (!categoryCheckboxes[j].disabled) categoryCheckboxes[j].checked = true;';
echo '            }';
echo '            submitForm();';
echo '        });';
echo '    }';
echo '';
echo '    updateIncludeCheckboxes();';
echo '';
echo '    /* Reorder: update hidden inputs from current row order. */';
echo '    function updateOrderInputs() {';
echo '        var tbody = document.getElementById("categoryTbody");';
echo '        var container = document.getElementById("categoryOrderInputs");';
echo '        if (!tbody || !container) return;';
echo '        container.innerHTML = "";';
echo '        var rows = tbody.querySelectorAll("tr[data-catid]");';
echo '        for (var j = 0; j < rows.length; j++) {';
echo '            var inp = document.createElement("input");';
echo '            inp.type = "hidden";';
echo '            inp.name = "categoryorder[]";';
echo '            inp.value = rows[j].getAttribute("data-catid");';
echo '            container.appendChild(inp);';
echo '        }';
echo '    }';
echo '';
echo '    /* Move up buttons. */';
echo '    var upBtns = document.querySelectorAll(".move-up-btn");';
echo '    for (var i = 0; i < upBtns.length; i++) {';
echo '        upBtns[i].addEventListener("click", function(e) {';
echo '            e.preventDefault();';
echo '            var row = this.closest("tr");';
echo '            if (!row) return;';
echo '            var prev = row.previousElementSibling;';
echo '            if (prev && prev.getAttribute("data-catid")) {';
echo '                row.parentNode.insertBefore(row, prev);';
echo '                updateOrderInputs();';
echo '                submitForm();';
echo '            }';
echo '        });';
echo '    }';
echo '';
echo '    /* Move down buttons. */';
echo '    var downBtns = document.querySelectorAll(".move-down-btn");';
echo '    for (var i = 0; i < downBtns.length; i++) {';
echo '        downBtns[i].addEventListener("click", function(e) {';
echo '            e.preventDefault();';
echo '            var row = this.closest("tr");';
echo '            if (!row) return;';
echo '            var next = row.nextElementSibling;';
echo '            if (next && next.getAttribute("data-catid")) {';
echo '                row.parentNode.insertBefore(next, row);';
echo '                updateOrderInputs();';
echo '                submitForm();';
echo '            }';
echo '        });';
echo '    }';
echo '';
echo '    /* Quiz checkboxes. */';
echo '    var quizCheckboxes = document.querySelectorAll(".quiz-checkbox");';
echo '    for (var i = 0; i < quizCheckboxes.length; i++) {';
echo '        quizCheckboxes[i].addEventListener("change", function() {';
echo '            submitForm();';
echo '        });';
echo '    }';
echo '';
echo '    var selectAllQuizzesBtn = document.getElementById("selectAllQuizzesBtn");';
echo '    if (selectAllQuizzesBtn) {';
echo '        selectAllQuizzesBtn.addEventListener("click", function() {';
echo '            for (var j = 0; j < quizCheckboxes.length; j++) {';
echo '                quizCheckboxes[j].checked = true;';
echo '            }';
echo '            submitForm();';
echo '        });';
echo '    }';
echo '})();';
echo '</script>';

echo $OUTPUT->footer();
