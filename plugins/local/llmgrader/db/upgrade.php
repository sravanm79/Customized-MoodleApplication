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
 * Upgrade steps for local_llmgrader.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_llmgrader_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101300) {
        // Review step: who approved or rejected a draft, and when.
        $table = new xmldb_table('local_llmgrader_job');
        foreach (['reviewerid', 'timereviewed'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Per-assignment settings.
        $table = new xmldb_table('local_llmgrader_assign');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('triggermode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'submit');
            $table->add_field('requirereview', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('rubric', XMLDB_TYPE_TEXT);
            $table->add_field('reference', XMLDB_TYPE_TEXT);
            $table->add_field('guidelines', XMLDB_TYPE_TEXT);
            $table->add_field('closedprocessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('cmid', XMLDB_KEY_FOREIGN_UNIQUE, ['cmid'], 'course_modules', ['id']);
            $dbman->create_table($table);
        }

        // The site prompt was notebook-only; if it is still the shipped default, switch to the general one.
        $prompt = get_config('local_llmgrader', 'systemprompt');
        if ($prompt !== false && strpos($prompt, 'grading a student\'s Python Jupyter notebook') !== false
                && strpos($prompt, '"awarded" is what the student earned') !== false) {
            set_config('systemprompt', \local_llmgrader\prompt_builder::DEFAULT_PROMPT, 'local_llmgrader');
        }

        upgrade_plugin_savepoint(true, 2026101300, 'local', 'llmgrader');
    }
    return true;
}
