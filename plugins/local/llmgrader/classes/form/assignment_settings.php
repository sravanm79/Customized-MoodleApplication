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

namespace local_llmgrader\form;

use local_llmgrader\assignment_config;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Per-assignment LLM grading settings: when to run, review, rubric, reference solution, guidelines.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_settings extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'triggermode', get_string('triggermode', 'local_llmgrader'), [
            assignment_config::TRIGGER_SUBMIT => get_string('trigger_submit', 'local_llmgrader'),
            assignment_config::TRIGGER_CLOSE => get_string('trigger_close', 'local_llmgrader'),
            assignment_config::TRIGGER_MANUAL => get_string('trigger_manual', 'local_llmgrader'),
        ]);
        $mform->addHelpButton('triggermode', 'triggermode', 'local_llmgrader');

        $mform->addElement('advcheckbox', 'requirereview', get_string('requirereview', 'local_llmgrader'),
            get_string('requirereview_label', 'local_llmgrader'));
        $mform->addHelpButton('requirereview', 'requirereview', 'local_llmgrader');

        foreach (['rubric' => 12, 'reference' => 14, 'guidelines' => 6] as $field => $rows) {
            $mform->addElement('textarea', $field, get_string($field, 'local_llmgrader'),
                ['rows' => $rows, 'cols' => 80, 'class' => 'font-monospace']);
            $mform->setType($field, PARAM_RAW);
            $mform->addHelpButton($field, $field, 'local_llmgrader');
        }
        $this->add_action_buttons();
    }
}
