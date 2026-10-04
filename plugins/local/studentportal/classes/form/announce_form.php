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
 * Send an announcement: audience (every student, or the students of chosen courses), subject and message.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class announce_form extends \moodleform {

    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $audiences = [];
        if ($this->_customdata['cansite']) {
            $audiences['site'] = get_string('audience_site', 'local_studentportal');
        }
        if ($this->_customdata['courses']) {
            $audiences['courses'] = get_string('audience_chosen', 'local_studentportal');
        }
        $mform->addElement('select', 'audience', get_string('audience', 'local_studentportal'), $audiences);
        $mform->setDefault('audience', $this->_customdata['courses'] ? 'courses' : 'site');

        $mform->addElement('autocomplete', 'courses', get_string('courses', 'local_studentportal'),
            $this->_customdata['courses'], ['multiple' => true, 'noselectionstring' => get_string('choosecourses', 'local_studentportal')]);
        $mform->hideIf('courses', 'audience', 'neq', 'courses');

        $mform->addElement('text', 'subject', get_string('subject', 'local_studentportal'), ['size' => 60, 'maxlength' => 255]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'message', get_string('message', 'local_studentportal'), ['rows' => 10],
            ['maxfiles' => 0, 'noclean' => false, 'context' => \context_system::instance()]);
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('sendannouncement', 'local_studentportal'));
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
        if (($data['audience'] ?? '') === 'courses' && empty($data['courses'])) {
            $errors['courses'] = get_string('required');
        }
        if (trim(strip_tags($data['message']['text'] ?? '')) === '') {
            $errors['message'] = get_string('required');
        }
        return $errors;
    }
}
