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

/**
 * Upgrade steps for local_notifier.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_notifier_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026111000) {
        // Per-course lead times (0.2.0).
        $table = new xmldb_table('local_notifier_course');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('assign_lead_times', XMLDB_TYPE_CHAR, '255');
        $table->add_field('quiz_lead_times', XMLDB_TYPE_CHAR, '255');
        $table->add_field('live_lead_times', XMLDB_TYPE_CHAR, '255');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom lead times now cover every live class (Zoom and Google Meet).
        $zoom = get_config('local_notifier', 'zoom_lead_times');
        if ($zoom !== false) {
            set_config('live_lead_times', $zoom, 'local_notifier');
            unset_config('zoom_lead_times', 'local_notifier');
        }

        // Live-class reminders are now keyed by calendar event (one per occurrence), not by Zoom meeting.
        $DB->delete_records('local_notifier_sent', ['component' => 'zoom']);
        $DB->delete_records('local_notifier_gcal_events', ['component' => 'zoom']);

        upgrade_plugin_savepoint(true, 2026111000, 'local', 'notifier');
    }
    return true;
}
