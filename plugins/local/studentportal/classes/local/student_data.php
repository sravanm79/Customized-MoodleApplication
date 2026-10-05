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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Everything a student sees about themselves: their courses, what is due, live classes, grades with feedback, and
 * their own activity. Used by the theme's student dashboard and by performance.php.
 *
 * Only the student's own data, and only what the gradebook lets them see: hidden grade items, hidden grades and
 * grades not yet released (marking workflow) are left out.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_data {

    /** @var \stdClass */
    protected $user;

    /** @var int */
    protected $now;

    /** @var \stdClass[]|null Cached courses. */
    protected $courses = null;

    /**
     * @param \stdClass $user
     * @param int|null $now
     */
    public function __construct(\stdClass $user, ?int $now = null) {
        $this->user = $user;
        $this->now = $now ?? time();
    }

    /**
     * @return int
     */
    public function get_now(): int {
        return $this->now;
    }

    /**
     * Courses where the user is actively enrolled with the student role, by last access (most recent first).
     *
     * @return \stdClass[] keyed by course id
     */
    public function get_courses(): array {
        global $DB;
        if ($this->courses !== null) {
            return $this->courses;
        }
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $courses = [];
        foreach (enrol_get_users_courses($this->user->id, true, 'id, fullname, shortname, startdate, enddate, visible')
                as $course) {
            $context = \context_course::instance($course->id);
            if (!user_has_role_assignment($this->user->id, $studentrole, $context->id)) {
                continue;
            }
            $course->lastaccess = (int) $DB->get_field('user_lastaccess', 'timeaccess',
                ['userid' => $this->user->id, 'courseid' => $course->id]);
            $courses[$course->id] = $course;
        }
        uasort($courses, fn($a, $b) => [$b->lastaccess, $a->fullname] <=> [$a->lastaccess, $b->fullname]);
        return $this->courses = $courses;
    }

    /**
     * Course total for the user as a percentage, or null if not graded / hidden from them.
     *
     * @param int $courseid
     * @return float|null
     */
    public function get_course_grade(int $courseid): ?float {
        $item = \grade_item::fetch_course_item($courseid);
        if (!$item || $item->is_hidden()) {
            return null;
        }
        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $this->user->id]);
        if (empty($grade->id) || $grade->finalgrade === null || $grade->is_hidden()) {
            return null;
        }
        return self::percent($grade->finalgrade, $item);
    }

    /**
     * Course completion percentage, or null when completion is not tracked in the course.
     *
     * @param \stdClass $course
     * @return float|null
     */
    public function get_completion(\stdClass $course): ?float {
        $value = \core_completion\progress::get_course_progress_percentage($course, $this->user->id);
        return $value === null ? null : (float) $value;
    }

    /**
     * Graded activity items in a course the user may see, newest first.
     *
     * @param int $courseid
     * @return array[] ['name', 'module', 'cmid', 'url', 'grade' (formatted), 'percent', 'feedback' (HTML), 'time']
     */
    public function get_grade_items(int $courseid): array {
        $items = \grade_item::fetch_all(['courseid' => $courseid]) ?: [];
        $context = \context_course::instance($courseid);
        $modinfo = get_fast_modinfo($courseid, $this->user->id);
        $out = [];
        foreach ($items as $item) {
            if ($item->itemtype === 'course' || $item->itemtype === 'category' || $item->is_hidden()) {
                continue;
            }
            $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $this->user->id]);
            if (empty($grade->id) || $grade->is_hidden()) {
                continue;
            }
            $hasgrade = $grade->finalgrade !== null;
            $feedback = trim((string) $grade->feedback) === '' ? '' : format_text($grade->feedback,
                $grade->feedbackformat ?? FORMAT_HTML, ['context' => $context]);
            if (!$hasgrade && $feedback === '') {
                continue;
            }
            $cm = null;
            if ($item->itemtype === 'mod' && isset($modinfo->instances[$item->itemmodule][$item->iteminstance])) {
                $cm = $modinfo->instances[$item->itemmodule][$item->iteminstance];
                if (!$cm->uservisible) {
                    continue;
                }
            }
            $out[] = [
                'name' => $cm ? $cm->get_formatted_name() : format_string($item->get_name()),
                'module' => $item->itemmodule ? get_string('modulename', $item->itemmodule) : get_string('manualitem', 'grades'),
                'modname' => (string) $item->itemmodule,
                'cmid' => $cm ? $cm->id : null,
                'url' => $cm && $cm->url ? $cm->url->out(false) : null,
                'grade' => $hasgrade ? grade_format_gradevalue($grade->finalgrade, $item, true) : null,
                'percent' => $hasgrade ? self::percent($grade->finalgrade, $item) : null,
                'feedback' => $feedback,
                'time' => (int) ($grade->timemodified ?: $grade->timecreated),
            ];
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /**
     * The most recent grades and feedback across all courses.
     *
     * @param int $limit
     * @return array[] As get_grade_items() plus 'course' (short name) and 'courseid'.
     */
    public function get_recent_feedback(int $limit = 5): array {
        $all = [];
        foreach ($this->get_courses() as $course) {
            foreach ($this->get_grade_items($course->id) as $item) {
                $all[] = $item + ['course' => format_string($course->shortname), 'courseid' => $course->id];
            }
        }
        usort($all, fn($a, $b) => $b['time'] <=> $a['time']);
        return array_slice($all, 0, $limit);
    }

    /**
     * Actionable deadlines (assignments to submit, quizzes to attempt, ...) from the calendar, as in the Timeline.
     *
     * @param int $days Look ahead this many days (overdue items from the last 14 days are included too).
     * @param int $limit
     * @return array[] ['name', 'course', 'courseid', 'modname', 'time', 'actionname', 'actionurl', 'overdue']
     */
    public function get_upcoming(int $days = 14, int $limit = 8): array {
        $courses = $this->get_courses();
        $events = \core_calendar\local\api::get_action_events_by_timesort($this->now - 14 * DAYSECS,
            $this->now + $days * DAYSECS, null, 50, true, $this->user);
        $out = [];
        foreach ($events as $event) {
            $courseid = (int) $event->get_course()->get('id');
            $action = $event->get_action();
            if (!isset($courses[$courseid]) || !$action || !$action->is_actionable()) {
                continue;
            }
            // Meetings are listed under live classes (get_live_classes()), not as deadlines.
            if (in_array($event->get_component(), ['mod_zoom', 'mod_googlemeet'], true)) {
                continue;
            }
            $time = $event->get_times()->get_sort_time()->getTimestamp();
            $out[] = [
                'name' => $event->get_name(),
                'course' => format_string($courses[$courseid]->shortname),
                'courseid' => $courseid,
                'modname' => str_replace('mod_', '', (string) $event->get_component()),
                'time' => $time,
                'actionname' => $action->get_name(),
                'actionurl' => $action->get_url()->out(false),
                'overdue' => $time < $this->now,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Live classes (Zoom and Google Meet sessions) in the student's courses from 2 hours ago to $days ahead.
     *
     * @param int $days
     * @return array[] ['name', 'course', 'start', 'end', 'joinurl', 'viewurl', 'islive', 'istoday']
     */
    public function get_live_classes(int $days = 7): array {
        global $DB;
        $courses = $this->get_courses();
        $joinpages = ['zoom' => '/mod/zoom/loadmeeting.php', 'googlemeet' => '/mod/googlemeet/join.php'];
        $modules = array_values(array_filter(array_keys($joinpages), fn($m) => $DB->get_manager()->table_exists($m)));
        if (!$courses || !$modules) {
            return [];
        }
        [$modsql, $modparams] = $DB->get_in_or_equal($modules, SQL_PARAMS_NAMED, 'mod');
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $params += $modparams + ['from' => $this->now - 2 * HOURSECS, 'to' => $this->now + $days * DAYSECS];
        $events = $DB->get_records_select('event', "modulename $modsql AND courseid $insql AND visible = 1
            AND timestart >= :from AND timestart <= :to", $params, 'timestart ASC', '*', 0, 10);
        $out = [];
        $today = usergetmidnight($this->now);
        foreach ($events as $event) {
            $modinfo = get_fast_modinfo($event->courseid, $this->user->id);
            $cm = $modinfo->instances[$event->modulename][$event->instance] ?? null;
            if (!$cm || !$cm->uservisible) {
                continue;
            }
            $end = $event->timestart + ($event->timeduration ?: HOURSECS);
            if ($end < $this->now) {
                continue;
            }
            $out[] = [
                'name' => $cm->get_formatted_name(),
                'course' => format_string($courses[$event->courseid]->shortname),
                'start' => (int) $event->timestart,
                'end' => $end,
                'joinurl' => (new \moodle_url($joinpages[$event->modulename], ['id' => $cm->id]))->out(false),
                'service' => $event->modulename,
                'viewurl' => $cm->url->out(false),
                'islive' => $event->timestart <= $this->now,
                'istoday' => $event->timestart < $today + DAYSECS,
            ];
        }
        return $out;
    }

    /**
     * The user's own actions per day (from the standard log), oldest first.
     *
     * @param int $days
     * @param int|null $courseid Only this course.
     * @return array[] ['start' => midnight, 'count' => int]
     */
    public function get_activity(int $days = 30, ?int $courseid = null): array {
        global $DB;
        $start = usergetmidnight($this->now) - ($days - 1) * DAYSECS;
        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $buckets[$i] = ['start' => $start + $i * DAYSECS, 'count' => 0];
        }
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return $buckets;
        }
        $params = ['userid' => $this->user->id, 'start' => $start];
        $where = 'userid = :userid AND timecreated >= :start AND anonymous = 0';
        if ($courseid) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        } else if ($courses = $this->get_courses()) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
            $where .= " AND courseid $insql";
            $params += $inparams;
        } else {
            return $buckets;
        }
        $rs = $DB->get_recordset_select('logstore_standard_log', $where, $params, '', 'id, timecreated');
        foreach ($rs as $log) {
            $i = (int) floor(($log->timecreated - $start) / DAYSECS);
            if (isset($buckets[$i])) {
                $buckets[$i]['count']++;
            }
        }
        $rs->close();
        return $buckets;
    }

    /**
     * Assignment submissions and quiz attempts the user has made in a course.
     *
     * @param int $courseid
     * @return array ['submissions' => int, 'attempts' => int]
     */
    public function get_work_counts(int $courseid): array {
        global $DB;
        $submissions = $DB->count_records_sql("SELECT COUNT(1) FROM {assign_submission} s JOIN {assign} a ON a.id = s.assignment
            WHERE a.course = :course AND s.userid = :userid AND s.latest = 1 AND s.status = 'submitted'",
            ['course' => $courseid, 'userid' => $this->user->id]);
        $attempts = $DB->count_records_sql("SELECT COUNT(1) FROM {quiz_attempts} qa JOIN {quiz} q ON q.id = qa.quiz
            WHERE q.course = :course AND qa.userid = :userid AND qa.preview = 0 AND qa.state = 'finished'",
            ['course' => $courseid, 'userid' => $this->user->id]);
        return ['submissions' => $submissions, 'attempts' => $attempts];
    }

    /**
     * Recent announcements to this student (site-wide and for their courses).
     *
     * @param int $limit
     * @param int $days Only from the last N days.
     * @return \stdClass[]
     */
    public function get_announcements(int $limit = 3, int $days = 30): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_studentportal_ann')) {
            return [];
        }
        return announcements::for_student((int) $this->user->id, $limit, $this->now - $days * DAYSECS);
    }

    /**
     * A grade as a percentage of its item's range.
     *
     * @param float $value
     * @param \grade_item $item
     * @return float
     */
    protected static function percent(float $value, \grade_item $item): float {
        $range = $item->grademax - $item->grademin;
        return $range > 0 ? max(0, min(100, 100 * ($value - $item->grademin) / $range)) : 0.0;
    }
}
