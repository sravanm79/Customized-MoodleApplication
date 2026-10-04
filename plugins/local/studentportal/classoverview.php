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
 * Class overview: every student of a course with last visit, progress, grade and work done; CSV export.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_studentportal\local\student_data;

$courseid = required_param('id', PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/studentportal:viewclass', $context);

$url = new moodle_url('/local/studentportal/classoverview.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('classoverview', 'local_studentportal') . ' | ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('local-studentportal');

$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$students = get_role_users($studentrole, $context, false,
    'u.id, u.idnumber, u.email, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, '
    . 'u.alternatename, u.suspended', 'u.lastname, u.firstname');
$now = time();
$rows = [];
$grades = [];
$never = 0;
$inactive = 0;
foreach ($students as $user) {
    if (!is_enrolled($context, $user->id, '', true)) {
        continue;
    }
    $data = new student_data($user, $now);
    $lastaccess = (int) $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $user->id, 'courseid' => $courseid]);
    $grade = $data->get_course_grade($courseid);
    $completion = $data->get_completion($course);
    $work = $data->get_work_counts($courseid);
    $isnever = !$lastaccess;
    $isinactive = !$isnever && $lastaccess < $now - 7 * DAYSECS;
    $never += $isnever ? 1 : 0;
    $inactive += $isinactive ? 1 : 0;
    if ($grade !== null) {
        $grades[] = $grade;
    }
    $rows[] = [
        'id' => $user->id,
        'fullname' => fullname($user),
        'idnumber' => $user->idnumber,
        'email' => $user->email,
        'lastaccess' => $isnever ? get_string('never') : get_string('ago', 'core_message', format_time($now - $lastaccess)),
        'lastaccesstime' => $lastaccess,
        'never' => $isnever,
        'inactive' => $isinactive,
        'hasgrade' => $grade !== null,
        'grade' => $grade === null ? null : (int) round($grade),
        'level' => $grade === null ? 'none' : ($grade >= 75 ? 'good' : ($grade >= 50 ? 'ok' : 'low')),
        'hascompletion' => $completion !== null,
        'completion' => $completion === null ? null : (int) round($completion),
        'submissions' => $work['submissions'],
        'attempts' => $work['attempts'],
        'reporturl' => (new moodle_url('/local/studentportal/report.php', ['id' => $courseid, 'userid' => $user->id]))->out(false),
        'messageurl' => (new moodle_url('/message/index.php', ['id' => $user->id]))->out(false),
    ];
}

if ($download) {
    require_sesskey();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="class-overview-' . clean_filename($course->shortname) . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Roll number', 'Email', 'Last visit', 'Progress %', 'Course grade %', 'Submissions', 'Tests'], ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, [$r['fullname'], $r['idnumber'], $r['email'],
            $r['lastaccesstime'] ? userdate($r['lastaccesstime'], '%Y-%m-%d %H:%M') : 'never',
            $r['completion'] ?? '', $r['grade'] ?? '', $r['submissions'], $r['attempts']], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentportal/classoverview', [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'metrics' => [
        ['label' => get_string('studentsenrolled', 'local_studentportal'), 'value' => count($rows), 'variant' => 'blue',
            'icon' => $OUTPUT->pix_icon('i/users', '')],
        ['label' => get_string('metric_avggrade', 'local_studentportal'),
            'value' => $grades ? round(array_sum($grades) / count($grades)) . '%' : '–', 'variant' => 'green',
            'icon' => $OUTPUT->pix_icon('i/grades', '')],
        ['label' => get_string('neverloggedin', 'local_studentportal'), 'value' => $never, 'variant' => 'amber',
            'icon' => $OUTPUT->pix_icon('i/warning', '')],
        ['label' => get_string('inactive7', 'local_studentportal'), 'value' => $inactive, 'variant' => 'teal',
            'icon' => $OUTPUT->pix_icon('i/calendar', '')],
    ],
    'downloadurl' => (new moodle_url($url, ['download' => 1, 'sesskey' => sesskey()]))->out(false),
    'announceurl' => has_capability('local/studentportal:announce', $context)
        ? (new moodle_url('/local/studentportal/announce.php', ['courseid' => $courseid]))->out(false) : null,
    'gradebookurl' => has_capability('gradereport/grader:view', $context)
        ? (new moodle_url('/grade/report/grader/index.php', ['id' => $courseid]))->out(false) : null,
]);
echo $OUTPUT->footer();
