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

use local_llmgrader\assignment_config;
use local_llmgrader\evaluator;
use local_llmgrader\permanent_failure;
use local_llmgrader\prompt_builder;
use local_llmgrader\review;
use local_llmgrader\submission_content;

/**
 * Evaluates one submission with the LLM: builds the prompt (assignment, rubric, reference solution, guidelines,
 * submission), calls the configured provider, and stores the suggested grade and feedback as a draft for the
 * teacher to approve, or, for assignments set to release without review, writes them to the assignment.
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

        $job = $DB->get_record('local_llmgrader_job', ['id' => $this->get_custom_data()->jobid]);
        if (!$job || $job->status !== 'queued') {
            return;
        }

        [$course, $cm] = get_course_and_cm_from_cmid($job->cmid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);
        $instance = $assign->get_instance();
        $config = assignment_config::get($cm->id);

        // Drafts don't overwrite anything, so a teacher's existing grade only blocks automatic release.
        if ($reason = review::stale_reason($job, $assign, !$config->requirereview)) {
            $this->finish($job, $this->stale_status($reason), ['error' => $reason]);
            return;
        }
        if ($instance->grade <= 0) {
            $this->finish($job, 'failed', ['error' => get_string('error_pointgrade', 'local_llmgrader')]);
            return;
        }
        $submission = $DB->get_record('assign_submission', ['id' => $job->submissionid], '*', MUST_EXIST);
        $content = submission_content::extract($context, $submission, review::maxchars());

        $job->tries++;
        $DB->set_field('local_llmgrader_job', 'tries', $job->tries, ['id' => $job->id]);
        try {
            $result = (new evaluator())->evaluate(prompt_builder::build($instance, $config, $content['text']));
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
        $fields = [
            'score' => $result['score'],
            'maxscore' => $result['maxscore'],
            'appliedgrade' => $grade,
            'feedback' => review::feedback_html($result, (float) $instance->grade, $grade),
            'resultjson' => json_encode(['tasks' => $result['tasks'], 'feedback' => $result['feedback']]),
            'metadatajson' => json_encode($result['metadata'] + ['condensedchars' => \core_text::strlen($content['text'])]),
            'timeevaluated' => time(),
            'error' => null,
        ];

        // The LLM call took a while: check again that nothing changed meanwhile.
        if ($reason = review::stale_reason($job, $assign, !$config->requirereview)) {
            $this->finish($job, $this->stale_status($reason), ['error' => $reason] + $fields);
            return;
        }
        if ($config->requirereview) {
            // Draft: nothing reaches the student until a teacher approves it.
            $this->finish($job, 'draft', $fields);
            return;
        }
        $this->finish($job, 'queued', $fields);
        review::apply($job, $assign, $grade, $fields['feedback'], 0);
    }

    /**
     * Status for a stale_reason(): a teacher's grade is recorded as such, anything else as outdated.
     *
     * @param string $reason
     * @return string
     */
    private function stale_status(string $reason): string {
        return $reason === get_string('stale_teachergraded', 'local_llmgrader') ? 'skipped_human_graded' : 'stale';
    }

    /**
     * Store the state of a job.
     *
     * @param \stdClass $job
     * @param string $status
     * @param array $fields
     */
    private function finish(\stdClass $job, string $status, array $fields = []): void {
        global $DB;
        $DB->update_record('local_llmgrader_job', (object) ($fields + ['id' => $job->id, 'status' => $status,
            'timemodified' => time()]));
    }
}
