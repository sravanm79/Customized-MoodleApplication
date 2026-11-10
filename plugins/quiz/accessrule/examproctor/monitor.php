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
 * Live exam monitor: everyone writing the exam and their violations on one page, refreshed every 15 seconds.
 *
 * With fragment=1 only the monitor's content is returned (used by the refresh).
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use quizaccess_examproctor\local\monitor;

$cmid = required_param('cmid', PARAM_INT);
$fragment = optional_param('fragment', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:viewreports', $context);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

$url = new moodle_url('/mod/quiz/accessrule/examproctor/monitor.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('monitor_title', 'quizaccess_examproctor', format_string($quiz->name)));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

$data = (new monitor($quiz, $cm))->export();

if ($fragment) {
    echo $OUTPUT->render_from_template('quizaccess_examproctor/monitor_content', $data);
    die();
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('quizaccess_examproctor/monitor', $data);
$PAGE->requires->js_call_amd('quizaccess_examproctor/monitor', 'init', [[
    'url' => (new moodle_url($url, ['fragment' => 1]))->out(false),
    'interval' => 15000,
]]);
echo $OUTPUT->footer();
