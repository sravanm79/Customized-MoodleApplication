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
 * Post-install steps for local_llmgrader.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create the "LLM Grader" account that grades are written as, so the audit trail shows them apart from teachers.
 */
function xmldb_local_llmgrader_install() {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/user/lib.php');

    $user = $DB->get_record('user', ['username' => 'llmgrader', 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$user) {
        // Tasks refuse to run as "nologin" users, so use manual auth with a random password nobody knows.
        $userid = user_create_user((object) [
            'username' => 'llmgrader',
            'auth' => 'manual',
            'password' => random_string(40) . 'Aa1!',
            'firstname' => 'LLM',
            'lastname' => 'Grader',
            'email' => 'llmgrader@invalid.local',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ], false, false);
    } else {
        $userid = $user->id;
    }

    // Test setup: a system-level Manager role lets it grade in every course.
    // For production, give it a Teacher role only in the courses that use LLM grading.
    $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
    role_assign($managerrole->id, $userid, context_system::instance()->id);

    set_config('graderuserid', $userid, 'local_llmgrader');
    return true;
}
