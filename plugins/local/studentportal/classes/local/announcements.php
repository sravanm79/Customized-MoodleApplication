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
namespace local_studentportal\local;

/**
 * Announcements to students: site-wide (every student) or to the students of chosen courses. Delivered as a Moodle
 * notification (bell, and email per the student's preferences) by an ad hoc task, and listed on the student dashboard
 * and the Announcements page.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class announcements {

    /**
     * Courses in which the user may announce to students.
     *
     * @param int $userid
     * @return \stdClass[] keyed by id (id, fullname, shortname)
     */
    public static function courses_for(int $userid): array {
        $out = [];
        foreach (get_courses('all', 'c.fullname ASC', 'c.id, c.fullname, c.shortname') as $course) {
            if ($course->id != SITEID
                    && has_capability('local/studentportal:announce', \context_course::instance($course->id), $userid)) {
                $out[$course->id] = $course;
            }
        }
        return $out;
    }

    /**
     * Saves an announcement and queues it for sending.
     *
     * @param int $userid Author.
     * @param int[] $courseids Empty for every student on the site.
     * @param string $subject
     * @param string $message
     * @param int $format
     * @return int Announcement id.
     */
    public static function create(int $userid, array $courseids, string $subject, string $message, int $format): int {
        global $DB;
        $id = $DB->insert_record('local_studentportal_ann', (object) [
            'userid' => $userid,
            'courses' => implode(',', array_map('intval', $courseids)),
            'subject' => \core_text::substr($subject, 0, 255),
            'message' => $message,
            'messageformat' => $format,
            'status' => 'queued',
            'recipients' => 0,
            'timecreated' => time(),
            'timesent' => 0,
        ]);
        $task = new \local_studentportal\task\send_announcement();
        $task->set_custom_data(['id' => $id]);
        \core\task\manager::queue_adhoc_task($task);
        return $id;
    }

    /**
     * Course ids of an announcement (empty = site-wide).
     *
     * @param \stdClass $announcement
     * @return int[]
     */
    public static function course_ids(\stdClass $announcement): array {
        return $announcement->courses === '' ? [] : array_map('intval', explode(',', $announcement->courses));
    }

    /**
     * Active, not suspended students who receive an announcement.
     *
     * @param int[] $courseids Empty for every student on the site.
     * @return int[] user ids
     */
    public static function recipients(array $courseids): array {
        global $DB;
        $params = [
            'studentrole' => $DB->get_field('role', 'id', ['shortname' => 'student']),
            'courselevel' => CONTEXT_COURSE,
            'enabled' => ENROL_INSTANCE_ENABLED,
            'active' => ENROL_USER_ACTIVE,
            'now1' => time(),
            'now2' => time(),
        ];
        $coursesql = '';
        if ($courseids) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $coursesql = "AND e.courseid $insql";
            $params += $inparams;
        }
        return array_map('intval', $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = :active
                   AND ue.timestart <= :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
              JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enabled $coursesql
              JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :courselevel
              JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid = :studentrole
             WHERE u.deleted = 0 AND u.suspended = 0", $params));
    }

    /**
     * Sends a queued announcement to its recipients.
     *
     * @param int $id
     * @return int Number of students notified.
     */
    public static function send(int $id): int {
        global $DB;
        $announcement = $DB->get_record('local_studentportal_ann', ['id' => $id]);
        if (!$announcement || $announcement->status === 'sent') {
            return 0;
        }
        $from = \core_user::get_user($announcement->userid) ?: \core_user::get_noreply_user();
        $url = new \moodle_url('/local/studentportal/announcements.php', ['id' => $id]);
        $html = format_text($announcement->message, $announcement->messageformat, ['context' => \context_system::instance()]);
        $text = html_to_text($html);
        $audience = self::audience_text($announcement);
        $sent = 0;
        foreach (self::recipients(self::course_ids($announcement)) as $userid) {
            $message = new \core\message\message();
            $message->component = 'local_studentportal';
            $message->name = 'announcement';
            $message->userfrom = $from;
            $message->userto = $userid;
            $message->subject = $announcement->subject;
            $message->fullmessage = $text . "\n\n" . $audience . "\n" . $url->out(false);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $html . '<p><small>' . s($audience) . '</small></p>';
            $message->smallmessage = $announcement->subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('announcements', 'local_studentportal');
            $message->courseid = count(self::course_ids($announcement)) === 1 ? self::course_ids($announcement)[0] : SITEID;
            if (message_send($message)) {
                $sent++;
            }
        }
        $DB->update_record('local_studentportal_ann', (object) ['id' => $id, 'status' => 'sent', 'recipients' => $sent,
            'timesent' => time()]);
        return $sent;
    }

    /**
     * "To all students" / "To students of: A, B".
     *
     * @param \stdClass $announcement
     * @return string
     */
    public static function audience_text(\stdClass $announcement): string {
        global $DB;
        $ids = self::course_ids($announcement);
        if (!$ids) {
            return get_string('audience_site', 'local_studentportal');
        }
        $names = [];
        foreach ($DB->get_records_list('course', 'id', $ids, 'fullname', 'id, shortname') as $course) {
            $names[] = format_string($course->shortname);
        }
        return get_string('audience_courses', 'local_studentportal', implode(', ', $names));
    }

    /**
     * Announcements a student received: site-wide ones and those for courses they are a student in (now).
     *
     * @param int $userid
     * @param int $limit
     * @param int $since Only newer than this timestamp (0 = all).
     * @return \stdClass[]
     */
    public static function for_student(int $userid, int $limit = 20, int $since = 0): array {
        global $DB;
        $courses = array_keys((new student_data(\core_user::get_user($userid)))->get_courses());
        $out = [];
        $records = $DB->get_records_select('local_studentportal_ann', "status = 'sent' AND timesent >= :since",
            ['since' => $since], 'timesent DESC', '*', 0, 200);
        foreach ($records as $record) {
            $ids = self::course_ids($record);
            if (!$ids || array_intersect($ids, $courses)) {
                $out[] = $record;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Announcements a staff member can see in the "Sent" list: their own, all site-wide ones if they can send those,
     * and those for courses where they can announce.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function sent_visible_to(int $userid): array {
        global $DB;
        $courses = array_keys(self::courses_for($userid));
        $site = has_capability('local/studentportal:announcesite', \context_system::instance(), $userid);
        $out = [];
        foreach ($DB->get_records('local_studentportal_ann', null, 'timecreated DESC', '*', 0, 200) as $record) {
            $ids = self::course_ids($record);
            if ($record->userid == $userid || ($site && !$ids) || ($ids && array_intersect($ids, $courses))) {
                $out[] = $record;
            }
        }
        return $out;
    }

    /**
     * Whether a user may read one announcement.
     *
     * @param \stdClass $announcement
     * @param int $userid
     * @return bool
     */
    public static function can_view(\stdClass $announcement, int $userid): bool {
        foreach (self::for_student($userid, 1000) as $record) {
            if ($record->id == $announcement->id) {
                return true;
            }
        }
        foreach (self::sent_visible_to($userid) as $record) {
            if ($record->id == $announcement->id) {
                return true;
            }
        }
        return false;
    }
}
