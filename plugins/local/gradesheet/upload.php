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
 * Upload a grade sheet: read the file, preview the matched students and problems, then import the valid rows into
 * a new gradebook item.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_gradesheet\form\upload_form;
use local_gradesheet\local\sheet_manager;
use local_gradesheet\local\sheet_parser;
use local_gradesheet\local\sheet_validator;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/gradesheet:manage', $context);

$indexurl = new moodle_url('/local/gradesheet/index.php', ['id' => $course->id]);
$PAGE->set_url(new moodle_url('/local/gradesheet/upload.php', ['id' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('uploadsheet', 'local_gradesheet') . moodle_page::TITLE_SEPARATOR . format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('gradesheets', 'local_gradesheet'), $indexurl);
$PAGE->navbar->add(get_string('uploadsheet', 'local_gradesheet'));

// Once a file has been chosen, read it (from the user's draft area) to offer its columns and preview the rows.
$parsed = null;
$parseerror = null;
$draftid = data_submitted() ? optional_param('sheetfile', 0, PARAM_INT) : 0;
if ($draftid) {
    $files = get_file_storage()->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid,
        'id DESC', false);
    if ($file = reset($files)) {
        try {
            $parsed = sheet_parser::parse($file->copy_content_to_temp(), $file->get_filename());
        } catch (moodle_exception $e) {
            $parseerror = $e->getMessage();
        }
    }
}

$validator = new sheet_validator($context);
$guess = $parsed ? sheet_validator::guess_columns($parsed['headers']) : [];
$result = null;
$columns = null;
if ($parsed) {
    // Columns as submitted, else the guess, to count the importable rows for the button.
    $columns = [
        'id' => optional_param('idcolumn', $guess['id'] ?? 0, PARAM_INT),
        'score' => optional_param('scorecolumn', $guess['score'] ?? 1, PARAM_INT),
    ];
    $grademax = (float) unformat_float(optional_param('grademax', '100', PARAM_RAW_TRIMMED));
    if ($grademax > 0 && $columns['id'] !== $columns['score']) {
        $result = $validator->validate($parsed['rows'], $columns['id'], $columns['score'], $grademax);
    }
}

$form = new upload_form(null, [
    'courseid' => $course->id,
    'headers' => $parsed['headers'] ?? null,
    'guess' => $guess,
    'importable' => $result ? count($result['valid']) : 0,
]);

if ($form->is_cancelled()) {
    redirect($indexurl);
}

if (($data = $form->get_data()) && $parsed && $result && !empty($data->import) && isset($data->idcolumn)) {
    // Validate again with the submitted values, then import.
    $result = $validator->validate($parsed['rows'], (int) $data->idcolumn, (int) $data->scorecolumn, (float) $data->grademax);
    if ($result['valid']) {
        sheet_manager::import($course->id, trim($data->name), (float) $data->grademax, $result['valid']);
        redirect($indexurl, get_string('imported', 'local_gradesheet', (object) [
            'count' => count($result['valid']),
            'name' => format_string(trim($data->name)),
        ]), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadsheet', 'local_gradesheet'));
echo html_writer::tag('p', get_string('uploadintro', 'local_gradesheet'), ['class' => 'text-muted']);
if ($parseerror) {
    echo $OUTPUT->notification($parseerror, \core\output\notification::NOTIFY_ERROR);
}
if ($result) {
    echo $OUTPUT->render_from_template('local_gradesheet/preview', [
        'rows' => count($parsed['rows']),
        'validcount' => count($result['valid']),
        'errorcount' => count($result['errors']),
        'missingcount' => count($result['missing']),
        'valid' => array_map(fn($row) => [
            'line' => $row['line'],
            'identifier' => $row['identifier'],
            'fullname' => $row['fullname'],
            'score' => format_float($row['score'], 2, true, true),
        ], $result['valid']),
        'errors' => array_map(fn($error) => [
            'line' => $error['line'],
            'identifier' => $error['identifier'],
            'value' => is_scalar($error['value']) ? (string) $error['value'] : '',
            'reason' => get_string($error['reason'], 'local_gradesheet'),
        ], $result['errors']),
        'haserrors' => !empty($result['errors']),
        'missing' => implode(', ', $result['missing']),
        'hasmissing' => !empty($result['missing']),
    ]);
}
$form->display();
echo $OUTPUT->footer();
