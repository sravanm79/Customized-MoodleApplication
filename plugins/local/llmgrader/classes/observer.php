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
 * Queues an LLM evaluation when a student submits, for assignments set to run on submission. Never calls the LLM.
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
        if (assignment_config::get($event->contextinstanceid)->triggermode !== assignment_config::TRIGGER_SUBMIT) {
            return;
        }
        $submission = $event->get_record_snapshot('assign_submission', $event->objectid);
        // Group submissions are out of scope for now.
        if (empty($submission->userid)) {
            return;
        }
        $content = submission_content::extract(\context_module::instance($event->contextinstanceid), $submission,
            review::maxchars());
        if (!$content) {
            return;
        }
        $submission->assignment = (int) $submission->assignment;
        jobs::queue($event->courseid, $event->contextinstanceid, $submission, $content);
    }
}
