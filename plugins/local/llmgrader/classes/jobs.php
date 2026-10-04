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

/**
 * Creates evaluation jobs and queues the background task for them.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs {

    /** Statuses after which a job is never touched again. */
    const FINAL_STATUSES = ['applied', 'draft', 'rejected', 'evaluated', 'stale', 'superseded', 'failed',
        'skipped_human_graded'];

    /**
     * Create (or re-use) the job for this exact content and queue it.
     *
     * @param int $courseid
     * @param int $cmid
     * @param \stdClass $submission assign_submission record
     * @param array $content From submission_content::extract().
     * @param bool $force Evaluate again even if this exact content was already evaluated.
     * @return int Job id
     */
    public static function queue(int $courseid, int $cmid, \stdClass $submission, array $content, bool $force = false): int {
        global $DB;

        $key = sha1($submission->id . '|' . $submission->attemptnumber . '|' . $content['hash']);
        $now = time();
        $job = $DB->get_record('local_llmgrader_job', ['idempotencykey' => $key]);

        if ($job && !$force && $job->status !== 'failed') {
            return $job->id; // Same content already queued or evaluated.
        }

        // Older waiting jobs and unreviewed drafts for this submission no longer matter.
        [$insql, $params] = $DB->get_in_or_equal(['queued', 'draft'], SQL_PARAMS_NAMED);
        $params['submissionid'] = $submission->id;
        $params['key'] = $key;
        $DB->set_field_select('local_llmgrader_job', 'status', 'superseded',
            "submissionid = :submissionid AND idempotencykey <> :key AND status $insql", $params);

        if ($job) {
            $job->status = 'queued';
            $job->error = null;
            $job->tries = 0;
            $job->reviewerid = null;
            $job->timereviewed = null;
            $job->timemodified = $now;
            $DB->update_record('local_llmgrader_job', $job);
        } else {
            $job = (object) [
                'idempotencykey' => $key,
                'courseid' => $courseid,
                'cmid' => $cmid,
                'assignid' => $submission->assignment,
                'submissionid' => $submission->id,
                'attemptnumber' => $submission->attemptnumber,
                'userid' => $submission->userid,
                'fileid' => $content['fileid'],
                'contenthash' => $content['hash'],
                'filename' => $content['filename'],
                'status' => 'queued',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $job->id = $DB->insert_record('local_llmgrader_job', $job);
        }

        $task = new task\evaluate_submission();
        $task->set_custom_data(['jobid' => (int) $job->id]);
        $task->set_userid((int) get_config('local_llmgrader', 'graderuserid'));
        \core\task\manager::queue_adhoc_task($task, true);

        return $job->id;
    }

    /**
     * Queues the submitted work of an assignment: "Assign to LLM", and the close trigger.
     *
     * @param \stdClass $course
     * @param \cm_info|\stdClass $cm
     * @param int $userid Only this student, evaluated again even if already done (0: everyone not yet evaluated).
     * @return int Submissions queued.
     */
    public static function queue_assignment(\stdClass $course, $cm, int $userid = 0): int {
        global $DB;
        $context = \context_module::instance($cm->id);
        $params = ['assignment' => $cm->instance, 'latest' => 1, 'status' => 'submitted'];
        if ($userid) {
            $params['userid'] = $userid;
        }
        // Only current students: not deleted, suspended or unenrolled users' leftover submissions.
        $students = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.id', null, 0, 0, true);
        $count = 0;
        foreach ($DB->get_records('assign_submission', $params) as $submission) {
            // Group submissions are out of scope for now.
            if (empty($submission->userid) || !isset($students[$submission->userid])) {
                continue;
            }
            $content = submission_content::extract($context, $submission, review::maxchars());
            if (!$content) {
                continue;
            }
            $done = $DB->record_exists('local_llmgrader_job', ['idempotencykey' =>
                sha1($submission->id . '|' . $submission->attemptnumber . '|' . $content['hash'])]);
            if ($userid || !$done) {
                self::queue($course->id, $cm->id, $submission, $content, (bool) $userid);
                $count++;
            }
        }
        return $count;
    }
}
