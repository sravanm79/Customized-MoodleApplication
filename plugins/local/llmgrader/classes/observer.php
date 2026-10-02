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
 * Queues an LLM evaluation when a student submits a notebook. Never calls the LLM itself.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle a final submission.
     *
     * @param \mod_assign\event\assessable_submitted $event
     */
    public static function assessable_submitted(\mod_assign\event\assessable_submitted $event): void {
        if (!get_config('local_llmgrader', 'enabled')) {
            return;
        }
        $submission = $event->get_record_snapshot('assign_submission', $event->objectid);
        // Group submissions are out of scope for now.
        if (empty($submission->userid)) {
            return;
        }
        $file = self::find_notebook($event->contextid, $submission->id);
        if (!$file) {
            return;
        }
        $submission->assignment = (int) $submission->assignment;
        jobs::queue($event->courseid, $event->contextinstanceid, $submission, $file);
    }

    /**
     * First .ipynb file in a submission.
     *
     * @param int $contextid
     * @param int $submissionid
     * @return \stored_file|null
     */
    public static function find_notebook(int $contextid, int $submissionid): ?\stored_file {
        $files = get_file_storage()->get_area_files($contextid, 'assignsubmission_file', 'submission_files',
            $submissionid, 'id', false);
        foreach ($files as $file) {
            if (strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'ipynb') {
                return $file;
            }
        }
        return null;
    }
}
