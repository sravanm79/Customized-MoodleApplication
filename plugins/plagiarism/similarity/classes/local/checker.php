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
 * Compares the latest submissions of an assignment with each other.
 *
 * Each submission's score is the share of its fingerprints found in the single most similar classmate's submission.
 * Fingerprints of the teacher's "ignore" text and the assignment description are not counted, nor are those shared by
 * more than half of the class (starter code, the question itself) once there are BOILERPLATE_MIN submissions.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checker {

    /** Submissions needed before fingerprints shared by most of the class are treated as boilerplate. */
    const BOILERPLATE_MIN = 4;

    /** A fingerprint in more than this share of submissions is boilerplate. */
    const BOILERPLATE_SHARE = 0.5;

    /**
     * Settings of an assignment's check, or null when the check is off for it.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public static function config(int $cmid): ?\stdClass {
        global $DB;
        if (!get_config('plagiarism_similarity', 'enabled') || empty(get_config('core', 'enableplagiarism'))) {
            return null;
        }
        $cfg = $DB->get_record('plagiarism_similarity_cfg', ['cmid' => $cmid]);
        return $cfg && $cfg->enabled ? $cfg : null;
    }

    /**
     * Saves an assignment's settings.
     *
     * @param int $cmid
     * @param bool $enabled
     * @param int $threshold
     * @param string $ignoretext
     */
    public static function save_config(int $cmid, bool $enabled, int $threshold, string $ignoretext): void {
        global $DB;
        $cfg = $DB->get_record('plagiarism_similarity_cfg', ['cmid' => $cmid]) ?: (object) ['cmid' => $cmid];
        $cfg->enabled = (int) $enabled;
        $cfg->threshold = max(1, min(100, $threshold));
        $cfg->ignoretext = $ignoretext;
        $cfg->timemodified = time();
        if (!empty($cfg->id)) {
            $DB->update_record('plagiarism_similarity_cfg', $cfg);
        } else {
            $DB->insert_record('plagiarism_similarity_cfg', $cfg);
        }
    }

    /**
     * Queues a check of the assignment (runs about a minute later, once for many submissions in a row).
     *
     * @param int $cmid
     */
    public static function queue(int $cmid): void {
        $task = new \plagiarism_similarity\task\check_assignment();
        $task->set_custom_data(['cmid' => $cmid]);
        $task->set_next_run_time(time() + MINSECS);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Fingerprints every latest submission (unchanged ones are reused) and stores each one's similarity.
     *
     * @param int $cmid
     * @return int Submissions checked.
     */
    public static function run(int $cmid): int {
        global $DB;
        $cfg = self::config($cmid);
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cfg || !$cm) {
            return 0;
        }
        $context = \context_module::instance($cm->id);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, intro', MUST_EXIST);

        // Latest individual submissions with something in them (team submissions have userid 0 and are not compared).
        $submissions = $DB->get_records_select('assign_submission',
            "assignment = :assignment AND latest = 1 AND userid > 0 AND status IN ('submitted', 'draft')",
            ['assignment' => $assign->id], '', 'id, userid, status');
        $existing = $DB->get_records('plagiarism_similarity_sub', ['cmid' => $cmid], '', '*');
        $byuser = [];
        foreach ($existing as $row) {
            $byuser[$row->userid] = $row;
        }

        // 1. Fingerprints.
        $fps = [];
        $rows = [];
        foreach ($submissions as $submission) {
            $content = extractor::extract($context, $submission);
            $hash = sha1($content['text']);
            $row = $byuser[$submission->userid] ?? (object) ['cmid' => $cmid, 'userid' => $submission->userid];
            unset($byuser[$submission->userid]);
            $row->submissionid = $submission->id;
            $row->skipped = implode(', ', $content['skipped']);
            if (($row->contenthash ?? '') !== $hash || !isset($row->fingerprint)) {
                $fp = fingerprint::of($content['text']);
                $row->contenthash = $hash;
                $row->fingerprint = fingerprint::encode($fp);
                $row->tokens = count(fingerprint::tokens($content['text']));
            } else {
                $fp = fingerprint::decode($row->fingerprint);
            }
            $rows[$submission->userid] = $row;
            $fps[$submission->userid] = $fp;
        }

        // 2. Fingerprints that do not count.
        $ignore = self::ignored($cfg, $assign, $fps);
        foreach ($fps as $userid => $fp) {
            $fps[$userid] = array_diff_key($fp, $ignore);
        }

        // 3. Shared fingerprints per pair, through an inverted index (only pairs that share something are visited).
        $index = [];
        foreach ($fps as $userid => $fp) {
            foreach ($fp as $h => $unused) {
                $index[$h][] = $userid;
            }
        }
        $shared = [];
        foreach ($index as $users) {
            $n = count($users);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $shared[$users[$i]][$users[$j]] = ($shared[$users[$i]][$users[$j]] ?? 0) + 1;
                    $shared[$users[$j]][$users[$i]] = ($shared[$users[$j]][$users[$i]] ?? 0) + 1;
                }
            }
        }

        // 4. Each submission's highest match.
        $now = time();
        foreach ($rows as $userid => $row) {
            $total = count($fps[$userid]);
            $row->score = null;
            $row->matchuserid = null;
            if ((int) $row->tokens === 0) {
                $row->status = 'nocontent';
            } else if ($total < fingerprint::MIN_FINGERPRINTS) {
                $row->status = 'tooshort';
            } else {
                $row->status = 'done';
                $row->score = 0;
                foreach ($shared[$userid] ?? [] as $other => $count) {
                    $score = round(100 * $count / $total, 1);
                    if ($score > $row->score) {
                        $row->score = $score;
                        $row->matchuserid = $other;
                    }
                }
            }
            $row->timemodified = $now;
            if (!empty($row->id)) {
                $DB->update_record('plagiarism_similarity_sub', $row);
            } else {
                $DB->insert_record('plagiarism_similarity_sub', $row);
            }
        }
        // Students whose submission is gone (removed, or reverted to "new").
        foreach ($byuser as $gone) {
            $DB->delete_records('plagiarism_similarity_sub', ['id' => $gone->id]);
        }
        $DB->set_field('plagiarism_similarity_cfg', 'timechecked', $now, ['id' => $cfg->id]);
        return count($rows);
    }

    /**
     * Fingerprints that are not counted: the ignore text, the assignment description, and class-wide boilerplate.
     *
     * @param \stdClass $cfg
     * @param \stdClass $assign
     * @param array $fps userid => fingerprint
     * @return array hash => true
     */
    public static function ignored(\stdClass $cfg, \stdClass $assign, array $fps): array {
        $ignore = fingerprint::of((string) $cfg->ignoretext) + fingerprint::of(html_to_text((string) $assign->intro, 0, false));
        if (count($fps) >= self::BOILERPLATE_MIN) {
            $counts = [];
            foreach ($fps as $fp) {
                foreach ($fp as $h => $unused) {
                    $counts[$h] = ($counts[$h] ?? 0) + 1;
                }
            }
            $limit = count($fps) * self::BOILERPLATE_SHARE;
            foreach ($counts as $h => $count) {
                if ($count > $limit) {
                    $ignore[$h] = true;
                }
            }
        }
        return $ignore;
    }
}
