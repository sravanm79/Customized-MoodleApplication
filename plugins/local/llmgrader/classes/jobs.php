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
    const FINAL_STATUSES = ['applied', 'evaluated', 'stale', 'superseded', 'failed', 'skipped_human_graded'];

    /**
     * Create (or re-use) the job for this exact notebook and queue it.
     *
     * @param int $courseid
     * @param int $cmid
     * @param \stdClass $submission assign_submission record
     * @param \stored_file $file The notebook
     * @param bool $force Re-evaluate even if this exact content was already evaluated
     * @return int Job id
     */
    public static function queue(int $courseid, int $cmid, \stdClass $submission, \stored_file $file, bool $force = false): int {
        global $DB;

        $key = sha1($submission->id . '|' . $submission->attemptnumber . '|' . $file->get_contenthash());
        $now = time();
        $job = $DB->get_record('local_llmgrader_job', ['idempotencykey' => $key]);

        if ($job && !$force && $job->status !== 'failed') {
            return $job->id; // Same content already queued or evaluated.
        }

        // Older jobs for this submission no longer matter.
        [$insql, $params] = $DB->get_in_or_equal(['queued'], SQL_PARAMS_NAMED);
        $params['submissionid'] = $submission->id;
        $params['key'] = $key;
        $DB->set_field_select('local_llmgrader_job', 'status', 'superseded',
            "submissionid = :submissionid AND idempotencykey <> :key AND status $insql", $params);

        if ($job) {
            $job->status = 'queued';
            $job->error = null;
            $job->tries = 0;
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
                'fileid' => $file->get_id(),
                'contenthash' => $file->get_contenthash(),
                'filename' => $file->get_filename(),
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
}
