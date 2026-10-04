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

namespace theme_iiitdwd\local;

/**
 * SQL for "students" of a set of courses: users with a student-archetype role and an active enrolment.
 *
 * Unenrolled, suspended (enrolment or account) and deleted users are excluded, matching what the
 * participants and grading pages show.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_sql {
    /**
     * Subquery returning distinct (courseid, userid) rows.
     *
     * @param int[] $courseids
     * @param int $now
     * @param string $p Parameter name prefix; must be unique within the final query.
     * @return array [sql, params]
     */
    public static function active_students(array $courseids, int $now, string $p): array {
        global $DB;
        [$coursesql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, $p . 'c', true, 0);
        [$rolesql, $roleparams] = $DB->get_in_or_equal(array_keys(get_archetype_roles('student')), SQL_PARAMS_NAMED,
            $p . 'r', true, 0);
        $params += $roleparams + [
            "{$p}ctxlevel" => CONTEXT_COURSE,
            "{$p}enrolenabled" => ENROL_INSTANCE_ENABLED,
            "{$p}ueactive" => ENROL_USER_ACTIVE,
            "{$p}now1" => $now,
            "{$p}now2" => $now,
        ];
        $sql = "SELECT DISTINCT e.courseid, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :{$p}ctxlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
                 WHERE e.courseid $coursesql AND e.status = :{$p}enrolenabled AND ue.status = :{$p}ueactive
                       AND ue.timestart <= :{$p}now1 AND (ue.timeend = 0 OR ue.timeend > :{$p}now2)
                       AND u.deleted = 0 AND u.suspended = 0 AND ra.roleid $rolesql";
        return [$sql, $params];
    }
}
