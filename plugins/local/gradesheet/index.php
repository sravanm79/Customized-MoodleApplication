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

/**
 * A course's grade sheets: teachers see every sheet's statistics and scores (and can upload or delete sheets);
 * students see their own score, normalised grade, percentile and the anonymised class distribution.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_gradesheet\local\sheet_manager;
use local_gradesheet\output\sheet_view;

$courseid = required_param('id', PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
$canmanage = has_capability('local/gradesheet:manage', $context);
if (!$canmanage) {
    require_capability('local/gradesheet:viewstats', $context);
}

$url = new moodle_url('/local/gradesheet/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('gradesheets', 'local_gradesheet') . moodle_page::TITLE_SEPARATOR . format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('gradesheets', 'local_gradesheet'), $url);

// Delete a sheet (teachers), after confirmation.
if ($deleteid && $canmanage) {
    $entry = sheet_manager::get_sheet($course->id, $deleteid);
    if (!$entry) {
        redirect($url);
    }
    if ($confirm && confirm_sesskey()) {
        sheet_manager::delete($entry);
        redirect($url, get_string('deleted', 'local_gradesheet', format_string($entry['sheet']->name)), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('confirmdelete', 'local_gradesheet', format_string($entry['sheet']->name)),
        new moodle_url($url, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]), $url);
    echo $OUTPUT->footer();
    exit;
}

$fields = 'u.id, ' . implode(', ', array_map(fn($f) => 'u.' . $f, \core_user\fields::get_name_fields()));
$students = get_enrolled_users($context, 'moodle/grade:view', 0, $fields, null, 0, 0, true);
$entries = sheet_manager::get_sheets($course->id);

echo $OUTPUT->header();
if ($canmanage) {
    $sheets = array_map(fn($entry) => (new sheet_view($entry, $students, null))->export_for_template($OUTPUT), $entries);
    echo $OUTPUT->render_from_template('local_gradesheet/teacher_page', [
        'uploadurl' => (new moodle_url('/local/gradesheet/upload.php', ['id' => $course->id]))->out(false),
        'sheets' => $sheets,
        'hassheets' => !empty($sheets),
        'studentcount' => count($students),
    ]);
} else {
    // Sheets hidden in the gradebook are not shown to students.
    $visible = array_filter($entries, fn($entry) => !$entry['item']->is_hidden());
    $sheets = array_map(fn($entry) => (new sheet_view($entry, $students, (int) $USER->id))->export_for_template($OUTPUT),
        array_values($visible));
    echo $OUTPUT->render_from_template('local_gradesheet/student_page', [
        'sheets' => $sheets,
        'hassheets' => !empty($sheets),
    ]);
}
echo $OUTPUT->footer();
