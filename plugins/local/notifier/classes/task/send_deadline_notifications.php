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
 * Scheduled task: scan upcoming assignments, quizzes and live classes (Zoom and Google Meet, every occurrence of
 * recurring ones) and send deadline/start reminders through Moodle messages (in-app + email + mobile push)
 * plus Google Calendar events for users who have connected their calendar.
 *
 * Lead times come from the course's Reminder settings, else the site defaults.
 * Runs every 5 minutes. Deduplication via local_notifier_sent prevents double-sends.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_deadline_notifications extends \core\task\scheduled_task {

    /** Longest lead time accepted anywhere (14 days), bounding the scan window. */
    const MAX_LEAD_MINUTES = 20160;

    /** @var array Enrolled user ids by course and capability, for this run. */
    protected $students = [];

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
        $this->process_quizzes($now);
        $this->process_live_classes($now);

        mtrace('local_notifier: done.');
    }

    // -------------------------------------------------------------------------
    // Assignments
    // -------------------------------------------------------------------------

    private function process_assignments(int $now): void {
        global $DB;

        // Visible assignments whose due date is still ahead but inside the longest possible lead window.
        $sql = "SELECT a.id, a.course, a.name, a.duedate, cm.id AS cmid
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m         ON m.id = cm.module AND m.name = 'assign'
                  JOIN {course} c          ON c.id = a.course
                 WHERE a.duedate > :now
                   AND a.duedate <= :until
                   AND cm.visible = 1
                   AND cm.deletioninprogress = 0
                   AND c.visible = 1";
        $assigns = $DB->get_records_sql($sql, ['now' => $now, 'until' => $now + self::MAX_LEAD_MINUTES * MINSECS]);

        foreach ($assigns as $assign) {
            $url = new \moodle_url('/mod/assign/view.php', ['id' => $assign->cmid]);
            $a   = (object) [
                'name'    => $assign->name,
                'course'  => get_course($assign->course)->shortname,
                'duedate' => userdate($assign->duedate),
                'url'     => $url->out(false),
            ];
            $this->remind((int) $assign->course, 'assign', 'mod/assign:submit', 'assign', (int) $assign->cmid,
                (int) $assign->duedate, 60, 'reminder_assign', $a, $url);
        }
    }

    // -------------------------------------------------------------------------
    // Quizzes
    // -------------------------------------------------------------------------

    private function process_quizzes(int $now): void {
        global $DB;

        $sql = "SELECT q.id, q.course, q.name, q.timeclose, cm.id AS cmid
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m         ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {course} c          ON c.id = q.course
                 WHERE q.timeclose > :now
                   AND q.timeclose <= :until
                   AND cm.visible = 1
                   AND cm.deletioninprogress = 0
                   AND c.visible = 1";
        $quizzes = $DB->get_records_sql($sql, ['now' => $now, 'until' => $now + self::MAX_LEAD_MINUTES * MINSECS]);

        foreach ($quizzes as $quiz) {
            $url = new \moodle_url('/mod/quiz/view.php', ['id' => $quiz->cmid]);
            $a   = (object) [
                'name'      => $quiz->name,
                'course'    => get_course($quiz->course)->shortname,
                'closedate' => userdate($quiz->timeclose),
                'url'       => $url->out(false),
            ];
            $this->remind((int) $quiz->course, 'quiz', 'mod/quiz:attempt', 'quiz', (int) $quiz->cmid,
                (int) $quiz->timeclose, 60, 'reminder_quiz', $a, $url);
        }
    }

    // -------------------------------------------------------------------------
    // Live classes: Zoom and Google Meet
    // -------------------------------------------------------------------------

    /**
     * Both plugins write one calendar event per class (one per occurrence for recurring Zoom meetings and for
     * Google Meet's repeated dates), so each occurrence is reminded separately.
     */
    private function process_live_classes(int $now): void {
        global $DB;

        $sql = "SELECT e.id, e.modulename, e.instance, e.courseid, e.name, e.timestart, e.timeduration, cm.id AS cmid
                  FROM {event} e
                  JOIN {modules} m         ON m.name = e.modulename
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = e.instance
                  JOIN {course} c          ON c.id = e.courseid
                 WHERE e.modulename IN ('zoom', 'googlemeet')
                   AND e.timestart > :now
                   AND e.timestart <= :until
                   AND e.visible = 1
                   AND cm.visible = 1
                   AND cm.deletioninprogress = 0
                   AND c.visible = 1";
        $events = $DB->get_records_sql($sql, ['now' => $now, 'until' => $now + self::MAX_LEAD_MINUTES * MINSECS]);

        foreach ($events as $event) {
            $viewurl = new \moodle_url('/mod/' . $event->modulename . '/view.php', ['id' => $event->cmid]);
            $joinurl = $viewurl;
            if ($event->modulename === 'zoom') {
                $join = $DB->get_field('zoom', 'join_url', ['id' => $event->instance]);
                $joinurl = $join ? new \moodle_url($join) : $viewurl;
            } else {
                $joinurl = new \moodle_url('/mod/googlemeet/join.php', ['id' => $event->cmid]);
            }
            $name = $DB->get_field($event->modulename, 'name', ['id' => $event->instance]) ?: $event->name;
            $a = (object) [
                'name'      => $name,
                'course'    => get_course($event->courseid)->shortname,
                'starttime' => userdate($event->timestart),
                'url'       => $joinurl->out(false),
            ];
            $prefix = $event->modulename === 'zoom' ? 'reminder_zoom' : 'reminder_meet';
            $this->remind((int) $event->courseid, 'live', 'mod/' . $event->modulename . ':view', $event->modulename,
                (int) $event->id, (int) $event->timestart, max(60, (int) $event->timeduration), $prefix, $a, $joinurl);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Sends every reminder that is due now for one deadline or class, once per user and lead time.
     *
     * @param int $courseid
     * @param string $type Lead-time type: assign, quiz, live.
     * @param string $capability Who is reminded (students who can submit/attempt/view).
     * @param string $component Dedup and Google Calendar key: assign, quiz, zoom, googlemeet.
     * @param int $instanceid cmid (assign, quiz) or calendar event id (live classes).
     * @param int $eventtime Due date, close time or start time.
     * @param int $duration Google Calendar event length in seconds.
     * @param string $stringprefix Language strings <prefix>_subject and <prefix>_body.
     * @param \stdClass $a String placeholders (lead is added here).
     * @param \moodle_url $url
     */
    private function remind(int $courseid, string $type, string $capability, string $component, int $instanceid,
            int $eventtime, int $duration, string $stringprefix, \stdClass $a, \moodle_url $url): void {
        $leads = array_filter(notifier::get_lead_minutes($type, $courseid),
            fn(int $lead) => notifier::should_fire($eventtime, $lead, time()));
        if (!$leads) {
            return;
        }
        // Only the shortest lead that is due: after downtime a user gets one reminder, not "24 h" and "1 h" at once.
        $lead = min($leads);
        $a->lead = notifier::format_lead($lead);
        $subject = get_string($stringprefix . '_subject', 'local_notifier', $a);
        $body = get_string($stringprefix . '_body', 'local_notifier', $a);

        foreach ($this->get_enrolled_students($courseid, $capability) as $userid) {
            if (notifier::is_sent($userid, $component, $instanceid, $lead)) {
                continue;
            }
            notifier::send_message($userid, $subject, $body, $url);
            gcal::create_or_update_event($userid, $component, $instanceid, [
                'summary'     => $a->name . ' (' . $a->course . ')',
                'description' => $body,
                'start'       => $eventtime,
                'end'         => $eventtime + $duration,
                'url'         => $url->out(false),
            ]);
            // Longer leads that were skipped are marked too, so they never fire late.
            foreach ($leads as $l) {
                if (!notifier::is_sent($userid, $component, $instanceid, $l)) {
                    notifier::mark_sent($userid, $component, $instanceid, $l);
                }
            }
            mtrace("  {$component} '{$a->name}' → user {$userid} ({$a->lead} lead)");
        }
    }

    private function get_enrolled_students(int $courseid, string $capability): array {
        $key = $courseid . '/' . $capability;
        if (!isset($this->students[$key])) {
            $context = \context_course::instance($courseid);
            $users   = get_enrolled_users($context, $capability, 0, 'u.id', null, 0, 0, true);
            $this->students[$key] = array_map('intval', array_keys($users));
        }
        return $this->students[$key];
    }
}
