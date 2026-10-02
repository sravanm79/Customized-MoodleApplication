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

namespace mod_jupyter\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy metadata for mod_jupyter.
 *
 * @package   mod_jupyter
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
        $collection->add_database_table('jupyter_submissions', [
            'userid' => 'privacy:metadata:jupyter_submissions:userid',
            'grade' => 'privacy:metadata:jupyter_submissions:grade',
            'feedback' => 'privacy:metadata:jupyter_submissions:feedback',
            'timemodified' => 'privacy:metadata:jupyter_submissions:timemodified',
        ], 'privacy:metadata:jupyter_submissions');
        $collection->add_external_location_link('jupyterhub', [
            'userid' => 'privacy:metadata:jupyterhub:userid',
            'notebook' => 'privacy:metadata:jupyterhub:notebook',
        ], 'privacy:metadata:jupyterhub');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:jupyter_submissions');
        return $collection;
    }
}
