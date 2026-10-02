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
 * List submissions and grade them.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/jupyter/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'jupyter');
$jupyter = $DB->get_record('jupyter', ['id' => $cm->instance], '*', MUST_EXIST);
$jupyter->cmidnumber = $cm->idnumber;

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jupyter:grade', $context);

$url = new moodle_url('/mod/jupyter/submissions.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($jupyter->name) . ': ' . get_string('submissions', 'mod_jupyter'));
$PAGE->set_heading(format_string($course->fullname));

$submissions = $DB->get_records('jupyter_submissions', ['jupyter' => $jupyter->id], '', '*');
$byuser = [];
foreach ($submissions as $sub) {
    $byuser[$sub->userid] = $sub;
}

$gradesmenu = $jupyter->grade != 0 ? make_grades_menu($jupyter->grade) : [];

if (optional_param('savegrades', false, PARAM_BOOL) && confirm_sesskey()) {
    $grades = optional_param_array('grade', [], PARAM_INT);
    $feedbacks = optional_param_array('feedback', [], PARAM_TEXT);
    $now = time();
    foreach ($byuser as $userid => $sub) {
        if (!array_key_exists($userid, $grades)) {
            continue;
        }
        $grade = $grades[$userid] < 0 ? null : $grades[$userid];
        $feedback = trim($feedbacks[$userid] ?? '');
        if ($grade === ($sub->grade === null ? null : (int) $sub->grade) && $feedback === (string) $sub->feedback) {
            continue;
        }
        $sub->grade = $grade;
        $sub->feedback = $feedback;
        $sub->grader = $USER->id;
        $sub->timegraded = $now;
        $DB->update_record('jupyter_submissions', $sub);
        jupyter_update_grades($jupyter, $userid);
    }
    redirect($url, get_string('gradessaved', 'mod_jupyter'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submissions', 'mod_jupyter'));

$students = get_enrolled_users($context, 'mod/jupyter:submit', 0, 'u.*', 'u.lastname, u.firstname');
// Also list anyone who submitted but has since lost the capability.
foreach (array_diff_key($byuser, $students) as $userid => $sub) {
    $students[$userid] = core_user::get_user($userid);
}

if (!$students) {
    echo $OUTPUT->notification(get_string('nosubmissions', 'mod_jupyter'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$fs = get_file_storage();
$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('status', 'mod_jupyter'),
    get_string('notebook', 'mod_jupyter'),
    get_string('grade', 'mod_jupyter'),
    get_string('feedback', 'mod_jupyter'),
];
$table->attributes['class'] = 'generaltable mod-jupyter-submissions';

foreach ($students as $user) {
    $sub = $byuser[$user->id] ?? null;
    $name = html_writer::link(new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]),
        fullname($user));

    if (!$sub) {
        $table->data[] = [$name, get_string('notsubmittedshort', 'mod_jupyter'), '', '', ''];
        continue;
    }

    $status = get_string('submittedon', 'mod_jupyter', userdate($sub->timemodified, get_string('strftimedatetimeshort')));
    $links = [];
    $files = $fs->get_area_files($context->id, 'mod_jupyter', 'submission', $sub->id, 'filename', false);
    if ($file = reset($files)) {
        $links[] = html_writer::link(moodle_url::make_pluginfile_url($context->id, 'mod_jupyter', 'submission',
            $sub->id, '/', $file->get_filename(), true), get_string('download'));
        $links[] = html_writer::link(new moodle_url('/mod/jupyter/review.php', ['id' => $cm->id, 'userid' => $user->id]),
            get_string('openinjupyter', 'mod_jupyter'));
    }

    if ($gradesmenu) {
        $gradecell = html_writer::select($gradesmenu, 'grade[' . $user->id . ']',
            $sub->grade === null ? -1 : (int) $sub->grade, [-1 => get_string('nograde', 'mod_jupyter')]);
    } else {
        $gradecell = get_string('nograding', 'mod_jupyter');
    }
    $feedbackcell = html_writer::tag('textarea', s($sub->feedback ?? ''),
        ['name' => 'feedback[' . $user->id . ']', 'rows' => 2, 'cols' => 30, 'class' => 'form-control']);

    $table->data[] = [$name, $status, implode(' | ', $links), $gradecell, $feedbackcell];
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrades', 'value' => 1]);
echo html_writer::table($table);
if ($byuser) {
    echo html_writer::tag('button', get_string('savegrades', 'mod_jupyter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
}
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
