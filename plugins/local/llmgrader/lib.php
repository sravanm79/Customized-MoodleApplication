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
 * Navigation hooks for local_llmgrader.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Auto Grade" to the assignment's "More" menu for graders.
 *
 * @param settings_navigation $nav
 * @param context $context
 */
function local_llmgrader_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $PAGE;

    if (!get_config('local_llmgrader', 'enabled') || $context->contextlevel != CONTEXT_MODULE
            || $PAGE->cm === null || $PAGE->cm->modname !== 'assign' || !has_capability('mod/assign:grade', $context)) {
        return;
    }
    if ($modulesettings = $nav->find('modulesettings', navigation_node::TYPE_SETTING)) {
        $modulesettings->add(get_string('llmgrading', 'local_llmgrader'),
            new moodle_url('/local/llmgrader/report.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING, null, 'local_llmgrader_report');
    }
}
