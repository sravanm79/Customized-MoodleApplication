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

namespace local_studentportal\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Register students: courses, then one student or a list (CSV file or pasted rows).
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'coursesheader', get_string('step_courses', 'local_studentportal'));
        $mform->addElement('autocomplete', 'courses', get_string('courses', 'local_studentportal'),
            $this->_customdata['courses'], ['multiple' => true, 'noselectionstring' => get_string('choosecourses', 'local_studentportal')]);
        $mform->addRule('courses', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('courses', 'courses', 'local_studentportal');

        $mform->addElement('header', 'studentsheader', get_string('step_students', 'local_studentportal'));
        $mform->setExpanded('studentsheader');
        $mform->addElement('select', 'mode', get_string('mode', 'local_studentportal'), [
            'single' => get_string('mode_single', 'local_studentportal'),
            'list' => get_string('mode_list', 'local_studentportal'),
        ]);

        $mform->addElement('text', 'idnumber', get_string('rollnumber', 'local_studentportal'), ['size' => 20]);
        $mform->setType('idnumber', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('idnumber', 'rollnumber', 'local_studentportal');
        $mform->addElement('text', 'firstname', get_string('firstname'), ['size' => 30]);
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addElement('text', 'lastname', get_string('lastname'), ['size' => 30]);
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addElement('text', 'email', get_string('email'), ['size' => 40]);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        foreach (['idnumber', 'firstname', 'lastname', 'email'] as $field) {
            $mform->hideIf($field, 'mode', 'neq', 'single');
        }

        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'local_studentportal'), null,
            ['accepted_types' => ['.csv', '.txt'], 'maxbytes' => 2 * 1024 * 1024]);
        $mform->addHelpButton('csvfile', 'csvfile', 'local_studentportal');
        $mform->addElement('textarea', 'pasted', get_string('pasted', 'local_studentportal'),
            ['rows' => 6, 'cols' => 70, 'class' => 'font-monospace',
             'placeholder' => "Roll number,First name,Last name,Email\n23BDS001,Asha,Rao,asha.rao@example.com"]);
        $mform->setType('pasted', PARAM_RAW);
        $mform->addHelpButton('pasted', 'pasted', 'local_studentportal');
        $mform->hideIf('csvfile', 'mode', 'neq', 'list');
        $mform->hideIf('pasted', 'mode', 'neq', 'list');

        $mform->addElement('header', 'optionsheader', get_string('step_options', 'local_studentportal'));
        $mform->addElement('advcheckbox', 'resetexisting', get_string('resetexisting', 'local_studentportal'),
            get_string('resetexisting_label', 'local_studentportal'));
        $mform->addHelpButton('resetexisting', 'resetexisting', 'local_studentportal');

        $this->add_action_buttons(true, get_string('preview', 'local_studentportal'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['courses'])) {
            $errors['courses'] = get_string('required');
        }
        if ($data['mode'] === 'single') {
            foreach (['firstname', 'lastname', 'email'] as $field) {
                if (trim($data[$field] ?? '') === '') {
                    $errors[$field] = get_string('required');
                }
            }
            if (empty($errors['email']) && !validate_email($data['email'])) {
                $errors['email'] = get_string('invalidemail');
            }
        } else if (trim($data['pasted'] ?? '') === '' && !$this->get_draft_files('csvfile')) {
            $errors['pasted'] = get_string('error_nolist', 'local_studentportal');
        }
        return $errors;
    }
}
