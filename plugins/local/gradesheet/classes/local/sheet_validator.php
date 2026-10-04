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

use context_course;

/**
 * Matches grade sheet rows to the course's students and checks the scores.
 *
 * Students are active participants who can view their grades (moodle/grade:view), as in the gradebook. A row's
 * student ID is matched, case-insensitively, against the ID number, then the username, then the email address.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sheet_validator {
    /** @var string[] Header patterns for the student ID column, best first. */
    const ID_HEADERS = [
        '/^(student\s*)?id\s*(number|no\.?)?$/i',
        '/^(roll|reg(istration)?|enrol(l)?ment|admission)\s*(no\.?|number|id)?$/i',
        '/^user\s*name$|^username$|^login$/i',
        '/^e-?mail(\s*address)?$/i',
        '/\b(id|roll|username|email)\b/i',
    ];

    /** @var string[] Header patterns for the score column, best first. */
    const SCORE_HEADERS = ['/^(score|marks?|grade|total|points?)$/i', '/(score|mark|grade|total|point|result)/i'];

    /** @var array Participants by lower-case identifier: ['idnumber' => [key => userids], 'username' => ..., 'email' => ...] */
    protected $lookup = ['idnumber' => [], 'username' => [], 'email' => []];

    /** @var \stdClass[] Participants by user id. */
    protected $participants = [];

    /**
     * @param context_course $context
     */
    public function __construct(context_course $context) {
        $fields = 'u.id, u.idnumber, u.username, u.email, ' . implode(', ', array_map(fn($f) => 'u.' . $f,
            \core_user\fields::get_name_fields()));
        $this->participants = get_enrolled_users($context, 'moodle/grade:view', 0, $fields, 'u.lastname, u.firstname',
            0, 0, true);
        foreach ($this->participants as $user) {
            foreach (array_keys($this->lookup) as $field) {
                $key = \core_text::strtolower(trim((string) $user->$field));
                if ($key !== '') {
                    $this->lookup[$field][$key][] = (int) $user->id;
                }
            }
        }
    }

    /**
     * Students of the course.
     *
     * @return \stdClass[] By user id.
     */
    public function get_participants(): array {
        return $this->participants;
    }

    /**
     * The likely student ID and score columns of a header row.
     *
     * @param string[] $headers
     * @return array ['id' => int|null, 'score' => int|null] Column indexes.
     */
    public static function guess_columns(array $headers): array {
        $find = function(array $patterns, ?int $skip) use ($headers): ?int {
            foreach ($patterns as $pattern) {
                foreach ($headers as $index => $header) {
                    if ($index !== $skip && preg_match($pattern, trim($header))) {
                        return $index;
                    }
                }
            }
            return null;
        };
        $id = $find(self::ID_HEADERS, null);
        return ['id' => $id, 'score' => $find(self::SCORE_HEADERS, $id)];
    }

    /**
     * Checks every row.
     *
     * @param array $rows From sheet_parser::parse().
     * @param int $idcolumn
     * @param int $scorecolumn
     * @param float $grademax
     * @return array [
     *     'valid' => [['line', 'userid', 'identifier', 'fullname', 'score']],
     *     'errors' => [['line', 'identifier', 'value', 'reason' (lang string key)]],
     *     'missing' => [fullname, ...] students with no row,
     * ]
     */
    public function validate(array $rows, int $idcolumn, int $scorecolumn, float $grademax): array {
        $valid = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $row) {
            $identifier = $row['cells'][$idcolumn] ?? '';
            $value = $row['cells'][$scorecolumn] ?? '';
            $error = function(string $reason) use (&$errors, $row, $identifier, $value) {
                $errors[] = ['line' => $row['line'], 'identifier' => $identifier, 'value' => $value, 'reason' => $reason];
            };
            if ($identifier === '') {
                $error('reasonnoid');
                continue;
            }
            $userids = $this->match($identifier);
            if (count($userids) !== 1) {
                $error($userids ? 'reasonambiguous' : 'reasonnotparticipant');
                continue;
            }
            $userid = reset($userids);
            if (isset($seen[$userid])) {
                $error('reasonduplicate');
                continue;
            }
            $score = self::parse_score($value, $grademax);
            if ($score === null) {
                $error($value === '' ? 'reasonnoscore' : 'reasoninvalidscore');
                continue;
            }
            if ($score < 0 || $score > $grademax) {
                $error('reasonoutofrange');
                continue;
            }
            $seen[$userid] = true;
            $valid[] = [
                'line' => $row['line'],
                'userid' => $userid,
                'identifier' => $identifier,
                'fullname' => fullname($this->participants[$userid]),
                'score' => $score,
            ];
        }
        $missing = [];
        foreach ($this->participants as $userid => $user) {
            if (!isset($seen[$userid])) {
                $missing[] = fullname($user);
            }
        }
        return ['valid' => $valid, 'errors' => $errors, 'missing' => $missing];
    }

    /**
     * Participants whose ID number, else username, else email equals the identifier.
     *
     * @param string $identifier
     * @return int[]
     */
    protected function match(string $identifier): array {
        $key = \core_text::strtolower(trim($identifier));
        foreach (array_keys($this->lookup) as $field) {
            if (!empty($this->lookup[$field][$key])) {
                return array_values(array_unique($this->lookup[$field][$key]));
            }
        }
        return [];
    }

    /**
     * A score cell as a number: "85", "85.5", "85,5" (decimal comma); "85%" is a percentage of the maximum.
     *
     * @param mixed $value
     * @param float $grademax
     * @return float|null Null if not a number.
     */
    public static function parse_score($value, float $grademax): ?float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $text = str_replace([' ', "\u{00A0}"], '', trim((string) $value));
        $percent = str_ends_with($text, '%');
        $text = rtrim($text, '%');
        if (strpos($text, ',') !== false && strpos($text, '.') === false && substr_count($text, ',') === 1) {
            $text = str_replace(',', '.', $text);
        }
        if ($text === '' || !is_numeric($text)) {
            return null;
        }
        $score = (float) $text;
        return $percent ? round($score / 100 * $grademax, 5) : $score;
    }
}
