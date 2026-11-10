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

namespace plagiarism_similarity\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Site settings of the similarity check.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('advcheckbox', 'enabled', get_string('settingenabled', 'plagiarism_similarity'),
            get_string('settingenabled_desc', 'plagiarism_similarity'));
        $mform->addElement('advcheckbox', 'defaulton', get_string('settingdefaulton', 'plagiarism_similarity'),
            get_string('settingdefaulton_desc', 'plagiarism_similarity'));
        $mform->addElement('text', 'threshold', get_string('formthreshold', 'plagiarism_similarity'), ['size' => 4]);
        $mform->setType('threshold', PARAM_INT);
        $mform->addHelpButton('threshold', 'formthreshold', 'plagiarism_similarity');
        $this->add_action_buttons(false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['threshold'] < 1 || $data['threshold'] > 100) {
            $errors['threshold'] = get_string('thresholderror', 'plagiarism_similarity');
        }
        return $errors;
    }
}
