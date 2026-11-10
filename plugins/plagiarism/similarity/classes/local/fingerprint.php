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

namespace plagiarism_similarity\local;

/**
 * Document fingerprints by winnowing (Schleimer, Wilkerson, Aiken 2003, the method behind MOSS).
 *
 * Text is reduced to word tokens (case, spacing and punctuation ignored), every run of K consecutive tokens is
 * hashed, and in each window of W consecutive hashes the smallest is kept. Two documents sharing a passage of at least
 * K + W - 1 tokens are guaranteed to share a fingerprint; short coincidences (fewer than K tokens) never match.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fingerprint {

    /** Tokens per k-gram. */
    const K = 5;

    /** Winnowing window. */
    const W = 4;

    /** Fewer distinct fingerprints than this: too short to judge. */
    const MIN_FINGERPRINTS = 8;

    /**
     * Word tokens with their byte offsets in the text.
     *
     * @param string $text
     * @return array[] [token, offset, length]
     */
    public static function tokens(string $text): array {
        preg_match_all('/[\p{L}\p{N}_]+/u', $text, $m, PREG_OFFSET_CAPTURE);
        return array_map(fn($t) => [\core_text::strtolower($t[0]), $t[1], strlen($t[0])], $m[0]);
    }

    /**
     * Hash of every k-gram, in order.
     *
     * @param array[] $tokens From tokens().
     * @return int[]
     */
    public static function kgram_hashes(array $tokens): array {
        $words = array_column($tokens, 0);
        $hashes = [];
        for ($i = 0, $n = count($words) - self::K + 1; $i < $n; $i++) {
            $hashes[] = crc32(implode(' ', array_slice($words, $i, self::K)));
        }
        return $hashes;
    }

    /**
     * Winnowed fingerprint set of a text.
     *
     * @param string $text
     * @return int[] Distinct hashes (as array keys => true for fast lookup).
     */
    public static function of(string $text): array {
        $hashes = self::kgram_hashes(self::tokens($text));
        $n = count($hashes);
        $selected = [];
        if ($n === 0) {
            return [];
        }
        if ($n <= self::W) {
            $selected[min($hashes)] = true;
            return $selected;
        }
        for ($i = 0; $i + self::W <= $n; $i++) {
            // Rightmost minimum of the window, as in the paper.
            $min = $hashes[$i];
            for ($j = 1; $j < self::W; $j++) {
                if ($hashes[$i + $j] <= $min) {
                    $min = $hashes[$i + $j];
                }
            }
            $selected[$min] = true;
        }
        return $selected;
    }

    /**
     * Share of fingerprint A also found in B, in percent.
     *
     * @param array $a Fingerprint (hash => true).
     * @param array $b Fingerprint (hash => true).
     * @return float 0..100
     */
    public static function containment(array $a, array $b): float {
        return $a ? 100 * count(array_intersect_key($a, $b)) / count($a) : 0.0;
    }

    /**
     * Serialises a fingerprint for storage.
     *
     * @param array $fp
     * @return string
     */
    public static function encode(array $fp): string {
        return implode(',', array_keys($fp));
    }

    /**
     * Reads a stored fingerprint.
     *
     * @param string|null $stored
     * @return array hash => true
     */
    public static function decode(?string $stored): array {
        if ($stored === null || $stored === '') {
            return [];
        }
        return array_fill_keys(array_map('intval', explode(',', $stored)), true);
    }

    /**
     * Byte ranges of $text covered by k-grams that also occur in $other (for highlighting), merged.
     *
     * @param string $text
     * @param string $other
     * @param array $ignore Fingerprint hashes not to highlight (starter code, boilerplate).
     * @return array[] [start, end] byte offsets, ascending.
     */
    public static function shared_ranges(string $text, string $other, array $ignore = []): array {
        $tokens = self::tokens($text);
        $hashes = self::kgram_hashes($tokens);
        $otherhashes = array_fill_keys(self::kgram_hashes(self::tokens($other)), true);
        $ranges = [];
        foreach ($hashes as $i => $h) {
            if (!isset($otherhashes[$h]) || isset($ignore[$h])) {
                continue;
            }
            $start = $tokens[$i][1];
            $last = $tokens[$i + self::K - 1];
            $end = $last[1] + $last[2];
            if ($ranges && $start <= $ranges[count($ranges) - 1][1] + 1) {
                $ranges[count($ranges) - 1][1] = max($ranges[count($ranges) - 1][1], $end);
            } else {
                $ranges[] = [$start, $end];
            }
        }
        return $ranges;
    }
}
