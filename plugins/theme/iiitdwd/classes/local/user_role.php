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
 * The role shown in the user's role badge (sidebar and Dashboard heading).
 *
 * Site admins are shown as "Administrator", even when they also teach a course. Teachers (editing or non-editing
 * teacher archetype in any course where they are actively enrolled) are shown as "Teacher". Everyone else gets
 * their highest-ranked active role, by the site's role order (Site administration > Users > Define roles): roles
 * at system, category or user level, and course roles with an active enrolment.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_role {
    /** @var string[] Role archetypes shown as "Teacher". */
    const TEACHER_ARCHETYPES = ['editingteacher', 'teacher'];

    /** @var array Per-request cache: userid => badge array or null. */
    protected static $cache = [];

    /**
     * The badge for a user.
     *
     * @param int $userid
     * @return array|null ['key' => CSS modifier (teacher, student, manager, admin, other), 'label' => text], or null
     *     for guests and users with no role.
     */
    public static function badge(int $userid): ?array {
        if (!array_key_exists($userid, self::$cache)) {
            self::$cache[$userid] = self::compute($userid);
        }
        return self::$cache[$userid];
    }

    /**
     * Works out the badge (uncached).
     *
     * @param int $userid
     * @return array|null
     */
    protected static function compute(int $userid): ?array {
        if (!$userid || isguestuser($userid)) {
            return null;
        }
        if (is_siteadmin($userid)) {
            return ['key' => 'admin', 'label' => get_string('administrator')];
        }
        $roles = self::active_roles($userid);
        foreach ($roles as $role) {
            if (in_array($role->archetype, self::TEACHER_ARCHETYPES, true)) {
                return ['key' => 'teacher', 'label' => get_string('roleteacher', 'theme_iiitdwd')];
            }
        }
        if ($roles) {
            $role = reset($roles);
            $key = in_array($role->archetype, ['student', 'manager'], true) ? $role->archetype : 'other';
            return ['key' => $key, 'label' => role_get_name($role)];
        }
        return null;
    }

    /**
     * The user's active roles, highest-ranked first.
     *
     * Module and block level assignments (e.g. a forum moderator) are not a user's primary role and are ignored.
     *
     * @param int $userid
     * @return \stdClass[] Role records (id, name, shortname, archetype, sortorder), ordered by sortorder.
     */
    protected static function active_roles(int $userid): array {
        global $DB;
        $now = time();
        $sql = "SELECT DISTINCT r.id, r.name, r.shortname, r.archetype, r.sortorder
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                       AND (ctx.contextlevel IN (:system, :category, :user)
                            OR (ctx.contextlevel = :course AND EXISTS (
                                    SELECT 1
                                      FROM {user_enrolments} ue
                                      JOIN {enrol} e ON e.id = ue.enrolid
                                     WHERE e.courseid = ctx.instanceid AND ue.userid = ra.userid
                                           AND e.status = :enrolenabled AND ue.status = :ueactive
                                           AND ue.timestart <= :now1 AND (ue.timeend = 0 OR ue.timeend > :now2))))
              ORDER BY r.sortorder";
        return array_values($DB->get_records_sql($sql, [
            'userid' => $userid,
            'system' => CONTEXT_SYSTEM,
            'category' => CONTEXT_COURSECAT,
            'user' => CONTEXT_USER,
            'course' => CONTEXT_COURSE,
            'enrolenabled' => ENROL_INSTANCE_ENABLED,
            'ueactive' => ENROL_USER_ACTIVE,
            'now1' => $now,
            'now2' => $now,
        ]));
    }
}
