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
 * Student report: one student's grades, feedback, progress and activity as they see it on My performance.
 * From a course (teachers: that course only) or site-wide (admins/managers: all the student's courses).
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_studentportal\local\student_data;
use local_studentportal\output\performance_report;

$courseid = optional_param('id', 0, PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$student = core_user::get_user($userid, '*', MUST_EXIST);

$params = ['userid' => $userid] + ($courseid ? ['id' => $courseid] : []);
$url = new moodle_url('/local/studentportal/report.php', $params);
$PAGE->set_url($url);
if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('local/studentportal:viewclass', $context);
    if (!is_enrolled($context, $userid)) {
        throw new moodle_exception('error_notastudent', 'local_studentportal');
    }
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $PAGE->navbar->add(get_string('classoverview', 'local_studentportal'),
        new moodle_url('/local/studentportal/classoverview.php', ['id' => $courseid]));
    $backurl = new moodle_url('/local/studentportal/classoverview.php', ['id' => $courseid]);
} else {
    require_login();
    $context = context_system::instance();
    require_capability('local/studentportal:register', $context);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('standard');
    $backurl = new moodle_url('/local/studentportal/allstudents.php');
}
$PAGE->set_title(get_string('studentreport', 'local_studentportal', fullname($student)));
$PAGE->set_heading(get_string('studentreport', 'local_studentportal', fullname($student)));
$PAGE->add_body_class('local-studentportal local-studentportal-performance');

$data = new student_data($student);
echo $OUTPUT->header();
echo html_writer::div(
    html_writer::tag('p', get_string('reportintro', 'local_studentportal', (object) ['name' => fullname($student),
        'idnumber' => $student->idnumber ?: '–', 'email' => $student->email]), ['class' => 'mb-0']) .
    html_writer::div(
        html_writer::link(new moodle_url('/message/index.php', ['id' => $userid]), get_string('messagestudent', 'local_studentportal'),
            ['class' => 'btn btn-secondary btn-sm']) . ' ' .
        html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-outline-secondary btn-sm']), 'd-flex gap-2'),
    'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 local-studentportal-intro');
echo $OUTPUT->render_from_template('local_studentportal/performance', performance_report::build($data, $OUTPUT, $courseid ?: null));
echo $OUTPUT->footer();
