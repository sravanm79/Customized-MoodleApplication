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

namespace local_notifier\task;

defined('MOODLE_INTERNAL') || die();

use local_notifier\notifier;
use local_notifier\gcal;

/**
 * Scheduled task: scan upcoming assignments, Zoom meetings, and quizzes and
 * send deadline/start reminders through Moodle messages (in-app + email + mobile push)
 * plus Google Calendar events for users who have connected their calendar.
 *
 * Runs every 5 minutes. Deduplication via local_notifier_sent prevents double-sends.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_deadline_notifications extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_sendnotifications', 'local_notifier');
    }

    public function execute() {
        if (!get_config('local_notifier', 'enabled')) {
            mtrace('local_notifier: disabled, skipping.');
            return;
        }

        $now = time();
        mtrace('local_notifier: starting notification scan at ' . userdate($now));

        $this->process_assignments($now);
        $this->process_zoom($now);
        $this->process_quizzes($now);

        mtrace('local_notifier: done.');
    }

    // -------------------------------------------------------------------------
    // Assignments
    // -------------------------------------------------------------------------

    private function process_assignments(int $now): void {
        global $DB;

        $leads = notifier::get_lead_minutes('assign');
        if (empty($leads)) {
            return;
        }

        $maxlead = max($leads) * MINSECS;

        // All visible assignments with a due date that is still in the future or recently passed
        // (within the longest lead time so we catch runs that missed a window).
        $sql = "SELECT a.id, a.course, a.name, a.duedate, cm.id AS cmid
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m         ON m.id = cm.module AND m.name = 'assign'
                  JOIN {course} c          ON c.id = a.course
                 WHERE a.duedate > 0
                   AND a.duedate > :past
                   AND cm.visible = 1
                   AND c.visible = 1";
        $assigns = $DB->get_records_sql($sql, ['past' => $now - $maxlead]);

        foreach ($assigns as $assign) {
            $students = $this->get_enrolled_students((int) $assign->course, 'mod/assign:submit');
            $course   = get_course($assign->course);
            $url      = new \moodle_url('/mod/assign/view.php', ['id' => $assign->cmid]);

            foreach ($students as $userid) {
                foreach ($leads as $lead) {
                    if (!notifier::should_fire((int) $assign->duedate, $lead, $now)) {
                        continue;
                    }
                    if (notifier::is_sent($userid, 'assign', (int) $assign->cmid, $lead)) {
                        continue;
                    }

                    $leadstr = notifier::format_lead($lead);
                    $a = (object) [
                        'name'    => $assign->name,
                        'course'  => $course->shortname,
                        'lead'    => $leadstr,
                        'duedate' => userdate($assign->duedate),
                        'url'     => $url->out(false),
                    ];
                    notifier::send_message(
                        $userid,
                        get_string('reminder_assign_subject', 'local_notifier', $a),
                        get_string('reminder_assign_body',    'local_notifier', $a),
                        $url
                    );

                    // GCal: create/update a 1-minute event at the due date.
                    gcal::create_or_update_event($userid, 'assign', (int) $assign->cmid, [
                        'summary'     => get_string('reminder_assign_subject', 'local_notifier', $a),
                        'description' => get_string('reminder_assign_body',    'local_notifier', $a),
                        'start'       => (int) $assign->duedate,
                        'end'         => (int) $assign->duedate + 60,
                        'url'         => $url->out(false),
                    ]);

                    notifier::mark_sent($userid, 'assign', (int) $assign->cmid, $lead);
                    mtrace("  assign '{$assign->name}' → user {$userid} ({$leadstr} lead)");
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Zoom meetings
    // -------------------------------------------------------------------------

    private function process_zoom(int $now): void {
        global $DB;

        $leads = notifier::get_lead_minutes('zoom');
        if (empty($leads)) {
            return;
        }

        $maxlead = max($leads) * MINSECS;

        $sql = "SELECT z.id, z.course, z.name, z.start_time, z.duration, z.join_url, cm.id AS cmid
                  FROM {zoom} z
                  JOIN {course_modules} cm ON cm.instance = z.id
                  JOIN {modules} m         ON m.id = cm.module AND m.name = 'zoom'
                  JOIN {course} c          ON c.id = z.course
                 WHERE z.start_time > :past
                   AND z.start_time > 0
                   AND (z.recurring = 0 OR z.recurring IS NULL)
                   AND cm.visible = 1
                   AND c.visible = 1";
        $meetings = $DB->get_records_sql($sql, ['past' => $now - $maxlead]);

        foreach ($meetings as $meeting) {
            $students = $this->get_enrolled_students((int) $meeting->course, 'mod/zoom:view');
            $course   = get_course($meeting->course);
            $url      = new \moodle_url('/mod/zoom/view.php', ['id' => $meeting->cmid]);
            $joinurl  = !empty($meeting->join_url) ? $meeting->join_url : $url->out(false);

            foreach ($students as $userid) {
                foreach ($leads as $lead) {
                    if (!notifier::should_fire((int) $meeting->start_time, $lead, $now)) {
                        continue;
                    }
                    if (notifier::is_sent($userid, 'zoom', (int) $meeting->id, $lead)) {
                        continue;
                    }

                    $leadstr = notifier::format_lead($lead);
                    $a = (object) [
                        'name'      => $meeting->name,
                        'course'    => $course->shortname,
                        'lead'      => $leadstr,
                        'starttime' => userdate($meeting->start_time),
                        'url'       => $joinurl,
                    ];
                    notifier::send_message(
                        $userid,
                        get_string('reminder_zoom_subject', 'local_notifier', $a),
                        get_string('reminder_zoom_body',    'local_notifier', $a),
                        new \moodle_url($joinurl)
                    );

                    // GCal: event spanning the meeting duration.
                    $duration = max(60, (int) $meeting->duration);
                    gcal::create_or_update_event($userid, 'zoom', (int) $meeting->id, [
                        'summary'     => get_string('reminder_zoom_subject', 'local_notifier', $a),
                        'description' => get_string('reminder_zoom_body',    'local_notifier', $a),
                        'start'       => (int) $meeting->start_time,
                        'end'         => (int) $meeting->start_time + $duration,
                        'url'         => $joinurl,
                    ]);

                    notifier::mark_sent($userid, 'zoom', (int) $meeting->id, $lead);
                    mtrace("  zoom '{$meeting->name}' → user {$userid} ({$leadstr} lead)");
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Quizzes
    // -------------------------------------------------------------------------

    private function process_quizzes(int $now): void {
        global $DB;

        $leads = notifier::get_lead_minutes('quiz');
        if (empty($leads)) {
            return;
        }

        $maxlead = max($leads) * MINSECS;

        $sql = "SELECT q.id, q.course, q.name, q.timeclose, cm.id AS cmid
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m         ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {course} c          ON c.id = q.course
                 WHERE q.timeclose > 0
                   AND q.timeclose > :past
                   AND cm.visible = 1
                   AND c.visible = 1";
        $quizzes = $DB->get_records_sql($sql, ['past' => $now - $maxlead]);

        foreach ($quizzes as $quiz) {
            $students = $this->get_enrolled_students((int) $quiz->course, 'mod/quiz:attempt');
            $course   = get_course($quiz->course);
            $url      = new \moodle_url('/mod/quiz/view.php', ['id' => $quiz->cmid]);

            foreach ($students as $userid) {
                foreach ($leads as $lead) {
                    if (!notifier::should_fire((int) $quiz->timeclose, $lead, $now)) {
                        continue;
                    }
                    if (notifier::is_sent($userid, 'quiz', (int) $quiz->cmid, $lead)) {
                        continue;
                    }

                    $leadstr = notifier::format_lead($lead);
                    $a = (object) [
                        'name'      => $quiz->name,
                        'course'    => $course->shortname,
                        'lead'      => $leadstr,
                        'closedate' => userdate($quiz->timeclose),
                        'url'       => $url->out(false),
                    ];
                    notifier::send_message(
                        $userid,
                        get_string('reminder_quiz_subject', 'local_notifier', $a),
                        get_string('reminder_quiz_body',    'local_notifier', $a),
                        $url
                    );

                    // GCal: 1-minute event at the close time.
                    gcal::create_or_update_event($userid, 'quiz', (int) $quiz->cmid, [
                        'summary'     => get_string('reminder_quiz_subject', 'local_notifier', $a),
                        'description' => get_string('reminder_quiz_body',    'local_notifier', $a),
                        'start'       => (int) $quiz->timeclose,
                        'end'         => (int) $quiz->timeclose + 60,
                        'url'         => $url->out(false),
                    ]);

                    notifier::mark_sent($userid, 'quiz', (int) $quiz->cmid, $lead);
                    mtrace("  quiz '{$quiz->name}' → user {$userid} ({$leadstr} lead)");
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns user IDs of students enrolled in a course with a given capability.
     *
     * @param int    $courseid
     * @param string $capability
     * @return int[]
     */
    private function get_enrolled_students(int $courseid, string $capability): array {
        $context = \context_course::instance($courseid);
        $users   = get_enrolled_users($context, $capability, 0, 'u.id', null, 0, 0, true);
        return array_keys($users);
    }
}
