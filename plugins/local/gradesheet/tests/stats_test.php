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

namespace local_gradesheet;

use local_gradesheet\local\sheet_validator;
use local_gradesheet\local\stats;

/**
 * Tests for the statistics, score parsing and column guessing.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_gradesheet\local\stats
 * @covers    \local_gradesheet\local\sheet_validator
 */
final class stats_test extends \advanced_testcase {
    public function test_summary(): void {
        $s = stats::summary([40, 60, 70, 80, 100]);
        $this->assertSame(5, $s['n']);
        $this->assertEqualsWithDelta(70, $s['mean'], 1e-9);
        $this->assertEqualsWithDelta(70, $s['median'], 1e-9);
        $this->assertEqualsWithDelta(60, $s['q1'], 1e-9);
        $this->assertEqualsWithDelta(80, $s['q3'], 1e-9);
        $this->assertEquals(40, $s['min']);
        $this->assertEquals(100, $s['max']);
        $this->assertNull(stats::summary([]));
    }

    public function test_quantile_interpolates(): void {
        // Spreadsheet QUARTILE.INC of 1..4: Q1 = 1.75, median = 2.5, Q3 = 3.25.
        $this->assertEqualsWithDelta(1.75, stats::quantile([1, 2, 3, 4], .25), 1e-9);
        $this->assertEqualsWithDelta(2.5, stats::quantile([1, 2, 3, 4], .5), 1e-9);
        $this->assertEqualsWithDelta(3.25, stats::quantile([1, 2, 3, 4], .75), 1e-9);
    }

    public function test_share_scored_lower(): void {
        // Lowest, middle and highest of three: higher than 0, 1 and 2 of the 2 classmates.
        $this->assertEqualsWithDelta(0, stats::share_scored_lower(65, [80, 65, 92]), 1e-9);
        $this->assertEqualsWithDelta(50, stats::share_scored_lower(80, [80, 65, 92]), 1e-9);
        $this->assertEqualsWithDelta(100, stats::share_scored_lower(92, [80, 65, 92]), 1e-9);
        // A tie is not "higher than".
        $this->assertEqualsWithDelta(0, stats::share_scored_lower(70, [70, 70]), 1e-9);
        $this->assertEqualsWithDelta(0, stats::share_scored_lower(70, [70]), 1e-9);
    }

    public function test_percentile_rank_counts_ties_as_half(): void {
        $this->assertEqualsWithDelta(50, stats::percentile_rank(70, [50, 70, 90]), 1e-9);
        // One below, two tied (counted as one): (1 + 2 / 2) / 4.
        $this->assertEqualsWithDelta(50, stats::percentile_rank(70, [50, 70, 70, 90]), 1e-9);
        $this->assertEqualsWithDelta(100 * 2.5 / 3, stats::percentile_rank(90, [50, 70, 90]), 1e-9);
    }

    public function test_histogram_buckets(): void {
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 1, 2], stats::histogram([0, 85, 90, 100], 10));
        $this->assertSame(9, stats::bucket(100));
        $this->assertSame(0, stats::bucket(-1));
    }

    public function test_parse_score(): void {
        $this->assertSame(85.0, sheet_validator::parse_score('85', 100));
        $this->assertSame(85.5, sheet_validator::parse_score('85,5', 100));
        $this->assertSame(42.0, sheet_validator::parse_score(42, 50));
        $this->assertSame(40.0, sheet_validator::parse_score('80%', 50));
        $this->assertNull(sheet_validator::parse_score('absent', 100));
        $this->assertNull(sheet_validator::parse_score('', 100));
    }

    public function test_guess_columns(): void {
        $this->assertSame(['id' => 1, 'score' => 3], sheet_validator::guess_columns(['Name', 'Roll No', 'Section', 'Marks']));
        $this->assertSame(['id' => 0, 'score' => 2], sheet_validator::guess_columns(['Email', 'Name', 'Total Score']));
        $this->assertSame(['id' => null, 'score' => null], sheet_validator::guess_columns(['A', 'B']));
    }
}
