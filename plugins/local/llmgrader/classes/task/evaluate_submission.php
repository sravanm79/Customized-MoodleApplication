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

namespace local_llmgrader\task;

use local_llmgrader\llm_client;
use local_llmgrader\permanent_failure;

/**
 * Grade one submitted notebook with the LLM and release the grade and feedback to the student straight away.
 *
 * Runs from cron as the "LLM Grader" user. Throwing reschedules the task with backoff (transient errors);
 * permanent problems are recorded on the job and the task returns normally.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluate_submission extends \core\task\adhoc_task {

    /** Give up on transient errors after this many tries. */
    const MAX_TRIES = 3;

    /**
     * Run the evaluation.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $jobid = $this->get_custom_data()->jobid;
        $job = $DB->get_record('local_llmgrader_job', ['id' => $jobid]);
        if (!$job || $job->status !== 'queued') {
            return;
        }

        [$course, $cm] = get_course_and_cm_from_cmid($job->cmid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);
        $instance = $assign->get_instance();

        if ($reason = $this->stale_reason($job, $assign)) {
            $this->finish($job, 'stale', ['error' => $reason]);
            return;
        }
        if ($instance->grade <= 0) {
            $this->finish($job, 'failed', ['error' => get_string('error_pointgrade', 'local_llmgrader')]);
            return;
        }

        $file = get_file_storage()->get_file_by_id($job->fileid);
        if (!$file) {
            $this->finish($job, 'stale', ['error' => 'The submitted file no longer exists.']);
            return;
        }

        $job->tries++;
        $DB->set_field('local_llmgrader_job', 'tries', $job->tries, ['id' => $job->id]);
        try {
            $result = llm_client::grade($file->get_content(), (float) $instance->grade);
        } catch (permanent_failure $e) {
            $this->finish($job, 'failed', ['error' => $e->a ?? $e->getMessage()]);
            return;
        } catch (\moodle_exception $e) {
            if ($job->tries >= self::MAX_TRIES) {
                $this->finish($job, 'failed', ['error' => $e->getMessage()]);
                return;
            }
            $DB->set_field('local_llmgrader_job', 'error', $e->getMessage(), ['id' => $job->id]);
            throw $e; // Retried by the task manager with backoff.
        }

        $grade = round($result['score'] / $result['maxscore'] * $instance->grade, 2);
        $feedback = $this->feedback_html($result, $instance->grade, $grade);
        $fields = [
            'score' => $result['score'],
            'maxscore' => $result['maxscore'],
            'appliedgrade' => $grade,
            'feedback' => $feedback,
            'resultjson' => json_encode(['tasks' => $result['tasks'], 'feedback' => $result['feedback']]),
            'metadatajson' => json_encode($result['metadata']),
            'timeevaluated' => time(),
            'error' => null,
        ];

        // The LLM call took a while: check again that nothing changed meanwhile.
        if ($reason = $this->stale_reason($job, $assign)) {
            $this->finish($job, 'stale', ['error' => $reason] + $fields);
            return;
        }
        $data = (object) [
            'grade' => $grade,
            'attemptnumber' => $job->attemptnumber,
            'sendstudentnotifications' => true,
            'addattempt' => false,
            'applytoall' => false,
            'assignfeedbackcomments_editor' => [
                'text' => $feedback,
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
        ];
        if ($instance->markingworkflow) {
            // No teacher review step: release immediately so the student sees the grade.
            $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
        }
        $assign->save_grade($job->userid, $data);
        $this->finish($job, 'applied', $fields);
    }

    /**
     * Why the job should not be applied any more, or null if it is still current.
     *
     * @param \stdClass $job
     * @param \assign $assign
     * @return string|null
     */
    private function stale_reason(\stdClass $job, \assign $assign): ?string {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id' => $job->submissionid]);
        if (!$submission || !$submission->latest || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED
                || (int) $submission->attemptnumber !== (int) $job->attemptnumber) {
            return 'The submission changed or was reverted to draft after it was queued.';
        }
        $file = \local_llmgrader\observer::find_notebook($assign->get_context()->id, $submission->id);
        if (!$file || $file->get_contenthash() !== $job->contenthash) {
            return 'The student submitted a different notebook after this one was queued.';
        }
        // Never overwrite a grade a teacher gave. "grader" alone isn't enough: reverting to draft or releasing also
        // sets it to the teacher. So a teacher's grade is one that differs from what the LLM itself last applied.
        $grade = $DB->get_record('assign_grades', ['assignment' => $job->assignid, 'userid' => $job->userid,
            'attemptnumber' => $job->attemptnumber]);
        $graderid = (int) get_config('local_llmgrader', 'graderuserid');
        if ($grade && $grade->grade !== null && $grade->grade >= 0 && (int) $grade->grader !== $graderid
                && !$DB->record_exists_select('local_llmgrader_job',
                    'submissionid = :sid AND attemptnumber = :attempt AND status = :applied AND appliedgrade = :grade',
                    ['sid' => $job->submissionid, 'attempt' => $job->attemptnumber, 'applied' => 'applied',
                        'grade' => $grade->grade])) {
            $DB->set_field('local_llmgrader_job', 'status', 'skipped_human_graded', ['id' => $job->id]);
            return 'A teacher has already graded this attempt.';
        }
        return null;
    }

    /**
     * Feedback shown to the student (and the teacher).
     *
     * @param array $result
     * @param float $maxgrade
     * @param float $grade
     * @return string HTML
     */
    private function feedback_html(array $result, float $maxgrade, float $grade): string {
        $html = \html_writer::tag('p', s($result['feedback']));
        $items = '';
        foreach ($result['tasks'] as $t) {
            $icon = $t['awarded'] >= $t['out_of'] ? '✅' : ($t['awarded'] > 0 ? '🟡' : '❌');
            $items .= \html_writer::tag('li', $icon . ' ' . \html_writer::tag('strong', s($t['task']))
                . ' — ' . format_float($t['awarded'], -1) . '/' . format_float($t['out_of'], -1) . ': ' . s($t['comment']));
        }
        $html .= \html_writer::tag('ul', $items);
        $html .= \html_writer::tag('p', \html_writer::tag('em', get_string('feedbackfooter', 'local_llmgrader', (object) [
            'score' => format_float($result['score'], -1),
            'maxscore' => format_float($result['maxscore'], -1),
            'grade' => format_float($grade, -1),
            'maxgrade' => format_float($maxgrade, -1),
        ])));
        return $html;
    }

    /**
     * Store the final state of a job.
     *
     * @param \stdClass $job
     * @param string $status
     * @param array $fields
     */
    private function finish(\stdClass $job, string $status, array $fields = []): void {
        global $DB;
        $current = $DB->get_field('local_llmgrader_job', 'status', ['id' => $job->id]);
        $record = (object) ($fields + ['id' => $job->id, 'timemodified' => time()]);
        // stale_reason() may already have recorded skipped_human_graded.
        $record->status = $current === 'skipped_human_graded' ? $current : $status;
        $DB->update_record('local_llmgrader_job', $record);
    }
}
