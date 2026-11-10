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

namespace plagiarism_similarity\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for plagiarism_similarity.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('plagiarism_similarity_sub', [
            'userid' => 'privacy:metadata:sub:userid',
            'fingerprint' => 'privacy:metadata:sub:fingerprint',
            'score' => 'privacy:metadata:sub:score',
            'matchuserid' => 'privacy:metadata:sub:matchuserid',
            'timemodified' => 'privacy:metadata:sub:timemodified',
        ], 'privacy:metadata:sub');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql("SELECT ctx.id
                                      FROM {plagiarism_similarity_sub} s
                                      JOIN {context} ctx ON ctx.instanceid = s.cmid AND ctx.contextlevel = :level
                                     WHERE s.userid = :userid", ['level' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_module) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {plagiarism_similarity_sub} WHERE cmid = :cmid',
                ['cmid' => $context->instanceid]);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $row = $DB->get_record('plagiarism_similarity_sub', ['cmid' => $context->instanceid, 'userid' => $userid]);
            if ($row) {
                writer::with_context($context)->export_data([get_string('pluginname', 'plagiarism_similarity')], (object) [
                    'status' => $row->status,
                    'score' => $row->score,
                    'timemodified' => transform::datetime($row->timemodified),
                ]);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_module) {
            $DB->delete_records('plagiarism_similarity_sub', ['cmid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                $DB->delete_records('plagiarism_similarity_sub', ['cmid' => $context->instanceid, 'userid' => $userid]);
                // Others' results naming this user as the match.
                $DB->set_field('plagiarism_similarity_sub', 'matchuserid', null,
                    ['cmid' => $context->instanceid, 'matchuserid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;
        $DB->delete_records_select('plagiarism_similarity_sub', "cmid = :cmid AND userid $insql", $params);
        $DB->set_field_select('plagiarism_similarity_sub', 'matchuserid', null, "cmid = :cmid AND matchuserid $insql",
            $params);
    }
}
