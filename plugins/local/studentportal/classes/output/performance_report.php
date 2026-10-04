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

namespace local_studentportal\output;

use local_studentportal\local\student_data;
use moodle_url;

/**
 * Context for the local_studentportal/performance template: a student's grades with feedback, completion, activity
 * and insights. Used for the student's own "My performance" page and for the teacher/admin "Student report".
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class performance_report {

    /**
     * Builds the template context.
     *
     * @param student_data $data The student's data.
     * @param \core\output\renderer_base $output
     * @param int|null $onlycourseid Limit to one course (teacher view), or null for all the student's courses.
     * @return array
     */
    public static function build(student_data $data, \core\output\renderer_base $output, ?int $onlycourseid = null): array {
        $courses = $data->get_courses();
                if ($onlycourseid) {
                    $courses = array_intersect_key($courses, [$onlycourseid => true]);
                }
        $pct = fn(?float $v) => $v === null ? null : (int) round($v);

        $cards = [];
        $grades = [];
        $completions = [];
        $strongest = null;
        $weakest = null;
        $inactive = [];
        $totalsubmissions = 0;
        $totalattempts = 0;
        foreach ($courses as $course) {
            $grade = $data->get_course_grade($course->id);
            $completion = $data->get_completion($course);
            $items = $data->get_grade_items($course->id);
            $work = $data->get_work_counts($course->id);
            $totalsubmissions += $work['submissions'];
            $totalattempts += $work['attempts'];
            if ($grade !== null) {
                $grades[] = $grade;
            }
            if ($completion !== null) {
                $completions[] = $completion;
            }
            foreach ($items as $item) {
                if ($item['percent'] === null) {
                    continue;
                }
                if (!$strongest || $item['percent'] > $strongest['percent']) {
                    $strongest = $item + ['coursename' => format_string($course->shortname)];
                }
                if (!$weakest || $item['percent'] < $weakest['percent']) {
                    $weakest = $item + ['coursename' => format_string($course->shortname)];
                }
            }
            if ($course->lastaccess && $course->lastaccess < $data->get_now() - 7 * DAYSECS) {
                $inactive[] = ['course' => format_string($course->shortname),
                    'days' => (int) floor(($data->get_now() - $course->lastaccess) / DAYSECS)];
            }
            $cards[] = [
                'id' => $course->id,
                'fullname' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'gradesurl' => (new moodle_url('/grade/report/user/index.php', ['id' => $course->id]))->out(false),
                'hasgrade' => $grade !== null,
                'grade' => $pct($grade),
                'hascompletion' => $completion !== null,
                'completion' => $pct($completion),
                'lastaccess' => $course->lastaccess ? userdate($course->lastaccess, get_string('strftimedatetimeshort', 'langconfig'))
                    : get_string('never'),
                'submissions' => $work['submissions'],
                'attempts' => $work['attempts'],
                'items' => array_map(fn($i) => $i + [
                    'haspercent' => $i['percent'] !== null,
                    'percentround' => $pct($i['percent']),
                    'level' => $i['percent'] === null ? 'none' : ($i['percent'] >= 75 ? 'good' : ($i['percent'] >= 50 ? 'ok' : 'low')),
                    'hasfeedback' => $i['feedback'] !== '',
                    'date' => userdate($i['time'], get_string('strftimedatefullshort', 'langconfig')),
                ], $items),
                'hasitems' => !empty($items),
            ];
        }

        // Activity: last 30 days.
        $activity = $data->get_activity(30, $onlycourseid);
        $max = max(1, ...array_column($activity, 'count'));
        $activedays = count(array_filter($activity, fn($d) => $d['count'] > 0));
        $bars = array_map(fn($d) => [
            'height' => $d['count'] ? max(6, (int) round(100 * $d['count'] / $max)) : 3,
            'count' => $d['count'],
            'title' => get_string('activitybar', 'local_studentportal',
                ['day' => userdate($d['start'], get_string('strftimedateshort', 'langconfig')), 'count' => $d['count']]),
            'istoday' => $d['start'] === usergetmidnight($data->get_now()),
        ], $activity);

        $insights = [];
        // Praise only a genuinely good result; a low one (even if it is the only grade) is something to work on.
        if ($strongest && $strongest['percent'] >= 60) {
            $insights[] = ['type' => 'good', 'text' => get_string('insight_strongest', 'local_studentportal',
                ['name' => $strongest['name'], 'course' => $strongest['coursename'], 'percent' => $pct($strongest['percent'])])];
        }
        if ($weakest && $weakest['percent'] < 60) {
            $insights[] = ['type' => 'low', 'text' => get_string('insight_weakest', 'local_studentportal',
                ['name' => $weakest['name'], 'course' => $weakest['coursename'], 'percent' => $pct($weakest['percent'])])];
        }
        foreach ($inactive as $i) {
            $insights[] = ['type' => 'warn', 'text' => get_string('insight_inactive', 'local_studentportal', $i)];
        }
        $insights[] = ['type' => $activedays >= 10 ? 'good' : 'info', 'text' => get_string('insight_activedays', 'local_studentportal',
            $activedays)];

        return [
            'metrics' => [
                ['label' => get_string('metric_courses', 'local_studentportal'), 'value' => count($courses), 'variant' => 'blue',
                    'icon' => $output->pix_icon('i/course', '')],
                ['label' => get_string('metric_avggrade', 'local_studentportal'),
                    'value' => $grades ? round(array_sum($grades) / count($grades)) . '%' : '–', 'variant' => 'green',
                    'icon' => $output->pix_icon('i/grades', '')],
                ['label' => get_string('metric_completion', 'local_studentportal'),
                    'value' => $completions ? round(array_sum($completions) / count($completions)) . '%' : '–', 'variant' => 'teal',
                    'icon' => $output->pix_icon('i/completion-auto-y', '')],
                ['label' => get_string('metric_activedays', 'local_studentportal'), 'value' => $activedays . ' / 30', 'variant' => 'amber',
                    'icon' => $output->pix_icon('i/calendar', '')],
                ['label' => get_string('metric_submissions', 'local_studentportal'), 'value' => $totalsubmissions, 'variant' => 'blue',
                    'icon' => $output->pix_icon('monologo', '', 'mod_assign')],
                ['label' => get_string('metric_tests', 'local_studentportal'), 'value' => $totalattempts, 'variant' => 'green',
                    'icon' => $output->pix_icon('monologo', '', 'mod_quiz')],
            ],
            'bars' => $bars,
            'activedays' => $activedays,
            'insights' => $insights,
            'courses' => $cards,
            'hascourses' => !empty($cards),
            'dashboardurl' => (new moodle_url('/my/'))->out(false),
        ];
    }
}
