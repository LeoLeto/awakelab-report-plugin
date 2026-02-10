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
 * Library functions for the question bank report plugin.
 *
 * @package    report_questionbank
 * @copyright  2025 Awakelab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This function extends the course navigation with the report items.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The course context
 */
function report_questionbank_extend_navigation_course($navigation, $course, $context) {
    global $USER;
    
    // Check if user has any teacher-level roles
    $userroles = get_user_roles($context, $USER->id);
    $hasteacherrole = false;
    $hasstudentonly = true;
    
    foreach ($userroles as $role) {
        if (in_array($role->shortname, array('teacher', 'editingteacher', 'manager'))) {
            $hasteacherrole = true;
            $hasstudentonly = false;
            break;
        }
        if ($role->shortname !== 'student') {
            $hasstudentonly = false;
        }
    }
    
    // Hide from pure students (those without any teacher-level roles)
    if ($hasstudentonly && !$hasteacherrole) {
        return;
    }
    
    // Check capability and ensure user has teacher-level access
    if (has_capability('report/questionbank:view', $context) &&
        (has_capability('moodle/course:viewhiddenactivities', $context) || 
         has_capability('moodle/course:update', $context))) {
        $url = new moodle_url('/report/questionbank/index.php', array('id' => $course->id));
        $navigation->add(
            get_string('pluginname', 'report_questionbank'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', '')
        );
    }
}
