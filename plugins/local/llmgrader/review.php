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
 * Review one LLM draft: see the per-criterion breakdown, edit the grade and feedback, then approve (released to the
 * student) or reject (nothing is written).
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->libdir . '/formslib.php');

use core\output\notification;
use local_llmgrader\review;

$jobid = required_param('id', PARAM_INT);
$job = $DB->get_record('local_llmgrader_job', ['id' => $jobid], '*', MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_cmid($job->cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);

$url = new moodle_url('/local/llmgrader/review.php', ['id' => $job->id]);
$reporturl = new moodle_url('/local/llmgrader/report.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('reviewdraft', 'local_llmgrader'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

if ($job->status !== 'draft') {
    redirect($reporturl, get_string('notadraft', 'local_llmgrader'), null, notification::NOTIFY_WARNING);
}
$assign = new assign($context, $cm, $course);
$maxgrade = (float) $assign->get_instance()->grade;
$student = core_user::get_user($job->userid, '*', MUST_EXIST);

/**
 * Draft review form: grade, feedback, approve / reject.
 */
class local_llmgrader_review_form extends moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('float', 'grade', get_string('gradeoutof', 'local_llmgrader', $this->_customdata['max']));
        $mform->addRule('grade', null, 'required', null, 'client');
        $mform->addElement('editor', 'feedback', get_string('feedbackforstudent', 'local_llmgrader'), ['rows' => 12]);
        $mform->setType('feedback', PARAM_RAW);
        $buttons = [
            $mform->createElement('submit', 'approve', get_string('approverelease', 'local_llmgrader')),
            $mform->createElement('submit', 'reject', get_string('reject', 'local_llmgrader')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['reject']) && ($data['grade'] < 0 || $data['grade'] > $this->_customdata['max'])) {
            $errors['grade'] = get_string('gradeoutofrange', 'local_llmgrader', $this->_customdata['max']);
        }
        return $errors;
    }
}

$form = new local_llmgrader_review_form($url, ['max' => format_float($maxgrade, -1)]);
$form->set_data(['id' => $job->id, 'grade' => format_float($job->appliedgrade, 2, false),
    'feedback' => ['text' => $job->feedback, 'format' => FORMAT_HTML]]);

if ($form->is_cancelled()) {
    redirect($reporturl);
}
if ($data = $form->get_data()) {
    if (!empty($data->reject)) {
        review::reject($job, (int) $USER->id);
        redirect($reporturl, get_string('rejected', 'local_llmgrader'), null, notification::NOTIFY_INFO);
    }
    if ($reason = review::stale_reason($job, $assign, false)) {
        $DB->update_record('local_llmgrader_job', (object) ['id' => $job->id, 'status' => 'stale', 'error' => $reason,
            'timemodified' => time()]);
        redirect($reporturl, $reason, null, notification::NOTIFY_ERROR);
    }
    review::apply($job, $assign, (float) $data->grade, $data->feedback['text'], (int) $USER->id);
    redirect($reporturl, get_string('approved', 'local_llmgrader'), null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewdraftfor', 'local_llmgrader', (object) ['name' => fullname($student),
    'assignment' => format_string($cm->name)]));
echo html_writer::tag('p', html_writer::link(new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grader',
    'userid' => $job->userid]), get_string('viewsubmission', 'local_llmgrader', s($job->filename))));

// What the LLM suggested, criterion by criterion.
$result = json_decode((string) $job->resultjson, true) ?: ['tasks' => []];
$table = new html_table();
$table->head = [get_string('criterion', 'local_llmgrader'), get_string('marks', 'local_llmgrader'),
    get_string('comment', 'local_llmgrader')];
$table->attributes['class'] = 'generaltable';
foreach ($result['tasks'] as $t) {
    $table->data[] = [s($t['task']), format_float($t['awarded'], -1) . ' / ' . format_float($t['out_of'], -1), s($t['comment'])];
}
$table->data[] = [html_writer::tag('strong', get_string('total')), html_writer::tag('strong',
    format_float($job->score, -1) . ' / ' . format_float($job->maxscore, -1)), get_string('scaledto', 'local_llmgrader',
    format_float($job->appliedgrade, -1) . ' / ' . format_float($maxgrade, -1))];
echo $OUTPUT->heading(get_string('llmsuggestion', 'local_llmgrader'), 3);
echo html_writer::table($table);
if ($job->metadatajson && ($meta = json_decode($job->metadatajson))) {
    echo html_writer::div(get_string('metadata', 'local_llmgrader', $meta), 'small text-muted mb-3');
}
$form->display();
echo $OUTPUT->footer();
