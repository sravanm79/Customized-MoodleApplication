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

namespace local_gradesheet\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_gradesheet\local\sheet_manager;
use local_gradesheet\local\stats;
use moodle_url;

/**
 * One grade sheet as shown on the course's Grade sheets page.
 *
 * Teachers (local/gradesheet:manage) get the full statistics and every student's score. Students get only their
 * own score, its normalised value (percent of the maximum) and percentile, and, when at least the minimum cohort
 * size have grades, the anonymised class distribution (bucket counts) with the mean, median and quartiles; never the
 * minimum, maximum or anyone else's score.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sheet_view implements renderable, templatable {
    /** @var int Histogram buckets (10 % wide). */
    const BUCKETS = 10;

    /** @var array ['sheet' => stdClass, 'item' => grade_item] */
    protected $entry;

    /** @var int[] Student user ids. */
    protected $students;

    /** @var int|null The student viewing, or null for the teacher view. */
    protected $viewer;

    /** @var string[] Student names by id (teacher view). */
    protected $names;

    /**
     * @param array $entry From sheet_manager::get_sheet(s)().
     * @param \stdClass[] $students Course students by id.
     * @param int|null $viewer The student viewing, or null for the teacher view.
     */
    public function __construct(array $entry, array $students, ?int $viewer) {
        $this->entry = $entry;
        $this->students = array_map('intval', array_keys($students));
        $this->names = array_map(fn($user) => fullname($user), $students);
        $this->viewer = $viewer;
    }

    /**
     * Template context (local_gradesheet/sheet_teacher or local_gradesheet/sheet_student).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $sheet = $this->entry['sheet'];
        $item = $this->entry['item'];
        $max = (float) $item->grademax;
        $scores = sheet_manager::get_scores($item, $this->students);
        $normalise = fn(float $score) => $max > 0 ? 100 * $score / $max : 0.0;
        $percents = array_map(fn($s) => $normalise($s['score']), $scores);
        $summary = stats::summary(array_values($percents));

        $context = [
            'id' => $sheet->id,
            'name' => format_string($sheet->name),
            'date' => userdate($sheet->timecreated, get_string('strftimedatefullshort', 'langconfig')),
            'max' => self::number($max),
            'n' => count($scores),
        ];
        return $this->viewer === null
            ? $context + $this->teacher($scores, $percents, $summary)
            : $context + $this->student($scores, $percents, $summary, $normalise);
    }

    /**
     * Teacher view: everything.
     *
     * @param array $scores
     * @param float[] $percents
     * @param array|null $summary
     * @return array
     */
    protected function teacher(array $scores, array $percents, ?array $summary): array {
        $rows = [];
        foreach ($scores as $userid => $score) {
            $rows[] = [
                'fullname' => $this->names[$userid] ?? '',
                'score' => self::number($score['score']),
                'percent' => self::number($percents[$userid]),
                'sort' => $percents[$userid],
            ];
        }
        usort($rows, fn($a, $b) => $b['sort'] <=> $a['sort']);
        return [
            'hasscores' => !empty($scores),
            'stats' => $summary ? self::stats($summary, true) : null,
            'box' => $summary ? self::box($summary) : null,
            'histogram' => self::histogram($percents, null),
            'students' => $rows,
            'missing' => count($this->students) - count($scores),
            'gradebookurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => $this->entry['sheet']->courseid]))
                ->out(false),
            'deleteurl' => (new moodle_url('/local/gradesheet/index.php', ['id' => $this->entry['sheet']->courseid,
                'delete' => $this->entry['sheet']->id]))->out(false),
        ];
    }

    /**
     * Student view: their own score and percentile; the anonymised distribution once the cohort is large enough.
     *
     * @param array $scores
     * @param float[] $percents
     * @param array|null $summary
     * @param callable $normalise
     * @return array
     */
    protected function student(array $scores, array $percents, ?array $summary, callable $normalise): array {
        $own = $scores[$this->viewer] ?? null;
        $hasscore = $own !== null && !$own['hidden'];
        $mincohort = max(2, (int) get_config('local_gradesheet', 'mincohort'));
        $showclass = $summary && $summary['n'] >= $mincohort;
        $percent = $hasscore ? $normalise($own['score']) : null;
        $percentile = $hasscore ? stats::share_scored_lower($percent, array_values($percents)) : null;
        return [
            'hasscore' => $hasscore,
            'score' => $hasscore ? self::number($own['score']) : null,
            'percent' => $hasscore ? self::number($percent) : null,
            'percentwidth' => $hasscore ? round($percent, 1) : 0,
            // A flag, not the number: Mustache would treat a percentile of 0 (the lowest score) as false.
            'haspercentile' => $hasscore && $showclass,
            'percentile' => $hasscore && $showclass ? (int) round($percentile) : null,
            'showclass' => $showclass,
            'stats' => $showclass ? self::stats($summary, false) : null,
            'histogram' => $showclass ? self::histogram($percents, $percent) : null,
            'mincohort' => $mincohort,
        ];
    }

    /**
     * Formatted summary (normalised %). Students do not get the minimum, maximum or standard deviation.
     *
     * @param array $summary
     * @param bool $full
     * @return array
     */
    protected static function stats(array $summary, bool $full): array {
        $keys = $full ? ['mean', 'median', 'q1', 'q3', 'min', 'max', 'sd'] : ['mean', 'median', 'q1', 'q3'];
        $items = [];
        foreach ($keys as $key) {
            $items[] = ['label' => get_string('stat' . $key, 'local_gradesheet'),
                'value' => self::number($summary[$key]) . ($key === 'sd' ? '' : '%')];
        }
        return $items;
    }

    /**
     * Box plot positions (percent of the 0-100 axis).
     *
     * @param array $summary
     * @return array
     */
    protected static function box(array $summary): array {
        return array_map(fn($v) => round($v, 2), [
            'min' => $summary['min'],
            'q1' => $summary['q1'],
            'median' => $summary['median'],
            'q3' => $summary['q3'],
            'max' => $summary['max'],
            'iqr' => $summary['q3'] - $summary['q1'],
            'whisker' => $summary['max'] - $summary['min'],
        ]);
    }

    /**
     * Histogram bars: count and height per 10 % bucket; the viewer's bucket is marked.
     *
     * @param float[] $percents
     * @param float|null $own The viewing student's normalised score.
     * @return array
     */
    protected static function histogram(array $percents, ?float $own): array {
        $counts = stats::histogram(array_values($percents), self::BUCKETS);
        $top = max(1, ...$counts);
        $ownbucket = $own === null ? null : stats::bucket($own, self::BUCKETS);
        $bars = [];
        foreach ($counts as $i => $count) {
            $from = $i * (100 / self::BUCKETS);
            $label = $from . '–' . ($from + 100 / self::BUCKETS) . '%';
            $bars[] = [
                'label' => $label,
                'count' => $count,
                'height' => $count ? max(4, round(100 * $count / $top)) : 0,
                'isown' => $i === $ownbucket,
                'title' => get_string('histogrambar', 'local_gradesheet', ['range' => $label, 'count' => $count]),
            ];
        }
        return $bars;
    }

    /**
     * A score for display: up to two decimals, no trailing zeros.
     *
     * @param float $value
     * @return string
     */
    protected static function number(float $value): string {
        return format_float($value, 2, true, true);
    }
}
