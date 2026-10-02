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

namespace local_llmgrader\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy metadata for local_llmgrader.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {

    /**
     * Describe the personal data stored and sent elsewhere.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_llmgrader_job', [
            'userid' => 'privacy:metadata:local_llmgrader_job:userid',
            'score' => 'privacy:metadata:local_llmgrader_job:score',
            'feedback' => 'privacy:metadata:local_llmgrader_job:feedback',
        ], 'privacy:metadata:local_llmgrader_job');
        $collection->add_external_location_link('llm', [
            'notebook' => 'privacy:metadata:llm:notebook',
        ], 'privacy:metadata:llm');
        return $collection;
    }
}
