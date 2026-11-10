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
 * Recorded webcam video of one attempt: the segments in order, played one after another.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use quizaccess_examproctor\local\video;

$cmid = required_param('cmid', PARAM_INT);
$attemptid = required_param('attempt', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:viewreports', $context);

$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $cm->instance], '*', MUST_EXIST);
$user = core_user::get_user($attempt->userid, '*', MUST_EXIST);

$url = new moodle_url('/mod/quiz/accessrule/examproctor/video.php', ['cmid' => $cmid, 'attempt' => $attemptid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('videotitle', 'quizaccess_examproctor', fullname($user)));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

// Close segments the recorder left open (page change, submit, lost connection) a minute ago or more.
$finished = !in_array($attempt->state, ['inprogress', 'overdue']);
video::merge_all($context, $attemptid, $finished ? 0 : time() - 60);
$segments = video::segments($context, $attemptid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('videotitle', 'quizaccess_examproctor', fullname($user)));
echo html_writer::div(
    html_writer::link(new moodle_url('/mod/quiz/accessrule/examproctor/monitor.php', ['cmid' => $cmid]),
        get_string('monitor_link', 'quizaccess_examproctor')) . ' · ' .
    html_writer::link(new moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $cmid, 'attempt' => $attemptid]),
        get_string('reportdetail', 'quizaccess_examproctor', fullname($user))),
    'mb-3');

if (!$segments) {
    echo $OUTPUT->notification(get_string('novideo', 'quizaccess_examproctor'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$totalbytes = array_sum(array_column($segments, 'bytes'));
echo html_writer::tag('p', get_string('videosummary', 'quizaccess_examproctor', (object) [
    'segments' => count($segments),
    'from' => userdate($segments[0]['start'], get_string('strftimedatetimeshort', 'core_langconfig')),
    'size' => display_size($totalbytes),
]), ['class' => 'text-muted']);

echo $OUTPUT->render_from_template('quizaccess_examproctor/video', [
    'firsturl' => $segments[0]['url'],
    'segments' => array_map(fn($s, $i) => $s + [
        'index' => $i,
        'label' => userdate($s['start'], get_string('strftimetime', 'core_langconfig')),
    ], $segments, array_keys($segments)),
]);
$PAGE->requires->js_call_amd('quizaccess_examproctor/video', 'init');
echo $OUTPUT->footer();
