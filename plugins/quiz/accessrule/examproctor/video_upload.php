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
 * Receives one chunk of webcam video for the current user's in-progress attempt (POST, multipart).
 *
 * Returns JSON {status: ok | stop}. "stop" tells the recorder the attempt is over or recording is off.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');

use mod_quiz\quiz_attempt;
use quizaccess_examproctor\local\video;

$attemptid = required_param('attemptid', PARAM_INT);
$segment = required_param('segment', PARAM_INT);
$seq = required_param('seq', PARAM_INT);
$format = required_param('format', PARAM_ALPHANUM);
$final = optional_param('final', 0, PARAM_BOOL);

header('Content-Type: application/json');

$attemptobj = quiz_attempt::create($attemptid);
$context = context_module::instance($attemptobj->get_cmid());
require_login($attemptobj->get_course(), false, $attemptobj->get_cm(), false, true);
require_sesskey();
if ((int) $attemptobj->get_userid() !== (int) $USER->id) {
    throw new moodle_exception('notyourattempt', 'quiz');
}

$recordvideo = $DB->get_field('quizaccess_examproctor', 'recordvideo', ['quizid' => $attemptobj->get_quizid()]);
$active = in_array($attemptobj->get_state(), [quiz_attempt::IN_PROGRESS, quiz_attempt::OVERDUE]);
if (!$recordvideo || !$active) {
    // Late chunks of a just-submitted attempt still close their segment.
    if ($final) {
        video::merge_segment($context, $attemptid, $segment);
    }
    echo json_encode(['status' => 'stop']);
    exit;
}

$file = $_FILES['chunk'] ?? null;
if (!isset(video::FORMATS[$format]) || $seq < 0 || $seq > 9999 || $segment <= 0 || !$file
        || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > video::MAX_CHUNK_BYTES
        || !is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}
// The segment id is the recorder's start time; refuse ids that are not within this attempt.
if ($segment < ($attemptobj->get_attempt()->timestart - 60) * 1000 || $segment > (time() + 60) * 1000) {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}

video::save_chunk($context, $attemptid, (int) $USER->id, $segment, $seq, $format, $file['tmp_name'], (bool) $final);
echo json_encode(['status' => 'ok']);
