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

namespace theme_iiitdwd\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use theme_iiitdwd\local\color_mode;

/**
 * Privacy provider for theme_iiitdwd: the Day/Night mode user preference.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * Describes the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(color_mode::PREFERENCE, 'privacy:metadata:preference:colormode');
        return $collection;
    }

    /**
     * Exports the user's Day/Night mode.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $mode = get_user_preferences(color_mode::PREFERENCE, null, $userid);
        if ($mode !== null) {
            $description = get_string($mode === color_mode::LIGHT ? 'daymode' : 'nightmode', 'theme_iiitdwd');
            writer::export_user_preference('theme_iiitdwd', color_mode::PREFERENCE, $mode, $description);
        }
    }
}
