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
 * LLM grading status for one assignment, with "grade with LLM" actions.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);

$url = new moodle_url('/local/llmgrader/report.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($cm->name) . ': ' . get_string('llmgrading', 'local_llmgrader'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$assign = new assign($context, $cm, $course);
$instance = $assign->get_instance();

// Queue submitted notebooks: all that have no job yet, or one specific student again.
if ($action === 'queue' && confirm_sesskey()) {
    $onlyuser = optional_param('userid', 0, PARAM_INT);
    $params = ['assignment' => $instance->id, 'latest' => 1, 'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED];
    if ($onlyuser) {
        $params['userid'] = $onlyuser;
    }
    $count = 0;
    foreach ($DB->get_records('assign_submission', $params) as $submission) {
        if (empty($submission->userid) || !($file = \local_llmgrader\observer::find_notebook($context->id, $submission->id))) {
            continue;
        }
        $exists = $DB->record_exists('local_llmgrader_job', ['submissionid' => $submission->id,
            'contenthash' => $file->get_contenthash(), 'attemptnumber' => $submission->attemptnumber]);
        if ($onlyuser || !$exists) {
            \local_llmgrader\jobs::queue($course->id, $cm->id, $submission, $file, (bool) $onlyuser);
            $count++;
        }
    }
    redirect($url, get_string('queued', 'local_llmgrader', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cm->name) . ': ' . get_string('llmgrading', 'local_llmgrader'));

if (!get_config('local_llmgrader', 'enabled')) {
    echo $OUTPUT->notification(get_string('disabled', 'local_llmgrader'), 'warning');
}
if ($instance->grade <= 0) {
    echo $OUTPUT->notification(get_string('error_pointgrade', 'local_llmgrader'), 'warning');
}
echo html_writer::tag('p', get_string('reportintro', 'local_llmgrader'));

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url($url, ['action' => 'queue']), get_string('queueall', 'local_llmgrader')) . ' ' .
    $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']),
        get_string('gotograding', 'local_llmgrader'), 'get'),
    'mb-3');

// Latest job per student.
$jobs = $DB->get_records('local_llmgrader_job', ['cmid' => $cm->id], 'timecreated DESC, id DESC');
$latest = [];
foreach ($jobs as $job) {
    $latest[$job->userid] ??= $job;
}

if (!$latest) {
    echo $OUTPUT->notification(get_string('nojobs', 'local_llmgrader'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('fullname'), get_string('file'), get_string('status'), get_string('grade'),
    get_string('details', 'local_llmgrader'), ''];
$table->attributes['class'] = 'generaltable';
$users = user_get_users_by_id(array_keys($latest));
foreach ($latest as $userid => $job) {
    $status = get_string('status_' . $job->status, 'local_llmgrader');
    $badge = [
        'applied' => 'bg-success', 'evaluated' => 'bg-info', 'queued' => 'bg-secondary', 'failed' => 'bg-danger',
    ][$job->status] ?? 'bg-warning text-dark';
    $grade = $job->appliedgrade !== null
        ? format_float($job->appliedgrade, -1) . ' / ' . format_float($instance->grade, -1)
            . html_writer::tag('div', '(' . format_float($job->score, -1) . ' / ' . format_float($job->maxscore, -1)
            . ' ' . get_string('marks', 'local_llmgrader') . ')', ['class' => 'small text-muted'])
        : '—';

    $details = '';
    if ($job->feedback) {
        $details .= print_collapsible_region($job->feedback, '', 'llmjob' . $job->id,
            get_string('showfeedback', 'local_llmgrader'), '', true, true);
    }
    if ($job->error) {
        $details .= html_writer::div(s($job->error), 'small text-danger');
    }
    if ($job->metadatajson && ($meta = json_decode($job->metadatajson))) {
        $details .= html_writer::div(get_string('metadata', 'local_llmgrader', $meta), 'small text-muted');
    }

    $regrade = $OUTPUT->single_button(new moodle_url($url, ['action' => 'queue', 'userid' => $userid]),
        get_string('regrade', 'local_llmgrader'), 'post', ['type' => 'secondary']);

    $table->data[] = [
        isset($users[$userid]) ? fullname($users[$userid]) : $userid,
        s($job->filename),
        html_writer::span($status, 'badge ' . $badge) . html_writer::div(userdate($job->timemodified,
            get_string('strftimedatetimeshort')), 'small text-muted'),
        $grade,
        $details,
        $job->status === 'queued' ? '' : $regrade,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
