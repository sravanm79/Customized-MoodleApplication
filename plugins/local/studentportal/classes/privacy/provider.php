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
namespace local_studentportal\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider. The plugin stores the announcements people send (author, audience, text). Accounts, enrolments
 * and grades it creates or shows belong to core; issued passwords live only in the issuing admin's session.
 *
 * On a deletion request the author is removed from their announcements (they stay readable for the students).
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_studentportal_ann', [
            'userid' => 'privacy:metadata:ann:userid',
            'subject' => 'privacy:metadata:ann:subject',
            'message' => 'privacy:metadata:ann:message',
            'timecreated' => 'privacy:metadata:ann:timecreated',
        ], 'privacy:metadata:ann');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:messages');
        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if ($DB->record_exists('local_studentportal_ann', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel == CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_studentportal_ann} WHERE userid > 0', []);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $rows = $DB->get_records('local_studentportal_ann', ['userid' => $userid], 'timecreated');
            if ($rows) {
                writer::with_context($context)->export_data([get_string('announcements', 'local_studentportal')],
                    (object) ['announcements' => array_values(array_map(fn($r) => [
                        'subject' => $r->subject,
                        'message' => $r->message,
                        'audience' => $r->courses === '' ? 'all students' : 'courses ' . $r->courses,
                        'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
                    ], $rows))]);
            }
        }
    }

    /**
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->set_field('local_studentportal_ann', 'userid', 0, []);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->set_field('local_studentportal_ann', 'userid', 0, ['userid' => $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids());
        $DB->set_field_select('local_studentportal_ann', 'userid', 0, "userid $insql", $params);
    }
}
