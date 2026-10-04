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
 * Navigation: Class overview, Send announcement and Students and logins in courses, per capability.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "Students and logins" to the course navigation (the theme shows it in the course sub-sidebar).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_studentportal_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ($course->id == SITEID) {
        return;
    }
    if (has_capability('local/studentportal:viewclass', $context)) {
        $navigation->add(get_string('classoverview', 'local_studentportal'),
            new moodle_url('/local/studentportal/classoverview.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_studentportal_class', new pix_icon('i/report', ''));
    }
    if (has_capability('local/studentportal:announce', $context)) {
        $navigation->add(get_string('sendannouncement', 'local_studentportal'),
            new moodle_url('/local/studentportal/announce.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_studentportal_announce', new pix_icon('i/email', ''));
    }
    if (has_capability('local/studentportal:register', context_system::instance())) {
        $navigation->add(get_string('coursestudents', 'local_studentportal'),
            new moodle_url('/local/studentportal/students.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_studentportal_students', new pix_icon('i/users', ''));
    }
}
