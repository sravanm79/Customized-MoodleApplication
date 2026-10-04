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
 * LLM grading settings of one assignment.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_llmgrader\assignment_config;
use local_llmgrader\form\assignment_settings;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);

$url = new moodle_url('/local/llmgrader/settings_assign.php', ['id' => $cm->id]);
$reporturl = new moodle_url('/local/llmgrader/report.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($cm->name) . ': ' . get_string('assignsettings', 'local_llmgrader'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$config = assignment_config::get($cm->id);
$form = new assignment_settings($url);
$form->set_data((object) ['id' => $cm->id, 'triggermode' => $config->triggermode, 'requirereview' => $config->requirereview,
    'rubric' => $config->rubric, 'reference' => $config->reference, 'guidelines' => $config->guidelines]);

if ($form->is_cancelled()) {
    redirect($reporturl);
}
if ($data = $form->get_data()) {
    $data->cmid = $cm->id;
    assignment_config::save($data);
    redirect($reporturl, get_string('settingssaved', 'local_llmgrader'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cm->name) . ': ' . get_string('assignsettings', 'local_llmgrader'));
echo html_writer::tag('p', get_string('assignsettings_intro', 'local_llmgrader'), ['class' => 'text-muted']);
$form->display();
echo $OUTPUT->footer();
