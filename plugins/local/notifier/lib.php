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
 * Library functions for local_notifier.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "Reminder settings" to the course navigation (the course's More menu) for teachers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_notifier_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    if ($course->id != SITEID && has_capability('local/notifier:managecourse', $context)) {
        $navigation->add(get_string('coursereminders', 'local_notifier'),
            new moodle_url('/local/notifier/course.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_notifier_course', new pix_icon('i/notifications', ''));
    }
}

/**
 * Adds "Google Calendar sync" to the user's Preferences page once the site has Google OAuth credentials.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_notifier_extend_navigation_user_settings(navigation_node $navigation, stdClass $user,
        context_user $usercontext, stdClass $course, context_course $coursecontext) {
    global $USER;
    if ((int) $user->id !== (int) $USER->id || !get_config('local_notifier', 'gcal_client_id')) {
        return;
    }
    $navigation->add(get_string('gcalheading', 'local_notifier'),
        new moodle_url('/local/notifier/gcal_connect.php'),
        navigation_node::TYPE_SETTING, null, 'local_notifier_gcal', new pix_icon('i/calendar', ''));
}
