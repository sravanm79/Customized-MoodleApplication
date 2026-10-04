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

namespace local_gradesheet\local;

/**
 * Descriptive statistics of a set of (normalised, 0-100) scores.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats {
    /**
     * Count, mean, median, quartiles, min, max and standard deviation.
     *
     * @param float[] $values
     * @return array|null Null for no values.
     */
    public static function summary(array $values): ?array {
        $n = count($values);
        if (!$n) {
            return null;
        }
        sort($values);
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / $n;
        return [
            'n' => $n,
            'mean' => $mean,
            'median' => self::quantile($values, .5),
            'q1' => self::quantile($values, .25),
            'q3' => self::quantile($values, .75),
            'min' => $values[0],
            'max' => $values[$n - 1],
            'sd' => sqrt($variance),
        ];
    }

    /**
     * Quantile of sorted values, by linear interpolation between closest ranks (as spreadsheets' QUARTILE.INC).
     *
     * @param float[] $sorted
     * @param float $p 0..1
     * @return float
     */
    public static function quantile(array $sorted, float $p): float {
        $h = (count($sorted) - 1) * $p;
        $lo = (int) floor($h);
        $hi = min($lo + 1, count($sorted) - 1);
        return $sorted[$lo] + ($h - $lo) * ($sorted[$hi] - $sorted[$lo]);
    }

    /**
     * Percentile rank of a value: the share of scores below it, counting ties as half.
     *
     * @param float $value
     * @param float[] $values
     * @return float 0..100
     */
    public static function percentile_rank(float $value, array $values): float {
        $below = count(array_filter($values, fn($v) => $v < $value - 1e-9));
        $equal = count(array_filter($values, fn($v) => abs($v - $value) <= 1e-9));
        return 100 * ($below + $equal / 2) / count($values);
    }

    /**
     * Share of the other students who scored strictly lower: what "you scored higher than X% of the class" means.
     *
     * The lowest score gives 0 and the highest 100; tied students are not counted as lower. $values includes the
     * student's own score once.
     *
     * @param float $value
     * @param float[] $values
     * @return float 0..100
     */
    public static function share_scored_lower(float $value, array $values): float {
        $others = count($values) - 1;
        if ($others < 1) {
            return 0.0;
        }
        $below = count(array_filter($values, fn($v) => $v < $value - 1e-9));
        return 100 * $below / $others;
    }

    /**
     * Counts per bucket of width 100 / $buckets (the last bucket includes 100).
     *
     * @param float[] $values 0..100
     * @param int $buckets
     * @return int[]
     */
    public static function histogram(array $values, int $buckets = 10): array {
        $counts = array_fill(0, $buckets, 0);
        foreach ($values as $value) {
            $counts[self::bucket($value, $buckets)]++;
        }
        return $counts;
    }

    /**
     * The bucket of a value.
     *
     * @param float $value 0..100
     * @param int $buckets
     * @return int
     */
    public static function bucket(float $value, int $buckets = 10): int {
        return max(0, min($buckets - 1, (int) floor($value / (100 / $buckets))));
    }
}
