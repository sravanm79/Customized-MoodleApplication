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

namespace quizaccess_examproctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for quizaccess_examproctor.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_examproctor_log', [
            'attemptid' => 'privacy:metadata:log:attemptid',
            'userid' => 'privacy:metadata:log:userid',
            'eventtype' => 'privacy:metadata:log:eventtype',
            'details' => 'privacy:metadata:log:details',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        return $collection;
    }

    /** SQL joining a module context to the quiz it belongs to. */
    private const CONTEXT_JOIN = "JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                                  JOIN {quizaccess_examproctor_log} l ON l.quizid = cm.instance";

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql("SELECT ctx.id FROM {context} ctx " . self::CONTEXT_JOIN . " WHERE l.userid = :userid",
                ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT l.userid FROM {context} ctx " . self::CONTEXT_JOIN . " WHERE ctx.id = :ctxid",
                ['contextlevel' => CONTEXT_MODULE, 'ctxid' => $context->id]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$quizid = self::quizid($context)) {
                continue;
            }
            $events = $DB->get_records('quizaccess_examproctor_log', ['quizid' => $quizid, 'userid' => $userid],
                    'timecreated, id', 'id, attemptid, eventtype, details, timecreated');
            if (!$events) {
                continue;
            }
            $data = array_map(function($e) {
                return [
                    'attemptid' => $e->attemptid,
                    'eventtype' => $e->eventtype,
                    'details' => $e->details,
                    'timecreated' => transform::datetime($e->timecreated),
                ];
            }, array_values($events));
            writer::with_context($context)->export_data(
                    [get_string('pluginname', 'quizaccess_examproctor')], (object) ['events' => $data]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($quizid = self::quizid($context)) {
            $DB->delete_records('quizaccess_examproctor_log', ['quizid' => $quizid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($quizid = self::quizid($context)) {
                $DB->delete_records('quizaccess_examproctor_log', ['quizid' => $quizid, 'userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$quizid = self::quizid($userlist->get_context())) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;
        $DB->delete_records_select('quizaccess_examproctor_log', "quizid = :quizid AND userid $insql", $params);
    }

    /**
     * Quiz id for a module context, or null if the context is not a quiz.
     *
     * @param \context $context
     * @return int|null
     */
    private static function quizid(\context $context): ?int {
        if (!$context instanceof \context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }
}
