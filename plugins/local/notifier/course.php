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
 * Course reminder settings: teachers choose when their students are reminded.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_notifier\notifier;
use core\output\notification;

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/notifier:managecourse', $context);

$url = new moodle_url('/local/notifier/course.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursereminders', 'local_notifier'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_notifier\form\course_form($url);
$override = notifier::course_override($course->id);
$data = ['id' => $course->id];
foreach (notifier::TYPES as $type) {
    $value = $override ? $override->{$type . '_lead_times'} : null;
    $data[$type] = $value === '0' ? 'off' : (string) $value;
}
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
} else if ($fromform = $form->get_data()) {
    notifier::save_course_override($course->id, (array) $fromform);
    redirect($url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursereminders', 'local_notifier'));
if (!get_config('local_notifier', 'enabled')) {
    echo $OUTPUT->notification(get_string('disabledsite', 'local_notifier'), 'warning');
}
$form->display();
echo $OUTPUT->footer();
