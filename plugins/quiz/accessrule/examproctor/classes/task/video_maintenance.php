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

namespace quizaccess_examproctor\task;

use quizaccess_examproctor\local\video;

/**
 * Closes video segments left open (student left the page, lost connection) and deletes videos older than the
 * retention period.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_maintenance extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_videomaintenance', 'quizaccess_examproctor');
    }

    public function execute() {
        global $DB;

        // Attempts with loose chunks older than 10 minutes: merge them so playback is one file per minute.
        $sql = "SELECT DISTINCT f.contextid, f.itemid
                  FROM {files} f
                 WHERE f.component = 'quizaccess_examproctor' AND f.filearea = :filearea
                   AND f.filename LIKE :chunk AND f.timecreated < :before";
        $open = $DB->get_recordset_sql($sql, ['filearea' => video::FILEAREA, 'chunk' => '%-%', 'before' => time() - 600]);
        foreach ($open as $row) {
            $context = \context::instance_by_id($row->contextid, IGNORE_MISSING);
            if ($context instanceof \context_module) {
                video::merge_all($context, (int) $row->itemid, time() - 600);
            }
        }
        $open->close();

        $days = (int) get_config('quizaccess_examproctor', 'videoretentiondays');
        if ($days <= 0) {
            return;
        }
        // Whole attempts whose newest video is older than the retention period.
        $sql = "SELECT f.contextid, f.itemid
                  FROM {files} f
                 WHERE f.component = 'quizaccess_examproctor' AND f.filearea = :filearea AND f.filename <> '.'
              GROUP BY f.contextid, f.itemid
                HAVING MAX(f.timecreated) < :before";
        $old = $DB->get_recordset_sql($sql, ['filearea' => video::FILEAREA, 'before' => time() - $days * DAYSECS]);
        $deleted = 0;
        foreach ($old as $row) {
            get_file_storage()->delete_area_files($row->contextid, 'quizaccess_examproctor', video::FILEAREA, $row->itemid);
            $deleted++;
        }
        $old->close();
        mtrace("quizaccess_examproctor: deleted the videos of {$deleted} attempt(s) older than {$days} days.");
    }
}
