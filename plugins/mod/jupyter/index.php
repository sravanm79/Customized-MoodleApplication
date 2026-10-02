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
 * List all Jupyter activities in a course.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/jupyter/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_jupyter'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_jupyter'));

$table = new html_table();
$table->head = [get_string('name'), get_string('moduleintro')];
foreach (get_all_instances_in_course('jupyter', $course) as $jupyter) {
    $link = html_writer::link(new moodle_url('/mod/jupyter/view.php', ['id' => $jupyter->coursemodule]),
        format_string($jupyter->name), $jupyter->visible ? [] : ['class' => 'dimmed']);
    $table->data[] = [$link, format_module_intro('jupyter', $jupyter, $jupyter->coursemodule)];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
