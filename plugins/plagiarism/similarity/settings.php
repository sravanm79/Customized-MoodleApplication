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
 * Site settings page of the similarity check (Site administration > Plugins > Plagiarism > Similarity check).
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('plagiarismsimilarity');

$form = new \plagiarism_similarity\form\settings_form();
$form->set_data([
    'enabled' => (int) get_config('plagiarism_similarity', 'enabled'),
    'defaulton' => (int) get_config('plagiarism_similarity', 'defaulton'),
    'threshold' => (int) get_config('plagiarism_similarity', 'threshold') ?: 40,
]);
if ($data = $form->get_data()) {
    foreach (['enabled', 'defaulton', 'threshold'] as $name) {
        set_config($name, (int) $data->$name, 'plagiarism_similarity');
    }
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'plagiarism_similarity'));
if (empty($CFG->enableplagiarism)) {
    echo $OUTPUT->notification(get_string('plagiarismoff', 'plagiarism_similarity',
        (new moodle_url('/admin/search.php', ['query' => 'enableplagiarism']))->out()), 'warning');
}
echo $OUTPUT->box(get_string('settingsintro', 'plagiarism_similarity'));
$form->display();
echo $OUTPUT->footer();
