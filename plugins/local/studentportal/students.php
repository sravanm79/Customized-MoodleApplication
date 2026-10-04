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
 * A course's students with their login status; reset passwords and share them again.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_studentportal\local\credentials;
use local_studentportal\local\registrar;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$coursecontext = context_course::instance($courseid);
$systemcontext = context_system::instance();
require_capability('local/studentportal:register', $systemcontext);

$url = new moodle_url('/local/studentportal/students.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursestudents', 'local_studentportal') . ' | ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('local-studentportal');

$students = get_role_users($DB->get_field('role', 'id', ['shortname' => 'student']), $coursecontext, false,
    'u.id, u.username, u.auth, u.idnumber, u.email, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, '
    . 'u.middlename, u.alternatename, u.lastaccess, u.suspended', 'u.lastname, u.firstname');

// Reset the selected students' passwords and show them for sharing. A row's own button resets only that student,
// even if other rows are ticked.
$single = optional_param('single', 0, PARAM_INT);
$reset = $single ? [$single] : optional_param_array('reset', [], PARAM_INT);
if ($reset && confirm_sesskey()) {
    $results = [];
    foreach ($reset as $userid) {
        if (!isset($students[$userid]) || $students[$userid]->auth !== 'manual') {
            continue;
        }
        $user = $students[$userid];
        $results[] = [
            'userid' => (int) $user->id,
            'username' => $user->username,
            'password' => registrar::reset_password((int) $user->id),
            'fullname' => fullname($user),
            'firstname' => $user->firstname,
            'email' => $user->email,
            'idnumber' => $user->idnumber,
            'created' => false,
            'courses' => [format_string($course->fullname)],
        ];
    }
    if ($results) {
        redirect(new moodle_url('/local/studentportal/credentials.php', ['key' => credentials::store($results)]));
    }
    redirect($url);
}

$rows = [];
$never = 0;
foreach ($students as $user) {
    $neverloggedin = empty($user->lastaccess);
    $never += $neverloggedin ? 1 : 0;
    $rows[] = [
        'id' => $user->id,
        'fullname' => fullname($user),
        'idnumber' => $user->idnumber,
        'email' => $user->email,
        'username' => $user->username,
        'lastaccess' => $neverloggedin ? get_string('never') : userdate($user->lastaccess, get_string('strftimedatetimeshort', 'langconfig')),
        'neverloggedin' => $neverloggedin,
        'suspended' => !empty($user->suspended),
        'canreset' => $user->auth === 'manual' && empty($user->suspended),
        'profileurl' => (new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $courseid]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentportal/students', [
    'coursename' => format_string($course->fullname),
    'rows' => $rows,
    'count' => count($rows),
    'never' => $never,
    'hasrows' => !empty($rows),
    'registerurl' => (new moodle_url('/local/studentportal/register.php', ['courseid' => $courseid]))->out(false),
    'participantsurl' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
