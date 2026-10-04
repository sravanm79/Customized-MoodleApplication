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

use grade_item;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

/**
 * Grade sheets of a course: each is a manual gradebook item, so the scores live in the gradebook (reports,
 * backups, privacy) and corrections made there show up here.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sheet_manager {
    /** @var string Source recorded on grade item and grade changes. */
    const SOURCE = 'local_gradesheet';

    /**
     * Creates a sheet: a manual grade item with the valid rows as grades.
     *
     * @param int $courseid
     * @param string $name
     * @param float $grademax
     * @param array $valid Rows from sheet_validator::validate().
     * @return stdClass The sheet record.
     */
    public static function import(int $courseid, string $name, float $grademax, array $valid): stdClass {
        global $DB, $USER;
        $transaction = $DB->start_delegated_transaction();

        $item = new grade_item([
            'courseid' => $courseid,
            'itemtype' => 'manual',
            'itemname' => $name,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => $grademax,
            'grademin' => 0,
        ], false);
        $item->insert(self::SOURCE);

        $sheet = (object) ['courseid' => $courseid, 'gradeitemid' => $item->id, 'name' => $name, 'timecreated' => time()];
        $sheet->id = $DB->insert_record('local_gradesheet', $sheet);

        foreach ($valid as $row) {
            $item->update_final_grade($row['userid'], $row['score'], self::SOURCE, false, FORMAT_MOODLE, $USER->id);
        }
        $transaction->allow_commit();
        return $sheet;
    }

    /**
     * The course's sheets, newest first, with their grade items (sheets whose item was deleted are dropped).
     *
     * @param int $courseid
     * @return array of ['sheet' => stdClass, 'item' => grade_item]
     */
    public static function get_sheets(int $courseid): array {
        global $DB;
        $sheets = [];
        foreach ($DB->get_records('local_gradesheet', ['courseid' => $courseid], 'timecreated DESC, id DESC') as $sheet) {
            $item = grade_item::fetch(['id' => $sheet->gradeitemid, 'courseid' => $courseid]);
            if ($item) {
                $sheets[] = ['sheet' => $sheet, 'item' => $item];
            } else {
                // Deleted in the gradebook.
                $DB->delete_records('local_gradesheet', ['id' => $sheet->id]);
            }
        }
        return $sheets;
    }

    /**
     * One sheet of a course.
     *
     * @param int $courseid
     * @param int $sheetid
     * @return array|null ['sheet' => stdClass, 'item' => grade_item]
     */
    public static function get_sheet(int $courseid, int $sheetid): ?array {
        foreach (self::get_sheets($courseid) as $entry) {
            if ((int) $entry['sheet']->id === $sheetid) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Current scores of the course's students (as in the gradebook, including later edits).
     *
     * @param grade_item $item
     * @param int[] $userids Students to include.
     * @return array userid => ['score' => float, 'hidden' => bool] (hidden: this grade is hidden from the student)
     */
    public static function get_scores(grade_item $item, array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['itemid'] = $item->id;
        $records = $DB->get_records_select('grade_grades', "itemid = :itemid AND finalgrade IS NOT NULL AND userid $insql",
            $params, '', 'userid, finalgrade, hidden');
        $now = time();
        $scores = [];
        foreach ($records as $record) {
            $scores[(int) $record->userid] = [
                'score' => (float) $record->finalgrade,
                'hidden' => $record->hidden == 1 || $record->hidden > $now,
            ];
        }
        return $scores;
    }

    /**
     * Deletes a sheet and its grade item (with the grades).
     *
     * @param array $entry From get_sheet().
     */
    public static function delete(array $entry): void {
        global $DB;
        $entry['item']->delete(self::SOURCE);
        $DB->delete_records('local_gradesheet', ['id' => $entry['sheet']->id]);
    }
}
