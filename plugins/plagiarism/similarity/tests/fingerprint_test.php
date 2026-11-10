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

namespace plagiarism_similarity;

use plagiarism_similarity\local\fingerprint;

/**
 * Tests for the winnowing fingerprints.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \plagiarism_similarity\local\fingerprint
 */
final class fingerprint_test extends \basic_testcase {

    /** A paragraph long enough to fingerprint. */
    const ESSAY = 'Photosynthesis converts light energy into chemical energy that is stored in glucose. Plants, algae and
        some bacteria use chlorophyll to absorb light, mostly in the blue and red wavelengths, and release oxygen as a
        by-product of splitting water molecules during the light dependent reactions.';

    public function test_identical_texts_match_fully(): void {
        $fp = fingerprint::of(self::ESSAY);
        $this->assertGreaterThanOrEqual(fingerprint::MIN_FINGERPRINTS, count($fp));
        $this->assertEquals(100.0, fingerprint::containment($fp, fingerprint::of(self::ESSAY)));
    }

    public function test_case_spacing_and_punctuation_are_ignored(): void {
        $changed = strtoupper(str_replace([',', '.', ' '], [' ;', ' !', "  \n "], self::ESSAY));
        $this->assertEquals(100.0, fingerprint::containment(fingerprint::of(self::ESSAY), fingerprint::of($changed)));
    }

    public function test_unrelated_texts_do_not_match(): void {
        $other = 'The French Revolution began in 1789 and transformed the political landscape of Europe, ending absolute
            monarchy, spreading ideas of citizenship and rights, and leading to decades of war across the continent.';
        $this->assertEquals(0.0, fingerprint::containment(fingerprint::of(self::ESSAY), fingerprint::of($other)));
    }

    public function test_partial_copy_scores_in_between(): void {
        $half = substr(self::ESSAY, 0, (int) (strlen(self::ESSAY) / 2)) . ' My own conclusion talks about something else
            entirely, such as the history of agriculture in river valleys and early irrigation systems.';
        $score = fingerprint::containment(fingerprint::of($half), fingerprint::of(self::ESSAY));
        $this->assertGreaterThan(20, $score);
        $this->assertLessThan(80, $score);
    }

    public function test_encode_decode_round_trip(): void {
        $fp = fingerprint::of(self::ESSAY);
        $this->assertEquals($fp, fingerprint::decode(fingerprint::encode($fp)));
        $this->assertSame([], fingerprint::decode(''));
    }

    public function test_shared_ranges_cover_the_copied_passage(): void {
        $copy = 'Intro of my own. ' . self::ESSAY;
        $ranges = fingerprint::shared_ranges($copy, self::ESSAY);
        $this->assertCount(1, $ranges);
        $this->assertSame(strlen('Intro of my own. '), $ranges[0][0]);
    }
}
