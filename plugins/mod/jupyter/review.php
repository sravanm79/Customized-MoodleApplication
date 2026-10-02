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
 * Open a student's submitted notebook in the grader's own JupyterLab so it can be run.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/jupyter/lib.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'jupyter');
$jupyter = $DB->get_record('jupyter', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jupyter:grade', $context);

$student = core_user::get_user($userid, '*', MUST_EXIST);
$submission = $DB->get_record('jupyter_submissions', ['jupyter' => $jupyter->id, 'userid' => $userid], '*', MUST_EXIST);
$files = get_file_storage()->get_area_files($context->id, 'mod_jupyter', 'submission', $submission->id, 'filename', false);
if (!$file = reset($files)) {
    throw new moodle_exception('nonotebook', 'mod_jupyter');
}

$PAGE->set_url('/mod/jupyter/review.php', ['id' => $cm->id, 'userid' => $userid]);
$PAGE->set_title(format_string($jupyter->name) . ': ' . fullname($student));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth-off');

\core\session\manager::write_close();

$notebookurl = null;
$launcherror = null;
try {
    $client = new \mod_jupyter\hub_client();
    $hubuser = \mod_jupyter\hub_client::username($USER->id);
    // Always refresh the copy so the grader sees the latest submission.
    $path = 'review_' . $cm->id . '/' . clean_filename(fullname($student)) . '_' . $userid . '.ipynb';
    $path = str_replace(' ', '_', $path);
    $client->ensure_server($hubuser);
    $client->put_notebook($hubuser, $path, $file->get_content());
    $notebookurl = $client->notebook_url($hubuser, $path);
} catch (moodle_exception $e) {
    $launcherror = $e->getMessage();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewing', 'mod_jupyter', fullname($student)));
echo html_writer::div(html_writer::link(new moodle_url('/mod/jupyter/submissions.php', ['id' => $cm->id]),
    get_string('backtosubmissions', 'mod_jupyter')), 'mb-3');

if ($launcherror) {
    echo $OUTPUT->notification(get_string('launchfailed', 'mod_jupyter', $launcherror), 'error');
} else {
    echo html_writer::tag('iframe', '', [
        'src' => $notebookurl,
        'class' => 'mod-jupyter-frame',
        'title' => fullname($student),
        'allow' => 'clipboard-read; clipboard-write; fullscreen',
    ]);
}

echo $OUTPUT->footer();
