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
use core_date;
use DateTimeImmutable;
use stdClass;

/**
 * Data provider for the teacher dashboard.
 *
 * "Teaching" courses are the courses the user is actively enrolled in and can view all grades in
 * (editing teacher, non-editing teacher, manager). "Students" are users with a student-archetype role
 * and an active enrolment in one of those courses, so unenrolled or suspended users are never counted.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_dashboard_data {
    /** @var string[] Modules whose calendar events count as live classes. */
    const LIVE_MODULES = ['zoom', 'bigbluebuttonbn'];

    /** @var stdClass The teacher. */
    protected $user;

    /** @var int Current time. */
    protected $now;

    /** @var stdClass[]|null Teaching courses, keyed by id. */
    protected $courses = null;

    /** @var array|null Cached result of get_course_stats(). */
    protected $coursestats = null;

    /** @var int Counter for unique SQL parameter prefixes. */
    protected $paramcount = 0;

    /**
     * Constructor.
     *
     * @param stdClass $user The teacher.
     * @param int|null $now Current time (for tests).
     */
    public function __construct(stdClass $user, ?int $now = null) {
        $this->user = $user;
        $this->now = $now ?? time();
    }

    /**
     * Courses the user teaches.
     *
     * @return stdClass[] Keyed by course id.
     */
    public function get_courses(): array {
        if ($this->courses === null) {
            $this->courses = [];
            $courses = enrol_get_users_courses($this->user->id, true, 'id, shortname, fullname, enablecompletion');
            foreach ($courses as $course) {
                if (has_capability('moodle/grade:viewall', context_course::instance($course->id), $this->user)) {
                    $this->courses[$course->id] = $course;
                }
            }
        }
        return $this->courses;
    }

    /**
     * Number of distinct students across the teaching courses.
     *
     * @return int
     */
    public function count_students(): int {
        global $DB;
        [$studentsql, $params] = $this->student_sql();
        return $DB->count_records_sql("SELECT COUNT(DISTINCT st.userid) FROM ($studentsql) st", $params);
    }

    /**
     * Assignments with submissions that need grading, most submissions first.
     *
     * Uses the same rule as the assignment grading table: the latest submission is "submitted" and
     * has no grade, or was modified after it was graded.
     *
     * @return stdClass[] Records with id, course, name, cmid, needsgrading, oldest.
     */
    public function get_assignments_to_grade(): array {
        global $DB;
        [$coursesql, $params] = $this->course_in_sql();
        [$studentsql, $studentparams] = $this->student_sql();
        $params += $studentparams + [
            'assignmodule' => $DB->get_field('modules', 'id', ['name' => 'assign']),
            'submitted' => 'submitted',
        ];
        $sql = "SELECT a.id, a.course, a.name, cm.id AS cmid, COUNT(s.userid) AS needsgrading,
                       MIN(s.timemodified) AS oldest
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = :assignmodule
                       AND cm.deletioninprogress = 0
                  JOIN {assign_submission} s ON s.assignment = a.id AND s.latest = 1 AND s.status = :submitted
                  JOIN ($studentsql) st ON st.courseid = a.course AND st.userid = s.userid
             LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                       AND g.attemptnumber = s.attemptnumber
                 WHERE a.course $coursesql
                       AND (g.id IS NULL OR g.grade IS NULL OR g.grade = -1 OR s.timemodified >= g.timemodified)
              GROUP BY a.id, a.course, a.name, cm.id
              ORDER BY COUNT(s.userid) DESC, MIN(s.timemodified) ASC";
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Activity for the current week (Monday to Sunday in the teacher's timezone).
     *
     * @return array With 'activelearners' (int), 'submissions' (int) and 'days' (list of
     *               ['start' => int, 'count' => int, 'istoday' => bool, 'isfuture' => bool]).
     */
    public function get_week(): array {
        global $DB;
        $tz = core_date::get_user_timezone_object($this->user);
        $today = (new DateTimeImmutable('@' . $this->now))->setTimezone($tz)->setTime(0, 0);
        $monday = $today->modify('monday this week');

        $days = [];
        for ($i = 0; $i <= 7; $i++) {
            $days[$i] = $monday->modify("+$i day")->getTimestamp();
        }
        $weekstart = $days[0];
        $weekend = $days[7];

        // Submissions per day, in one query.
        [$coursesql, $params] = $this->course_in_sql();
        [$studentsql, $studentparams] = $this->student_sql();
        $params += $studentparams + ['submitted' => 'submitted', 'weekstart' => $weekstart, 'weekend' => $weekend];
        $columns = [];
        for ($i = 0; $i < 7; $i++) {
            $columns[] = "SUM(CASE WHEN s.timemodified >= :ds$i AND s.timemodified < :de$i THEN 1 ELSE 0 END) AS d$i";
            $params["ds$i"] = $days[$i];
            $params["de$i"] = $days[$i + 1];
        }
        $sql = "SELECT " . implode(', ', $columns) . "
                  FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment
                  JOIN ($studentsql) st ON st.courseid = a.course AND st.userid = s.userid
                 WHERE a.course $coursesql AND s.latest = 1 AND s.status = :submitted
                       AND s.timemodified >= :weekstart AND s.timemodified < :weekend";
        $counts = $DB->get_record_sql($sql, $params);

        $todaystart = $today->getTimestamp();
        $result = ['days' => [], 'submissions' => 0];
        for ($i = 0; $i < 7; $i++) {
            $count = (int) ($counts->{"d$i"} ?? 0);
            $result['submissions'] += $count;
            $result['days'][] = [
                'start' => $days[$i],
                'count' => $count,
                'istoday' => $days[$i] === $todaystart,
                'isfuture' => $days[$i] > $todaystart,
            ];
        }

        // Students who opened one of the courses since Monday.
        [$studentsql, $params] = $this->student_sql();
        $params['weekstart'] = $weekstart;
        $result['activelearners'] = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT la.userid)
               FROM {user_lastaccess} la
               JOIN ($studentsql) st ON st.courseid = la.courseid AND st.userid = la.userid
              WHERE la.timeaccess >= :weekstart",
            $params
        );
        return $result;
    }

    /**
     * Per-course student count and average activity completion.
     *
     * A course's completion is the mean of its students' progress percentages (completed / tracked
     * activities), computed in SQL rather than per student. Activity availability restrictions are not
     * considered.
     *
     * @return array Keyed by course id: ['students' => int, 'tracked' => int, 'completed' => int,
     *               'completion' => float|null (null when the course tracks no activities or has no students)].
     */
    public function get_course_stats(): array {
        global $CFG, $DB;
        if ($this->coursestats !== null) {
            return $this->coursestats;
        }
        $courses = $this->get_courses();
        $this->coursestats = [];
        if (!$courses) {
            return $this->coursestats;
        }

        [$studentsql, $params] = $this->student_sql();
        $students = $DB->get_records_sql_menu(
            "SELECT st.courseid, COUNT(st.userid) FROM ($studentsql) st GROUP BY st.courseid",
            $params
        );

        $tracked = [];
        $completed = [];
        $completionids = empty($CFG->enablecompletion) ? [] :
            array_keys(array_filter($courses, fn($c) => !empty($c->enablecompletion)));
        if ($completionids) {
            require_once($CFG->libdir . '/completionlib.php');
            [$coursesql, $params] = $DB->get_in_or_equal($completionids, SQL_PARAMS_NAMED, $this->prefix() . 'cc');
            $tracked = $DB->get_records_sql_menu(
                "SELECT cm.course, COUNT(cm.id)
                   FROM {course_modules} cm
                  WHERE cm.course $coursesql AND cm.completion > 0 AND cm.visible = 1 AND cm.deletioninprogress = 0
               GROUP BY cm.course",
                $params
            );

            [$studentsql, $studentparams] = $this->student_sql();
            [$statesql, $stateparams] = $DB->get_in_or_equal([COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
                SQL_PARAMS_NAMED, $this->prefix() . 'cs');
            $completed = $DB->get_records_sql_menu(
                "SELECT cm.course, COUNT(cmc.id)
                   FROM {course_modules_completion} cmc
                   JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                   JOIN ($studentsql) st ON st.courseid = cm.course AND st.userid = cmc.userid
                  WHERE cm.course $coursesql AND cm.completion > 0 AND cm.visible = 1 AND cm.deletioninprogress = 0
                        AND cmc.completionstate $statesql
               GROUP BY cm.course",
                $params + $studentparams + $stateparams
            );
        }

        foreach ($courses as $courseid => $course) {
            $n = (int) ($students[$courseid] ?? 0);
            $t = (int) ($tracked[$courseid] ?? 0);
            $c = (int) ($completed[$courseid] ?? 0);
            $this->coursestats[$courseid] = [
                'students' => $n,
                'tracked' => $t,
                'completed' => $c,
                'completion' => ($n && $t) ? 100 * $c / ($n * $t) : null,
            ];
        }
        return $this->coursestats;
    }

    /**
     * Average activity completion across all teaching courses that track completion.
     *
     * Weighted by student-activity pairs, so it equals the mean progress of every student in every such course.
     *
     * @return float|null Percentage, or null when no course tracks completion.
     */
    public function get_average_completion(): ?float {
        $possible = 0;
        $done = 0;
        foreach ($this->get_course_stats() as $stats) {
            $possible += $stats['students'] * $stats['tracked'];
            $done += $stats['completed'];
        }
        return $possible ? 100 * $done / $possible : null;
    }

    /**
     * Today's live classes: Zoom and BigBlueButton calendar events in the teaching courses.
     *
     * Recurring Zoom meetings have one calendar event per occurrence, so they are included.
     *
     * @return stdClass[] Event records with id, name, modulename, instance, courseid, timestart, timeduration.
     */
    public function get_live_classes_today(): array {
        global $DB;
        $tz = core_date::get_user_timezone_object($this->user);
        $today = (new DateTimeImmutable('@' . $this->now))->setTimezone($tz)->setTime(0, 0);

        [$coursesql, $params] = $this->course_in_sql();
        [$modsql, $modparams] = $DB->get_in_or_equal(self::LIVE_MODULES, SQL_PARAMS_NAMED, 'lm');
        $params += $modparams + [
            'daystart' => $today->getTimestamp(),
            'dayend' => $today->modify('+1 day')->getTimestamp(),
        ];
        return array_values($DB->get_records_sql(
            "SELECT ev.id, ev.name, ev.modulename, ev.instance, ev.courseid, ev.timestart, ev.timeduration
               FROM {event} ev
              WHERE ev.courseid $coursesql AND ev.modulename $modsql AND ev.visible = 1
                    AND ev.timestart >= :daystart AND ev.timestart < :dayend
           ORDER BY ev.timestart, ev.id",
            $params
        ));
    }

    /**
     * Current time used for all calculations.
     *
     * @return int
     */
    public function get_now(): int {
        return $this->now;
    }

    /**
     * IN clause for the teaching course ids.
     *
     * @return array [sql, params]
     */
    protected function course_in_sql(): array {
        global $DB;
        return $DB->get_in_or_equal(array_keys($this->get_courses()), SQL_PARAMS_NAMED, $this->prefix() . 'c', true, 0);
    }

    /**
     * Subquery of (courseid, userid) for actively enrolled students in the teaching courses.
     *
     * Every call uses fresh parameter names, so it can appear several times in one query.
     *
     * @return array [sql, params]
     */
    protected function student_sql(): array {
        return student_sql::active_students(array_keys($this->get_courses()), $this->now, $this->prefix());
    }

    /**
     * Quizzes with attempts waiting for manual grading (essay and other manually graded questions), most first.
     *
     * Uses the same rule as the quiz "Manual grading" report: a finished, non-preview attempt with a question
     * whose latest step is in the "needsgrading" state.
     *
     * @return stdClass[] Records with id, course, name, cmid, needsgrading (attempts), oldest (time finished).
     */
    public function get_quizzes_to_grade(): array {
        global $DB;
        [$coursesql, $params] = $this->course_in_sql();
        [$studentsql, $studentparams] = $this->student_sql();
        $params += $studentparams + [
            'quizmodule' => $DB->get_field('modules', 'id', ['name' => 'quiz']),
            'finished' => 'finished',
            'needsgrading' => 'needsgrading',
        ];
        $sql = "SELECT q.id, q.course, q.name, cm.id AS cmid, COUNT(DISTINCT qa.id) AS needsgrading,
                       MIN(qa.timefinish) AS oldest
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = :quizmodule
                       AND cm.deletioninprogress = 0
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = :finished AND qa.preview = 0
                  JOIN ($studentsql) st ON st.courseid = q.course AND st.userid = qa.userid
                  JOIN {question_attempts} qta ON qta.questionusageid = qa.uniqueid
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qta.id
                       AND qas.sequencenumber = (SELECT MAX(latest.sequencenumber)
                                                   FROM {question_attempt_steps} latest
                                                  WHERE latest.questionattemptid = qta.id)
                 WHERE q.course $coursesql AND qas.state = :needsgrading
              GROUP BY q.id, q.course, q.name, cm.id
              ORDER BY COUNT(DISTINCT qa.id) DESC, MIN(qa.timefinish) ASC";
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Unique parameter name prefix.
     *
     * @return string
     */
    protected function prefix(): string {
        return 'td' . ($this->paramcount++) . '_';
    }
}
