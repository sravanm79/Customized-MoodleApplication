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
use local_llmgrader\jobs;

/**
 * "When submissions close" trigger: for assignments set to run on close, queues every submitted, not yet evaluated
 * submission once the cut-off date (or, without one, the due date) has passed. Runs again if the date is moved later.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_closed_assignments extends \core\task\scheduled_task {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_queueclosed', 'local_llmgrader');
    }

    /**
     * Run.
     */
    public function execute() {
        global $DB;
        if (!get_config('local_llmgrader', 'enabled')) {
            return;
        }
        $now = time();
        $configs = $DB->get_records('local_llmgrader_assign', ['triggermode' => assignment_config::TRIGGER_CLOSE]);
        foreach ($configs as $config) {
            $cm = get_coursemodule_from_id('assign', $config->cmid, 0, false, IGNORE_MISSING);
            if (!$cm || $cm->deletioninprogress) {
                continue;
            }
            $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, duedate, cutoffdate');
            $close = (int) ($assign->cutoffdate ?: $assign->duedate);
            if (!$close || $close > $now || $close <= (int) $config->closedprocessed) {
                continue;
            }
            $count = jobs::queue_assignment(get_course($cm->course), $cm);
            $DB->set_field('local_llmgrader_assign', 'closedprocessed', $close, ['id' => $config->id]);
            mtrace("  Assignment cm {$cm->id} closed: queued $count submissions.");
        }
    }
}
