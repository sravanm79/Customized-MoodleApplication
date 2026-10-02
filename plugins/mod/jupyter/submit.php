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
 * Copy the user's saved notebook from JupyterHub into Moodle as their submission.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/jupyter/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'jupyter');
$jupyter = $DB->get_record('jupyter', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/jupyter:submit', $context);

$viewurl = new moodle_url('/mod/jupyter/view.php', ['id' => $cm->id]);
$path = jupyter_notebook_path($cm, $jupyter->name);

try {
    $client = new \mod_jupyter\hub_client();
    $json = $client->fetch_notebook(\mod_jupyter\hub_client::username($USER->id), $path);
} catch (moodle_exception $e) {
    redirect($viewurl, get_string('submitfailed', 'mod_jupyter', $e->getMessage()), null,
        \core\output\notification::NOTIFY_ERROR);
}

$now = time();
$submission = $DB->get_record('jupyter_submissions', ['jupyter' => $jupyter->id, 'userid' => $USER->id]);
if ($submission) {
    $submission->timemodified = $now;
    $DB->update_record('jupyter_submissions', $submission);
} else {
    $submission = (object) [
        'jupyter' => $jupyter->id,
        'userid' => $USER->id,
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    $submission->id = $DB->insert_record('jupyter_submissions', $submission);
}

$fs = get_file_storage();
$fs->delete_area_files($context->id, 'mod_jupyter', 'submission', $submission->id);
$fs->create_file_from_string([
    'contextid' => $context->id,
    'component' => 'mod_jupyter',
    'filearea' => 'submission',
    'itemid' => $submission->id,
    'filepath' => '/',
    'filename' => $path,
    'userid' => $USER->id,
], $json);

redirect($viewurl, get_string('submissionsaved', 'mod_jupyter'), null, \core\output\notification::NOTIFY_SUCCESS);
