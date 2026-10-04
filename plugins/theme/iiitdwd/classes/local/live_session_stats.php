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

use context_course;
use stdClass;

/**
 * Live session (Zoom) attendance for one course.
 *
 * Sessions are Zoom meetings that took place, as reported back by Zoom (zoom_meeting_details, one row per
 * held meeting, recurring occurrences included). Attendance comes from zoom_meeting_participants, counting a
 * user once per session however often they rejoined. Participants Zoom could not match to a Moodle user are
 * ignored.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class live_session_stats {
    /**
     * Attendance for the course: the whole class for teachers, the user's own for everyone else.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @param int|null $now
     * @return array|null ['scope' => 'course'|'user', 'sessions' => int, 'attended' => int, 'missed' => int,
     *                    'percent' => int|null], or null when mod_zoom is not installed.
     */
    public static function get(stdClass $course, stdClass $user, ?int $now = null): ?array {
        global $DB;
        if (!$DB->get_manager()->table_exists('zoom_meeting_participants')) {
            return null;
        }
        $now = $now ?? time();

        $sessionsql = "SELECT d.id
                         FROM {zoom_meeting_details} d
                         JOIN {zoom} z ON z.id = d.zoomid
                        WHERE z.course = :course AND d.start_time <= :now";
        $params = ['course' => $course->id, 'now' => $now];
        $sessions = $DB->count_records_sql("SELECT COUNT(1) FROM ($sessionsql) s", $params);

        if (has_capability('moodle/grade:viewall', context_course::instance($course->id), $user)) {
            [$studentsql, $studentparams] = student_sql::active_students([$course->id], $now, 'ls_');
            $students = $DB->count_records_sql("SELECT COUNT(1) FROM ($studentsql) st", $studentparams);
            $attended = $DB->count_records_sql(
                "SELECT COUNT(1) FROM (
                    SELECT DISTINCT p.detailsid, p.userid
                      FROM {zoom_meeting_participants} p
                      JOIN ($studentsql) st ON st.userid = p.userid
                     WHERE p.detailsid IN ($sessionsql)
                 ) a",
                $params + $studentparams
            );
            $expected = $sessions * $students;
            $scope = 'course';
        } else {
            $attended = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT p.detailsid)
                   FROM {zoom_meeting_participants} p
                  WHERE p.userid = :userid AND p.detailsid IN ($sessionsql)",
                $params + ['userid' => $user->id]
            );
            $expected = $sessions;
            $scope = 'user';
        }

        return [
            'scope' => $scope,
            'sessions' => $sessions,
            'attended' => $attended,
            'missed' => max(0, $expected - $attended),
            'percent' => $expected ? (int) round(100 * min($attended, $expected) / $expected) : null,
        ];
    }
}
