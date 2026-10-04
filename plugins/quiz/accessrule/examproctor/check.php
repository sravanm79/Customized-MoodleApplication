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
 * Student device pre-check for a proctored quiz: camera and microphone, secure context, and (inside Safe Exam
 * Browser) the SEB config / browser exam keys. Run it in a normal browser first, then again inside SEB.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use mod_quiz\quiz_settings;
use quizaccess_examproctor\local\seb_diagnostics;

$cmid = required_param('cmid', PARAM_INT);
$ping = optional_param('ping', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:view', $context);

if ($ping) {
    // Round-trip test from the browser; no data beyond the server time.
    require_sesskey();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'time' => time()]);
    exit;
}

$quizobj = quiz_settings::create($cm->instance, $USER->id);
$quiz = $quizobj->get_quiz();

$url = new moodle_url('/mod/quiz/accessrule/examproctor/check.php', ['cmid' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('precheck_title', 'quizaccess_examproctor') . ' | ' . format_string($quiz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('precheck_title', 'quizaccess_examproctor'));
$PAGE->add_body_class('quizaccess-examproctor-precheck');

$diagnostics = new seb_diagnostics($quiz, $cm->id);
$sebmode = $diagnostics->seb_mode();
$camerarequired = $diagnostics->camera_proctoring_enabled();
$servercheck = $diagnostics->run(seb_diagnostics::origin(qualified_me()));

$labels = ['ok' => 'status_ok', 'warn' => 'status_warn', 'error' => 'status_error', 'info' => 'status_info'];
$servercheck = array_map(function($check) use ($labels) {
    $check['label'] = get_string($labels[$check['status']], 'quizaccess_examproctor');
    return $check;
}, $servercheck);

$strings = [];
foreach (['status_ok', 'status_warn', 'status_error', 'status_info', 'status_pending', 'status_skipped',
        'c_seb_yes', 'c_seb_no', 'c_seb_required_outside', 'c_seb_notrequired', 'c_secure_ok', 'c_secure_no',
        'c_media_ok', 'c_media_no', 'c_cam_ok', 'c_cam_dark', 'c_cam_notallowed', 'c_cam_notallowed_seb',
        'c_cam_notfound', 'c_cam_notreadable', 'c_cam_other', 'c_cam_notrequired', 'c_mic_ok', 'c_mic_silent',
        'c_mic_failed', 'c_keys_ok', 'c_keys_config_bad', 'c_keys_bek_bad', 'c_keys_noapi', 'c_keys_error',
        'c_keys_notinseb', 'c_keys_timeout', 'c_net_ok', 'c_net_slow', 'c_net_failed', 'summary_ready',
        'summary_warn', 'summary_notready', 'copied', 'camstart', 'camstop', 'mictest', 'mictesting'] as $key) {
    $strings[$key] = get_string('precheck_' . $key, 'quizaccess_examproctor');
}

$PAGE->requires->js_call_amd('quizaccess_examproctor/precheck', 'init', [[
    'cmid' => (int) $cm->id,
    'sebmode' => $sebmode,
    'camerarequired' => $camerarequired,
    'pingurl' => (new moodle_url($url, ['ping' => 1, 'sesskey' => sesskey()]))->out(false),
    'servercheck' => array_values($servercheck),
    'quizname' => format_string($quiz->name),
    'strings' => $strings,
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('quizaccess_examproctor/precheck', [
    'quizname' => format_string($quiz->name),
    'quizurl' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
    'requiresseb' => $sebmode > 0,
    'camerarequired' => $camerarequired,
    'servercheck' => array_values($servercheck),
]);
echo $OUTPUT->footer();
