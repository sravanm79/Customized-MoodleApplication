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
 * All students on the site (anyone with the student role in a course): courses, last login, search, CSV.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$q = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);
$perpage = 50;

require_login();
$systemcontext = context_system::instance();
require_capability('local/studentportal:register', $systemcontext);

$url = new moodle_url('/local/studentportal/allstudents.php', $q !== '' ? ['q' => $q] : []);
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('allstudents', 'local_studentportal'));
$PAGE->set_heading(get_string('allstudents', 'local_studentportal'));
$PAGE->add_body_class('local-studentportal');

$params = ['studentrole' => $DB->get_field('role', 'id', ['shortname' => 'student']), 'courselevel' => CONTEXT_COURSE];
$where = 'u.deleted = 0';
if ($q !== '') {
    $like = [];
    foreach (['u.firstname', 'u.lastname', 'u.email', 'u.idnumber', 'u.username'] as $i => $field) {
        $like[] = $DB->sql_like($field, ":q$i", false);
        $params["q$i"] = '%' . $DB->sql_like_escape($q) . '%';
    }
    $where .= ' AND (' . implode(' OR ', $like) . ')';
}
$from = "FROM {user} u
         WHERE $where AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid
                                   WHERE ra.userid = u.id AND ra.roleid = :studentrole AND ctx.contextlevel = :courselevel)";
$total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
$users = $DB->get_records_sql("SELECT u.id, u.username, u.idnumber, u.email, u.firstname, u.lastname, u.firstnamephonetic,
        u.lastnamephonetic, u.middlename, u.alternatename, u.lastaccess, u.suspended $from ORDER BY u.lastname, u.firstname",
    $params, $download ? 0 : $page * $perpage, $download ? 0 : $perpage);

$rows = [];
foreach ($users as $user) {
    $courses = [];
    foreach (enrol_get_users_courses($user->id, false, 'id, shortname') as $course) {
        if (user_has_role_assignment($user->id, $params['studentrole'], context_course::instance($course->id)->id)) {
            $courses[] = ['name' => format_string($course->shortname),
                'url' => (new moodle_url('/local/studentportal/students.php', ['id' => $course->id]))->out(false)];
        }
    }
    $rows[] = [
        'fullname' => fullname($user),
        'idnumber' => $user->idnumber,
        'email' => $user->email,
        'username' => $user->username,
        'courses' => $courses,
        'coursestext' => implode(', ', array_column($courses, 'name')),
        'never' => empty($user->lastaccess),
        'suspended' => !empty($user->suspended),
        'lastaccess' => $user->lastaccess ? userdate($user->lastaccess, get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('never'),
        'reporturl' => (new moodle_url('/local/studentportal/report.php', ['userid' => $user->id]))->out(false),
        'profileurl' => (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
    ];
}

if ($download) {
    require_sesskey();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="all-students.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Roll number', 'Email', 'Username', 'Courses', 'Last login', 'Suspended'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, [$r['fullname'], $r['idnumber'], $r['email'], $r['username'], $r['coursestext'], $r['lastaccess'],
            $r['suspended'] ? 'yes' : ''], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentportal/allstudents', [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'total' => $total,
    'never' => count(array_filter($rows, fn($r) => $r['never'])),
    'q' => $q,
    'searchurl' => (new moodle_url('/local/studentportal/allstudents.php'))->out(false),
    'registerurl' => (new moodle_url('/local/studentportal/register.php'))->out(false),
    'downloadurl' => (new moodle_url($url, ['download' => 1, 'sesskey' => sesskey()]))->out(false),
    'paging' => $OUTPUT->paging_bar($total, $page, $perpage, $url),
]);
echo $OUTPUT->footer();
