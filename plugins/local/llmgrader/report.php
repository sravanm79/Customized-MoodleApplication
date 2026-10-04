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
 * LLM grading of one assignment: "Assign to LLM", and the review of drafts (approve, edit, reject) before anything
 * reaches the students.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');

use core\output\notification;
use local_llmgrader\assignment_config;
use local_llmgrader\jobs;
use local_llmgrader\review;

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
$config = assignment_config::get($cm->id);

/**
 * Approves one draft as suggested; returns an error message if it can no longer be applied.
 *
 * @param stdClass $job
 * @param assign $assign
 * @return string|null
 */
function local_llmgrader_approve(stdClass $job, assign $assign): ?string {
    global $DB, $USER;
    if ($reason = review::stale_reason($job, $assign, false)) {
        $DB->update_record('local_llmgrader_job', (object) ['id' => $job->id, 'status' => 'stale', 'error' => $reason,
            'timemodified' => time()]);
        return $reason;
    }
    review::apply($job, $assign, (float) $job->appliedgrade, $job->feedback, (int) $USER->id);
    return null;
}

/**
 * Badge colour of a status.
 *
 * @param string $status
 * @return string
 */
function local_llmgrader_badge(string $status): string {
    return ['applied' => 'bg-success', 'draft' => 'bg-primary', 'queued' => 'bg-secondary', 'failed' => 'bg-danger',
        'rejected' => 'bg-dark'][$status] ?? 'bg-warning text-dark';
}

if ($action && confirm_sesskey()) {
    if ($action === 'queue') {
        // "Assign to LLM": every submitted, not yet evaluated submission, or one student again.
        $count = jobs::queue_assignment($course, $cm, optional_param('userid', 0, PARAM_INT));
        redirect($url, get_string('queued', 'local_llmgrader', $count), null, notification::NOTIFY_SUCCESS);
    }
    if ($action === 'approveall') {
        $approved = 0;
        $problems = 0;
        foreach ($DB->get_records('local_llmgrader_job', ['cmid' => $cm->id, 'status' => 'draft']) as $job) {
            local_llmgrader_approve($job, $assign) === null ? $approved++ : $problems++;
        }
        redirect($url, get_string('approvedall', 'local_llmgrader', (object) ['approved' => $approved, 'stale' => $problems]),
            null, $problems ? notification::NOTIFY_WARNING : notification::NOTIFY_SUCCESS);
    }
    if ($action === 'approve' || $action === 'reject') {
        $job = $DB->get_record('local_llmgrader_job', ['id' => required_param('jobid', PARAM_INT), 'cmid' => $cm->id,
            'status' => 'draft'], '*', MUST_EXIST);
        if ($action === 'reject') {
            review::reject($job, (int) $USER->id);
            redirect($url, get_string('rejected', 'local_llmgrader'), null, notification::NOTIFY_INFO);
        }
        $error = local_llmgrader_approve($job, $assign);
        redirect($url, $error ?? get_string('approved', 'local_llmgrader'), null,
            $error ? notification::NOTIFY_ERROR : notification::NOTIFY_SUCCESS);
    }
}

// Latest job per student.
$latest = [];
foreach ($DB->get_records('local_llmgrader_job', ['cmid' => $cm->id], 'timecreated DESC, id DESC') as $job) {
    $latest[$job->userid] ??= $job;
}
$counts = array_count_values(array_column($latest, 'status'));
$drafts = $counts['draft'] ?? 0;

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cm->name) . ': ' . get_string('llmgrading', 'local_llmgrader'));

if (!get_config('local_llmgrader', 'enabled')) {
    echo $OUTPUT->notification(get_string('disabled', 'local_llmgrader'), 'warning');
}
if ($instance->grade <= 0) {
    echo $OUTPUT->notification(get_string('error_pointgrade', 'local_llmgrader'), 'warning');
}
echo html_writer::tag('p', get_string($config->requirereview ? 'reportintro_review' : 'reportintro_auto', 'local_llmgrader'));
echo html_writer::tag('p', get_string('settingssummary', 'local_llmgrader', (object) [
    'trigger' => get_string('trigger_' . $config->triggermode, 'local_llmgrader'),
    'review' => get_string($config->requirereview ? 'yes' : 'no'),
    'rubric' => get_string(trim($config->rubric) !== '' ? 'yes' : 'no'),
    'reference' => get_string(trim($config->reference) !== '' ? 'yes' : 'no'),
]), ['class' => 'small text-muted']);

$buttons = $OUTPUT->single_button(new moodle_url($url, ['action' => 'queue']), get_string('assigntollm', 'local_llmgrader'),
    'post', ['type' => 'primary']);
if ($drafts) {
    $buttons .= $OUTPUT->single_button(new moodle_url($url, ['action' => 'approveall']),
        get_string('approveall', 'local_llmgrader', $drafts), 'post', ['type' => 'primary']);
}
$buttons .= $OUTPUT->single_button(new moodle_url('/local/llmgrader/settings_assign.php', ['id' => $cm->id]),
    get_string('assignsettings', 'local_llmgrader'), 'get');
$buttons .= $OUTPUT->single_button(new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']),
    get_string('gotograding', 'local_llmgrader'), 'get');
echo html_writer::div($buttons, 'd-flex flex-wrap gap-2 mb-3');

if (!$latest) {
    echo $OUTPUT->notification(get_string('nojobs', 'local_llmgrader'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Counts per status.
$chips = '';
foreach ($counts as $status => $count) {
    $chips .= html_writer::span(get_string('status_' . $status, 'local_llmgrader') . ': ' . $count,
        'badge rounded-pill ' . local_llmgrader_badge($status) . ' me-1');
}
echo html_writer::div($chips, 'mb-3');

$table = new html_table();
$table->head = [get_string('fullname'), get_string('submission', 'local_llmgrader'), get_string('status'),
    get_string('suggestedgrade', 'local_llmgrader'), get_string('details', 'local_llmgrader'), ''];
$table->attributes['class'] = 'generaltable local-llmgrader-review';
$users = user_get_users_by_id(array_keys($latest));
foreach ($latest as $userid => $job) {
    $grade = $job->appliedgrade !== null
        ? html_writer::tag('strong', format_float($job->appliedgrade, -1) . ' / ' . format_float($instance->grade, -1))
            . html_writer::div('(' . format_float($job->score, -1) . ' / ' . format_float($job->maxscore, -1)
            . ' ' . get_string('marks', 'local_llmgrader') . ')', 'small text-muted')
        : '—';

    $details = '';
    if ($job->feedback) {
        $details .= print_collapsible_region($job->feedback, '', 'llmjob' . $job->id,
            get_string('showfeedback', 'local_llmgrader'), '', $job->status !== 'draft', true);
    }
    if ($job->error) {
        $details .= html_writer::div(s($job->error), 'small text-danger');
    }
    if ($job->metadatajson && ($meta = json_decode($job->metadatajson))) {
        $details .= html_writer::div(get_string('metadata', 'local_llmgrader', $meta), 'small text-muted');
    }
    if ($job->reviewerid) {
        $reviewer = core_user::get_user($job->reviewerid);
        $details .= html_writer::div(get_string('reviewedby', 'local_llmgrader', (object) [
            'name' => $reviewer ? fullname($reviewer) : '?',
            'time' => userdate($job->timereviewed, get_string('strftimedatetimeshort')),
        ]), 'small text-muted');
    }

    $actions = '';
    if ($job->status === 'draft') {
        $actions .= $OUTPUT->single_button(new moodle_url($url, ['action' => 'approve', 'jobid' => $job->id]),
            get_string('approve', 'local_llmgrader'), 'post', ['type' => 'primary']);
        $actions .= $OUTPUT->single_button(new moodle_url('/local/llmgrader/review.php', ['id' => $job->id]),
            get_string('reviewedit', 'local_llmgrader'), 'get');
        $actions .= $OUTPUT->single_button(new moodle_url($url, ['action' => 'reject', 'jobid' => $job->id]),
            get_string('reject', 'local_llmgrader'), 'post', ['type' => 'secondary']);
    } else if ($job->status !== 'queued') {
        $actions .= $OUTPUT->single_button(new moodle_url($url, ['action' => 'queue', 'userid' => $userid]),
            get_string('regrade', 'local_llmgrader'), 'post', ['type' => 'secondary']);
    }

    $graderurl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grader', 'userid' => $userid]);
    $table->data[] = [
        isset($users[$userid]) ? fullname($users[$userid]) : $userid,
        html_writer::link($graderurl, s($job->filename)),
        html_writer::span(get_string('status_' . $job->status, 'local_llmgrader'), 'badge ' . local_llmgrader_badge($job->status))
            . html_writer::div(userdate($job->timemodified, get_string('strftimedatetimeshort')), 'small text-muted'),
        $grade,
        $details,
        html_writer::div($actions, 'd-flex flex-wrap gap-1'),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
