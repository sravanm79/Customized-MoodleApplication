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

namespace local_notifier\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_notifier\notifier;

/**
 * Course reminder lead times. Empty fields use the site default.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('static', 'intro', '', get_string('coursereminders_intro', 'local_notifier'));
        foreach (notifier::TYPES as $type) {
            $name = $type . '_lead_times';
            $default = get_config('local_notifier', $name) ?: get_string('none');
            $mform->addElement('text', $type, get_string($name, 'local_notifier'), ['size' => 20]);
            $mform->setType($type, PARAM_TEXT);
            $mform->addElement('static', $type . '_default', '',
                get_string('coursereminders_sitedefault', 'local_notifier', s($default)));
        }
        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (notifier::TYPES as $type) {
            if (!notifier::valid_leads((string) ($data[$type] ?? ''))) {
                $errors[$type] = get_string('invalidleads', 'local_notifier');
            } else if (max(notifier::parse_leads((string) $data[$type]) ?: [0])
                    > \local_notifier\task\send_deadline_notifications::MAX_LEAD_MINUTES) {
                $errors[$type] = get_string('leadtoolong', 'local_notifier');
            }
        }
        return $errors;
    }
}
