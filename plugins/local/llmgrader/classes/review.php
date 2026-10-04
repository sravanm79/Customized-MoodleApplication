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

namespace local_llmgrader;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Turning an LLM result into a released grade: checks that the submission is still the one evaluated, writes the
 * grade and feedback to the assignment (releasing it under marking workflow), and records the teacher's decision.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review {
    /**
     * Why the job no longer matches the submission, or null if it still does.
     *
     * @param \stdClass $job
     * @param \assign $assign
     * @param bool $checkteacher Also refuse when a teacher has graded the attempt (automatic grading); a teacher
     *     approving a draft decides that themselves.
     * @return string|null
     */
    public static function stale_reason(\stdClass $job, \assign $assign, bool $checkteacher = true): ?string {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id' => $job->submissionid]);
        if (!$submission || !$submission->latest || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED
                || (int) $submission->attemptnumber !== (int) $job->attemptnumber) {
            return get_string('stale_changed', 'local_llmgrader');
        }
        $content = submission_content::extract($assign->get_context(), $submission, self::maxchars());
        if (!$content || $content['hash'] !== $job->contenthash) {
            return get_string('stale_content', 'local_llmgrader');
        }
        if (!$checkteacher) {
            return null;
        }
        // Never overwrite a teacher's grade. "grader" alone isn't enough (reverting to draft or releasing also sets
        // it), so a teacher's grade is one that differs from every grade this plugin applied for the attempt.
        $grade = $DB->get_record('assign_grades', ['assignment' => $job->assignid, 'userid' => $job->userid,
            'attemptnumber' => $job->attemptnumber]);
        $graderid = (int) get_config('local_llmgrader', 'graderuserid');
        if ($grade && $grade->grade !== null && $grade->grade >= 0 && (int) $grade->grader !== $graderid
                && !$DB->record_exists_select('local_llmgrader_job',
                    'submissionid = :sid AND attemptnumber = :attempt AND status = :applied AND appliedgrade = :grade',
                    ['sid' => $job->submissionid, 'attempt' => $job->attemptnumber, 'applied' => 'applied',
                        'grade' => $grade->grade])) {
            return get_string('stale_teachergraded', 'local_llmgrader');
        }
        return null;
    }

    /**
     * Writes the grade and feedback to the assignment and marks the job as released.
     *
     * Runs as the current user: the "LLM Grader" account in the background task, the teacher when they approve.
     *
     * @param \stdClass $job
     * @param \assign $assign
     * @param float $grade On the assignment's scale.
     * @param string $feedback HTML
     * @param int $reviewerid The approving teacher, 0 for automatic release.
     */
    public static function apply(\stdClass $job, \assign $assign, float $grade, string $feedback, int $reviewerid): void {
        global $DB;
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
        if ($assign->get_instance()->markingworkflow) {
            // Approval is the release step.
            $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
        }
        $assign->save_grade($job->userid, $data);
        $DB->update_record('local_llmgrader_job', (object) [
            'id' => $job->id,
            'status' => 'applied',
            'appliedgrade' => $grade,
            'feedback' => $feedback,
            'reviewerid' => $reviewerid ?: null,
            'timereviewed' => $reviewerid ? time() : null,
            'timemodified' => time(),
        ]);
    }

    /**
     * Discards a draft: nothing is written to the assignment.
     *
     * @param \stdClass $job
     * @param int $reviewerid
     */
    public static function reject(\stdClass $job, int $reviewerid): void {
        global $DB;
        $DB->update_record('local_llmgrader_job', (object) ['id' => $job->id, 'status' => 'rejected',
            'reviewerid' => $reviewerid, 'timereviewed' => time(), 'timemodified' => time()]);
    }

    /**
     * Feedback for the student: overall comment, per-criterion marks and comments, and a footer.
     *
     * @param array $result From evaluator::evaluate().
     * @param float $maxgrade
     * @param float $grade
     * @return string HTML
     */
    public static function feedback_html(array $result, float $maxgrade, float $grade): string {
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
     * Maximum characters of submission sent to the model.
     *
     * @return int
     */
    public static function maxchars(): int {
        return (int) (get_config('local_llmgrader', 'maxchars') ?: 16000);
    }
}
