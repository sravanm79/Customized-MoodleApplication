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

namespace theme_iiitdwd\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core_date;
use moodle_url;
use local_studentportal\local\student_data;

/**
 * Student dashboard panel on /my/: what is due, live classes, recent grades and feedback, activity and courses.
 * Data comes from local_studentportal; without that plugin there is no student dashboard.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_dashboard implements renderable, templatable {
    /** @var int Maximum rows in "Up next". */
    const UPNEXT_LIMIT = 6;

    /** @var int Maximum course cards. */
    const COURSES_LIMIT = 6;

    /** @var student_data */
    protected $data;

    /**
     * @param student_data $data
     */
    public function __construct(student_data $data) {
        $this->data = $data;
    }

    /**
     * Whether the local_studentportal plugin is available.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(student_data::class);
    }

    /**
     * Shown to users enrolled as a student in at least one course.
     *
     * @return bool
     */
    public function should_display(): bool {
        return !empty($this->data->get_courses());
    }

    /**
     * Template context for theme_iiitdwd/student_dashboard.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;
        $now = $this->data->get_now();
        $courses = $this->data->get_courses();
        // 30 days, like Moodle's Timeline default: a test that closes in four weeks is still worth seeing.
        $upcoming = $this->data->get_upcoming(30, 20);
        $live = $this->data->get_live_classes(7);
        $feedback = $this->data->get_recent_feedback(4);

        $grades = [];
        $completions = [];
        $cards = [];
        foreach ($courses as $course) {
            $grade = $this->data->get_course_grade($course->id);
            $completion = $this->data->get_completion($course);
            if ($grade !== null) {
                $grades[] = $grade;
            }
            if ($completion !== null) {
                $completions[] = $completion;
            }
            if (count($cards) < self::COURSES_LIMIT) {
                $context = \context_course::instance($course->id);
                $cards[] = [
                    'shortname' => format_string($course->shortname, true, ['context' => $context]),
                    'fullname' => format_string($course->fullname, true, ['context' => $context]),
                    'lastvisit' => $course->lastaccess
                        ? get_string('sdashlastvisit', 'theme_iiitdwd', format_time($now - $course->lastaccess))
                        : get_string('sdashnotvisited', 'theme_iiitdwd'),
                    'hascompletion' => $completion !== null,
                    'completion' => $completion === null ? 0 : (int) round($completion),
                    'hasgrade' => $grade !== null,
                    'grade' => $grade === null ? null : (int) round($grade),
                    'variant' => count($cards) % 2 ? 'green' : 'blue',
                    'contenturl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'gradeurl' => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id]))->out(false),
                ];
            }
        }

        $weekend = $now + 7 * DAYSECS;
        $dueweek = count(array_filter($upcoming, fn($u) => $u['time'] <= $weekend));
        $livetoday = count(array_filter($live, fn($l) => $l['istoday']));

        // Hero.
        $hour = (int) userdate($now, '%H', core_date::get_user_timezone($USER), false);
        $greeting = $hour < 12 ? 'greetingmorning' : ($hour < 17 ? 'greetingafternoon' : 'greetingevening');
        $continue = reset($courses);

        // This week: the student's own activity per day, Monday to Sunday.
        $activity = $this->data->get_activity(14);
        $monday = usergetmidnight($now) - ((int) userdate($now, '%u', 99, false) - 1) * DAYSECS;
        $week = array_values(array_filter($activity, fn($d) => $d['start'] >= $monday));
        $max = max(1, ...array_map(fn($d) => $d['count'], $week ?: [['count' => 0]]));
        $bars = [];
        for ($i = 0; $i < 7; $i++) {
            $start = $monday + $i * DAYSECS;
            $count = 0;
            foreach ($week as $day) {
                if ($day['start'] === $start) {
                    $count = $day['count'];
                }
            }
            $label = userdate($start, '%a');
            $bars[] = [
                'label' => $label,
                'height' => $count ? max(8, (int) round(100 * $count / $max)) : 4,
                'istoday' => $start === usergetmidnight($now),
                'isfuture' => $start > $now,
                'title' => get_string('sdashbartitle', 'theme_iiitdwd', ['day' => $label, 'count' => $count]),
            ];
        }
        $activedays = count(array_filter($week, fn($d) => $d['count'] > 0));

        $announcements = [];
        foreach ($this->data->get_announcements(3) as $a) {
            $author = \core_user::get_user($a->userid);
            $time = $a->timesent ?: $a->timecreated;
            $announcements[] = [
                'subject' => format_string($a->subject),
                'author' => $author ? fullname($author) : '',
                'when' => get_string('ago', 'core_message', format_time($now - $time)),
                'isnew' => $time > $now - 3 * DAYSECS,
                'snippet' => shorten_text(html_to_text(format_text($a->message, $a->messageformat), 0, false), 160),
                'url' => (new moodle_url('/local/studentportal/announcements.php', ['id' => $a->id]))->out(false),
            ];
        }

        return [
            'announcements' => $announcements,
            'hasannouncements' => !empty($announcements),
            'announcementsurl' => (new moodle_url('/local/studentportal/announcements.php'))->out(false),
            'date' => userdate($now, get_string('strftimedaydate', 'langconfig')),
            'greeting' => get_string($greeting, 'theme_iiitdwd', $USER->firstname),
            'summary' => get_string('sdashsummary', 'theme_iiitdwd', [
                'due' => \html_writer::tag('strong', $dueweek),
                'live' => \html_writer::tag('strong', $livetoday),
                'courses' => \html_writer::tag('strong', count($courses)),
            ]),
            'continueurl' => $continue ? (new moodle_url('/course/view.php', ['id' => $continue->id]))->out(false) : null,
            'continuename' => $continue ? format_string($continue->shortname) : null,
            'performanceurl' => (new moodle_url('/local/studentportal/performance.php'))->out(false),
            'week' => ['bars' => $bars, 'activedays' => $activedays],
            'metrics' => [
                $this->metric($output, 'courses', count($courses), 'i/course', 'blue'),
                $this->metric($output, 'avggrade', $grades ? round(array_sum($grades) / count($grades)) . '%' : '–',
                    'i/grades', 'green'),
                $this->metric($output, 'dueweek', $dueweek, 'i/calendar', 'amber'),
                $this->metric($output, 'completion',
                    $completions ? round(array_sum($completions) / count($completions)) . '%' : '–', 'i/stats', 'teal'),
            ],
            'upnext' => $this->export_upnext(array_slice($upcoming, 0, self::UPNEXT_LIMIT), $now),
            'hasupnext' => !empty($upcoming),
            'upnextmore' => count($upcoming) > self::UPNEXT_LIMIT,
            'timelineurl' => (new moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
            'live' => $this->export_live($live, $now),
            'haslive' => !empty($live),
            'calendarurl' => (new moodle_url('/calendar/view.php', ['view' => 'month']))->out(false),
            'feedback' => array_map(fn($f) => [
                'name' => $f['name'],
                'course' => $f['course'],
                'url' => $f['url'],
                'grade' => $f['percent'] === null ? null : (int) round($f['percent']) . '%',
                'level' => $f['percent'] === null ? 'none' : ($f['percent'] >= 75 ? 'good' : ($f['percent'] >= 50 ? 'ok' : 'low')),
                'snippet' => shorten_text(html_to_text($f['feedback'], 0, false), 140),
                'when' => get_string('ago', 'core_message', format_time($now - $f['time'])),
            ], $feedback),
            'hasfeedback' => !empty($feedback),
            'courses' => $cards,
            'morecourses' => count($courses) > self::COURSES_LIMIT,
            'mycoursesurl' => (new moodle_url('/my/courses.php'))->out(false),
            'icons' => [
                'arrow' => $output->pix_icon('t/right', ''),
                'calendar' => $output->pix_icon('i/calendar', ''),
                'check' => $output->pix_icon('i/checked', ''),
                'grades' => $output->pix_icon('i/grades', ''),
                'quiz' => $output->pix_icon('monologo', '', 'mod_quiz'),
                'assign' => $output->pix_icon('monologo', '', 'mod_assign'),
                'other' => $output->pix_icon('i/calendareventdescription', ''),
                'announce' => $output->pix_icon('i/email', ''),
            ],
        ];
    }

    /**
     * One metric card.
     *
     * @param renderer_base $output
     * @param string $key
     * @param int|string $value
     * @param string $icon
     * @param string $variant
     * @return array
     */
    protected function metric(renderer_base $output, string $key, $value, string $icon, string $variant): array {
        return [
            'label' => get_string('sdashmetric' . $key, 'theme_iiitdwd'),
            'value' => $value,
            'icon' => $output->pix_icon($icon, ''),
            'variant' => $variant,
        ];
    }

    /**
     * "Up next" rows.
     *
     * @param array[] $items From student_data::get_upcoming().
     * @param int $now
     * @return array
     */
    protected function export_upnext(array $items, int $now): array {
        $out = [];
        foreach ($items as $item) {
            $soon = $item['time'] - $now < DAYSECS;
            $out[] = [
                'name' => $item['name'],
                'course' => $item['course'],
                'type' => $item['modname'] === 'quiz' ? 'quiz' : ($item['modname'] === 'assign' ? 'assign' : 'other'),
                'isquiz' => $item['modname'] === 'quiz',
                'isassign' => $item['modname'] === 'assign',
                'when' => $item['overdue']
                    ? get_string('sdashoverdue', 'theme_iiitdwd', format_time($now - $item['time']))
                    : userdate($item['time'], get_string('strftimedaydatetime', 'langconfig')),
                'overdue' => $item['overdue'],
                'soon' => !$item['overdue'] && $soon,
                'actionname' => $item['actionname'],
                'actionurl' => $item['actionurl'],
            ];
        }
        return $out;
    }

    /**
     * Live class rows.
     *
     * @param array[] $items From student_data::get_live_classes().
     * @param int $now
     * @return array
     */
    protected function export_live(array $items, int $now): array {
        $out = [];
        foreach ($items as $item) {
            $format = $item['istoday'] ? get_string('strftimetime', 'langconfig') : get_string('strftimedaydatetime', 'langconfig');
            $out[] = [
                'name' => $item['name'],
                'course' => $item['course'],
                'time' => userdate($item['start'], $format),
                'islive' => $item['islive'],
                'status' => get_string($item['islive'] ? 'livelive' : ($item['istoday'] ? 'liveupcoming' : 'sdashlater'), 'theme_iiitdwd'),
                'joinurl' => $item['joinurl'],
                'viewurl' => $item['viewurl'],
            ];
        }
        return $out;
    }
}
