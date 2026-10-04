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
 * Library functions for local_gradesheet.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "Grade sheets" to the course navigation (the course's More menu), for teachers and students.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_gradesheet_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    if (has_any_capability(['local/gradesheet:manage', 'local/gradesheet:viewstats'], $context)) {
        $navigation->add(get_string('gradesheets', 'local_gradesheet'),
            new moodle_url('/local/gradesheet/index.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_gradesheet', new pix_icon('i/grades', ''));
    }
}
