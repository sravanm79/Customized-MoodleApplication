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
use theme_iiitdwd\local\teacher_dashboard_data;

/**
 * Teacher dashboard panel shown above the blocks on /my/.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_dashboard implements renderable, templatable {
    /** @var int Maximum rows in "Needs your attention". */
    const ATTENTION_LIMIT = 5;

    /** @var int Maximum cards in "Your courses". */
    const COURSES_LIMIT = 6;

    /** @var teacher_dashboard_data */
    protected $data;

    /**
     * Constructor.
     *
     * @param teacher_dashboard_data $data
     */
    public function __construct(teacher_dashboard_data $data) {
        $this->data = $data;
    }

    /**
     * Whether the panel has anything to show (the user teaches at least one course).
     *
     * @return bool
     */
    public function should_display(): bool {
        return !empty($this->data->get_courses());
    }

    /**
     * Template context for theme_iiitdwd/teacher_dashboard.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $now = $this->data->get_now();
        $courses = $this->data->get_courses();
        $tasks = $this->grading_tasks();
        $live = $this->data->get_live_classes_today();
        $week = $this->data->get_week();
        $completion = $this->data->get_average_completion();
        $tograde = array_sum(array_column($tasks, 'needsgrading'));

        // Hero.
        $hour = (int) userdate($now, '%H', core_date::get_user_timezone($USER), false);
        $greeting = $hour < 12 ? 'greetingmorning' : ($hour < 17 ? 'greetingafternoon' : 'greetingevening');
        $attention = $this->export_attention($tasks, $courses);

        // This week chart: bar heights relative to the busiest day.
        $max = max(1, ...array_column($week['days'], 'count'));
        $bars = [];
        foreach ($week['days'] as $day) {
            $label = userdate($day['start'], '%a');
            $bars[] = [
                'label' => $label,
                'count' => $day['count'],
                'height' => $day['count'] ? max(8, round(100 * $day['count'] / $max)) : 4,
                'istoday' => $day['istoday'],
                'isfuture' => $day['isfuture'],
                'title' => get_string('chartbartitle', 'theme_iiitdwd', ['day' => $label, 'count' => $day['count']]),
            ];
        }

        return [
            'date' => userdate($now, get_string('strftimedaydate', 'langconfig')),
            // Escaped by the template.
            'greeting' => get_string($greeting, 'theme_iiitdwd', $USER->firstname),
            'summary' => get_string('summary', 'theme_iiitdwd', [
                'submissions' => \html_writer::tag('strong', $tograde),
                'classes' => \html_writer::tag('strong', count($live)),
                'courses' => \html_writer::tag('strong', count($courses)),
            ]),
            'gradeurl' => $attention ? $attention[0]['url'] : null,
            'messageurl' => (new moodle_url('/message/index.php'))->out(false),
            'week' => [
                'activelearners' => $week['activelearners'],
                'submissions' => $week['submissions'],
                'bars' => $bars,
            ],
            'metrics' => [
                $this->metric($output, 'courses', count($courses), 'i/course', 'blue'),
                $this->metric($output, 'students', $this->data->count_students(), 'i/users', 'green'),
                $this->metric($output, 'awaitinggrading', $tograde, 'i/grades', 'amber'),
                $this->metric($output, 'avgcompletion',
                    $completion === null ? '–' : round($completion) . '%', 'i/stats', 'teal'),
            ],
            'attention' => $attention,
            'hasattention' => !empty($attention),
            'attentioncount' => count($tasks),
            'attentionmore' => count($tasks) > self::ATTENTION_LIMIT
                ? get_string('attentionmore', 'theme_iiitdwd', count($tasks) - self::ATTENTION_LIMIT) : null,
            'live' => $this->export_live($live, $courses, $now),
            'haslive' => !empty($live),
            'calendarurl' => (new moodle_url('/calendar/view.php', ['view' => 'day']))->out(false),
            'courses' => $this->export_courses($courses),
            'morecourses' => count($courses) > self::COURSES_LIMIT,
            'mycoursesurl' => (new moodle_url('/my/courses.php'))->out(false),
            'icons' => [
                'warning' => $output->pix_icon('i/warning', ''),
                'arrow' => $output->pix_icon('t/right', ''),
                'calendar' => $output->pix_icon('i/calendar', ''),
                'check' => $output->pix_icon('i/checked', ''),
            ],
        ];
    }

    /**
     * One metric card.
     *
     * @param renderer_base $output
     * @param string $key Lang string key suffix.
     * @param int|string $value
     * @param string $icon Core pix key.
     * @param string $variant Colour modifier class suffix.
     * @return array
     */
    protected function metric(renderer_base $output, string $key, $value, string $icon, string $variant): array {
        return [
            'label' => get_string('metric' . $key, 'theme_iiitdwd'),
            'value' => $value,
            'icon' => $output->pix_icon($icon, ''),
            'variant' => $variant,
        ];
    }

    /**
     * "Your courses" cards, alternating blue and green headers.
     *
     * @param \stdClass[] $courses Teaching courses.
     * @return array
     */
    protected function export_courses(array $courses): array {
        $stats = $this->data->get_course_stats();
        $cards = [];
        foreach (array_slice($courses, 0, self::COURSES_LIMIT, true) as $courseid => $course) {
            $context = \context_course::instance($courseid);
            $completion = $stats[$courseid]['completion'];
            $cards[] = [
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'students' => get_string('nstudents', 'theme_iiitdwd', $stats[$courseid]['students']),
                'hascompletion' => $completion !== null,
                'completion' => $completion === null ? 0 : (int) round($completion),
                'variant' => count($cards) % 2 ? 'green' : 'blue',
                'gradeurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $courseid]))->out(false),
                'contenturl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
                'peopleurl' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
            ];
        }
        return $cards;
    }

    /**
     * Everything waiting to be graded in the teacher's courses: assignment submissions and quiz attempts with
     * manually graded questions, most items first, then the longest waiting.
     *
     * @return \stdClass[] Records with id, course, name, cmid, needsgrading, oldest, and modname (assign or quiz).
     */
    protected function grading_tasks(): array {
        $tasks = [];
        foreach (['assign' => $this->data->get_assignments_to_grade(), 'quiz' => $this->data->get_quizzes_to_grade()]
                as $modname => $records) {
            foreach ($records as $record) {
                $record->modname = $modname;
                $tasks[] = $record;
            }
        }
        usort($tasks, fn($a, $b) => [$b->needsgrading, $a->oldest] <=> [$a->needsgrading, $b->oldest]);
        return $tasks;
    }

    /**
     * "Needs your attention" rows, each linking straight to its grading page: the assignment grading table, or the
     * quiz "Manual grading" report.
     *
     * @param \stdClass[] $tasks From grading_tasks().
     * @param \stdClass[] $courses
     * @return array
     */
    protected function export_attention(array $tasks, array $courses): array {
        $items = [];
        foreach (array_slice($tasks, 0, self::ATTENTION_LIMIT) as $task) {
            $context = \context_module::instance($task->cmid);
            $coursecontext = \context_course::instance($task->course);
            $name = format_string($task->name, true, ['context' => $context]);
            $course = format_string($courses[$task->course]->shortname, true, ['context' => $coursecontext]);
            $tag = get_string('submissionstograde', 'theme_iiitdwd', $task->needsgrading);
            $url = $task->modname === 'quiz'
                ? new moodle_url('/mod/quiz/report.php', ['id' => $task->cmid, 'mode' => 'grading'])
                : new moodle_url('/mod/assign/view.php', ['id' => $task->cmid, 'action' => 'grading']);
            $items[] = [
                'name' => $name,
                'course' => $course,
                'coursefullname' => format_string($courses[$task->course]->fullname, true, ['context' => $coursecontext]),
                'type' => get_string('modulename', $task->modname),
                'tag' => $tag,
                'since' => get_string('oldestsubmission', 'theme_iiitdwd', format_time($this->data->get_now() - $task->oldest)),
                'url' => $url->out(false),
                'arialabel' => get_string('gradeitemlink', 'theme_iiitdwd', ['name' => $name, 'course' => $course, 'tag' => $tag]),
            ];
        }
        return $items;
    }

    /**
     * "Today's live classes" rows.
     *
     * @param \stdClass[] $events From teacher_dashboard_data::get_live_classes_today().
     * @param \stdClass[] $courses
     * @param int $now
     * @return array
     */
    protected function export_live(array $events, array $courses, int $now): array {
        $items = [];
        foreach ($events as $event) {
            $cm = get_fast_modinfo($event->courseid)->instances[$event->modulename][$event->instance] ?? null;
            // Zoom events carry no duration; assume an hour so "Live now" still works.
            $end = $event->timestart + ($event->timeduration ?: HOURSECS);
            $status = $now < $event->timestart ? 'upcoming' : ($now < $end ? 'live' : 'ended');
            $items[] = [
                'time' => userdate($event->timestart, get_string('strftimetime', 'langconfig')),
                'name' => $cm ? $cm->get_formatted_name() : format_string($event->name),
                'course' => format_string($courses[$event->courseid]->shortname),
                'url' => ($cm && $cm->url ? $cm->url : new moodle_url('/course/view.php', ['id' => $event->courseid]))
                    ->out(false),
                'status' => get_string('live' . $status, 'theme_iiitdwd'),
                'islive' => $status === 'live',
                'isended' => $status === 'ended',
            ];
        }
        return $items;
    }
}
