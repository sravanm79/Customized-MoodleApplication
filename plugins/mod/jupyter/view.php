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
 * Show the user's notebook in JupyterLab, with a submit button.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/jupyter/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'jupyter');
$jupyter = $DB->get_record('jupyter', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jupyter:view', $context);

$PAGE->set_url('/mod/jupyter/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($jupyter->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth-off');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$cansubmit = has_capability('mod/jupyter:submit', $context);
$cangrade = has_capability('mod/jupyter:grade', $context);
$submission = $DB->get_record('jupyter_submissions', ['jupyter' => $jupyter->id, 'userid' => $USER->id]);

// Starting a server can take several seconds; don't hold the session lock meanwhile.
\core\session\manager::write_close();

$notebookurl = null;
$launcherror = null;
try {
    $client = new \mod_jupyter\hub_client();
    $hubuser = \mod_jupyter\hub_client::username($USER->id);
    $path = jupyter_notebook_path($cm, $jupyter->name);
    $client->ensure_server($hubuser);
    $client->ensure_notebook($hubuser, $path, jupyter_starter_notebook($context));
    $notebookurl = $client->notebook_url($hubuser, $path);
} catch (moodle_exception $e) {
    $launcherror = $e->getMessage();
}

echo $OUTPUT->header();

if ($cangrade) {
    $count = $DB->count_records('jupyter_submissions', ['jupyter' => $jupyter->id]);
    echo html_writer::div(
        $OUTPUT->single_button(new moodle_url('/mod/jupyter/submissions.php', ['id' => $cm->id]),
            get_string('viewsubmissions', 'mod_jupyter', $count), 'get'),
        'mb-3');
}

if ($submission) {
    $status = get_string('submittedon', 'mod_jupyter', userdate($submission->timemodified));
    if ($submission->grade !== null) {
        $grades = make_grades_menu($jupyter->grade);
        $status .= html_writer::empty_tag('br') . get_string('yourgrade', 'mod_jupyter',
            $grades[(int) $submission->grade] ?? format_float($submission->grade, 2));
        if ($submission->feedback !== null && $submission->feedback !== '') {
            $status .= html_writer::empty_tag('br') . get_string('feedback', 'mod_jupyter') . ': '
                . format_text($submission->feedback, FORMAT_PLAIN);
        }
    }
    echo $OUTPUT->notification($status, 'success', false);
} else if ($cansubmit) {
    echo $OUTPUT->notification(get_string('notsubmitted', 'mod_jupyter'), 'info', false);
}

if ($launcherror) {
    echo $OUTPUT->notification(get_string('launchfailed', 'mod_jupyter', $launcherror), 'error');
} else {
    echo html_writer::tag('iframe', '', [
        'src' => $notebookurl,
        'class' => 'mod-jupyter-frame',
        'title' => format_string($jupyter->name),
        'allow' => 'clipboard-read; clipboard-write; fullscreen',
    ]);

    if ($cansubmit) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/mod/jupyter/submit.php'),
            'class' => 'mod-jupyter-submit mt-3']);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= html_writer::tag('button', get_string($submission ? 'resubmit' : 'submit', 'mod_jupyter'),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        $form .= html_writer::span(get_string('savebeforesubmit', 'mod_jupyter'), 'ms-3 text-muted');
        $form .= html_writer::end_tag('form');
        echo $form;
    }
}

echo $OUTPUT->footer();
