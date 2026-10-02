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
 * Activity settings form for mod_jupyter.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/jupyter/lib.php');

/**
 * Settings form.
 */
class mod_jupyter_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('filemanager', 'notebookfile', get_string('notebookfile', 'mod_jupyter'), null,
            jupyter_notebook_file_options());
        $mform->addHelpButton('notebookfile', 'notebookfile', 'mod_jupyter');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Load the existing starter notebook into the file manager.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        $draftitemid = file_get_submitted_draft_itemid('notebookfile');
        $contextid = $this->current->instance ? $this->context->id : null;
        file_prepare_draft_area($draftitemid, $contextid, 'mod_jupyter', 'notebook', 0,
            jupyter_notebook_file_options());
        $defaultvalues['notebookfile'] = $draftitemid;
    }
}
