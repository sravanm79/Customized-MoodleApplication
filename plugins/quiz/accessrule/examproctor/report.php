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
 * Proctoring report: violations per attempt, and an event timeline for one attempt.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:viewreports', $context);

$baseurl = new moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $cmid]);
$PAGE->set_url($attemptid ? new moodle_url($baseurl, ['attempt' => $attemptid]) : $baseurl);
$PAGE->set_title(get_string('reporttitle', 'quizaccess_examproctor'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

$eventname = function(string $type): string {
    $key = 'event_' . $type;
    return get_string_manager()->string_exists($key, 'quizaccess_examproctor')
        ? get_string($key, 'quizaccess_examproctor') : s($type);
};

echo $OUTPUT->header();

if ($attemptid) {
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $cm->instance], '*', MUST_EXIST);
    $user = core_user::get_user($attempt->userid, '*', MUST_EXIST);

    echo $OUTPUT->heading(get_string('reportdetail', 'quizaccess_examproctor', fullname($user)));
    echo html_writer::link($baseurl, get_string('backtoreport', 'quizaccess_examproctor'));

    $events = $DB->get_records('quizaccess_examproctor_log', ['attemptid' => $attemptid], 'timecreated, id');
    if (!$events) {
        echo $OUTPUT->notification(get_string('noevents', 'quizaccess_examproctor'), 'info');
    } else {
        $table = new html_table();
        $table->head = [get_string('time'), get_string('event', 'quizaccess_examproctor'),
                get_string('details', 'quizaccess_examproctor')];
        foreach ($events as $e) {
            $name = $eventname($e->eventtype);
            $row = new html_table_row([userdate($e->timecreated, get_string('strftimedatetimeaccurate', 'core_langconfig')),
                    $e->isviolation ? html_writer::tag('strong', $name) : $name, s($e->details)]);
            if ($e->isviolation) {
                $row->attributes['class'] = 'table-danger';
            }
            $table->data[] = $row;
        }
        echo html_writer::table($table);
    }
} else {
    echo $OUTPUT->heading(get_string('reporttitle', 'quizaccess_examproctor'));

    $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $sql = "SELECT qa.id, qa.attempt, qa.state, qa.preview, qa.userid, $userfields,
                   SUM(CASE WHEN l.eventtype = 'tabhidden' THEN 1 ELSE 0 END) AS tabhidden,
                   SUM(CASE WHEN l.eventtype = 'windowblur' THEN 1 ELSE 0 END) AS windowblur,
                   SUM(CASE WHEN l.eventtype = 'fullscreenexit' THEN 1 ELSE 0 END) AS fullscreenexit,
                   SUM(l.isviolation) AS violations,
                   MAX(l.timecreated) AS lastevent
              FROM {quiz_attempts} qa
              JOIN {user} u ON u.id = qa.userid
              JOIN {quizaccess_examproctor_log} l ON l.attemptid = qa.id
             WHERE qa.quiz = :quizid
          GROUP BY qa.id, qa.attempt, qa.state, qa.preview, qa.userid, $userfields
          ORDER BY violations DESC, lastevent DESC";
    $rows = $DB->get_records_sql($sql, ['quizid' => $cm->instance]);

    if (!$rows) {
        echo $OUTPUT->notification(get_string('noevents', 'quizaccess_examproctor'), 'info');
    } else {
        $table = new html_table();
        $table->head = [get_string('user'), get_string('attemptnumber', 'quiz'), get_string('status'),
                $eventname('tabhidden'), $eventname('windowblur'), $eventname('fullscreenexit'),
                get_string('violations', 'quizaccess_examproctor'), get_string('lastevent', 'quizaccess_examproctor'), ''];
        foreach ($rows as $r) {
            $name = fullname($r);
            if ($r->preview) {
                $name .= ' ' . html_writer::span(get_string('preview', 'quiz'), 'badge bg-info text-white');
            }
            $table->data[] = [
                $name,
                $r->attempt,
                get_string('state' . $r->state, 'quiz'),
                $r->tabhidden,
                $r->windowblur,
                $r->fullscreenexit,
                html_writer::tag('strong', $r->violations),
                userdate($r->lastevent),
                html_writer::link(new moodle_url($baseurl, ['attempt' => $r->id]), get_string('view')),
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
