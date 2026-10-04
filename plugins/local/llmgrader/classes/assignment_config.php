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
 * LLM grading settings of one assignment: when to run, whether results wait for a teacher, and the rubric,
 * reference solution and guidelines given to the model. Assignments without saved settings use the defaults.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_config {
    /** Run when a student submits. */
    const TRIGGER_SUBMIT = 'submit';

    /** Run for all submissions once the assignment closes (cut-off date, else due date). */
    const TRIGGER_CLOSE = 'close';

    /** Run only when a teacher clicks "Assign to LLM". */
    const TRIGGER_MANUAL = 'manual';

    /**
     * Settings of an assignment.
     *
     * @param int $cmid
     * @return \stdClass Record of local_llmgrader_assign (id 0 when not saved yet).
     */
    public static function get(int $cmid): \stdClass {
        global $DB;
        $record = $DB->get_record('local_llmgrader_assign', ['cmid' => $cmid]);
        return $record ?: (object) [
            'id' => 0,
            'cmid' => $cmid,
            'triggermode' => self::TRIGGER_SUBMIT,
            'requirereview' => (int) (get_config('local_llmgrader', 'requirereview') ?? 1),
            'rubric' => '',
            'reference' => '',
            'guidelines' => '',
            'closedprocessed' => 0,
            'timemodified' => 0,
        ];
    }

    /**
     * Saves settings.
     *
     * @param \stdClass $data cmid, triggermode, requirereview, rubric, reference, guidelines
     */
    public static function save(\stdClass $data): void {
        global $DB;
        $record = self::get((int) $data->cmid);
        foreach (['triggermode', 'requirereview', 'rubric', 'reference', 'guidelines'] as $field) {
            $record->$field = $data->$field;
        }
        $record->timemodified = time();
        if ($record->id) {
            $DB->update_record('local_llmgrader_assign', $record);
        } else {
            unset($record->id);
            $DB->insert_record('local_llmgrader_assign', $record);
        }
    }
}
