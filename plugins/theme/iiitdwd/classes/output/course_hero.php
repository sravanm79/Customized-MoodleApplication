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
use core_course_category;
use stdClass;
use theme_iiitdwd\local\live_session_stats;

/**
 * Course page hero (title, code, category, dates) with the Live Session Stats widget.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_hero implements renderable, templatable {
    /** @var stdClass */
    protected $course;

    /** @var stdClass */
    protected $user;

    /** @var array|null Attendance from live_session_stats::get(), for tests; looked up when null. */
    protected $stats;

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param stdClass $user Viewer.
     * @param array|null $stats Precomputed attendance (optional).
     */
    public function __construct(stdClass $course, stdClass $user, ?array $stats = null) {
        $this->course = $course;
        $this->user = $user;
        $this->stats = $stats;
    }

    /**
     * Template context for theme_iiitdwd/course_hero.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = \context_course::instance($this->course->id);
        $category = core_course_category::get($this->course->category, IGNORE_MISSING, true);
        $dateformat = get_string('strftimedate', 'langconfig');

        $stats = $this->stats ?? live_session_stats::get($this->course, $this->user);
        $live = null;
        if ($stats !== null) {
            $percent = $stats['percent'];
            $live = [
                'title' => get_string($stats['scope'] === 'course' ? 'livestatscourse' : 'livestatsmine', 'theme_iiitdwd'),
                'hasdata' => $percent !== null,
                'percent' => $percent ?? 0,
                // Gauge arc: 0-360 degrees for the conic-gradient.
                'degrees' => round(3.6 * ($percent ?? 0)),
                'attended' => $stats['attended'],
                'missed' => $stats['missed'],
                'sessions' => get_string('nsessions', 'theme_iiitdwd', $stats['sessions']),
            ];
        }

        return [
            'fullname' => format_string($this->course->fullname, true, ['context' => $context]),
            'shortname' => format_string($this->course->shortname, true, ['context' => $context]),
            'category' => $category ? $category->get_formatted_name() : '',
            'startdate' => $this->course->startdate ? userdate($this->course->startdate, $dateformat) : '',
            'enddate' => $this->course->enddate ? userdate($this->course->enddate, $dateformat) : '',
            'hasstarted' => $this->course->startdate <= time(),
            'live' => $live,
            'haslive' => $live !== null,
            'icons' => [
                'calendar' => $output->pix_icon('i/calendar', ''),
            ],
        ];
    }
}
