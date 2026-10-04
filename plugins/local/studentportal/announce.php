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
 * Send an announcement to students: they get a notification (bell and email) and see it on their dashboard.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_studentportal\form\announce_form;
use local_studentportal\local\announcements;

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();
$systemcontext = context_system::instance();
$cansite = has_capability('local/studentportal:announcesite', $systemcontext);
$courses = [];
foreach (announcements::courses_for($USER->id) as $course) {
    $courses[$course->id] = format_string($course->fullname) . ' (' . format_string($course->shortname) . ')';
}
if (!$cansite && !$courses) {
    throw new required_capability_exception($systemcontext, 'local/studentportal:announce', 'nopermissions', '');
}

$url = new moodle_url('/local/studentportal/announce.php', $courseid ? ['courseid' => $courseid] : []);
$PAGE->set_url($url);
if ($courseid && isset($courses[$courseid])) {
    require_login($courseid);
    $PAGE->set_context(context_course::instance($courseid));
    $PAGE->set_pagelayout('incourse');
} else {
    $PAGE->set_context($systemcontext);
    $PAGE->set_pagelayout('standard');
}
$PAGE->set_title(get_string('sendannouncement', 'local_studentportal'));
$PAGE->set_heading(get_string('sendannouncement', 'local_studentportal'));
$PAGE->add_body_class('local-studentportal');

$form = new announce_form($url, ['cansite' => $cansite, 'courses' => $courses]);
if ($courseid && isset($courses[$courseid])) {
    $form->set_data(['audience' => 'courses', 'courses' => [$courseid]]);
}
$listurl = new moodle_url('/local/studentportal/announcements.php');
if ($form->is_cancelled()) {
    redirect($listurl);
}
if ($data = $form->get_data()) {
    $chosen = $data->audience === 'site' && $cansite ? []
        : array_values(array_intersect(array_map('intval', (array) $data->courses), array_keys($courses)));
    if ($data->audience !== 'site' && !$chosen) {
        throw new moodle_exception('error_nocourses', 'local_studentportal');
    }
    $count = count(announcements::recipients($chosen));
    announcements::create($USER->id, $chosen, $data->subject, $data->message['text'], (int) $data->message['format']);
    redirect($listurl, get_string('announcementqueued', 'local_studentportal', $count), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('announceintro', 'local_studentportal'), ['class' => 'local-studentportal-intro']);
$form->display();
echo $OUTPUT->footer();
