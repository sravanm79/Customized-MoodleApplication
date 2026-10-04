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

namespace local_gradesheet\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Grade sheet upload: name, maximum score and file; once the file is read, the student ID and score columns
 * (guessed from the headers) with "Update preview" and "Import grades".
 *
 * Custom data: courseid, headers (string[] or null before a file is read), guess (['id' => ?int, 'score' => ?int]),
 * importable (int, rows that would be imported).
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $headers = $this->_customdata['headers'] ?? null;

        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('sheetname', 'local_gradesheet'), ['size' => 50, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'sheetname', 'local_gradesheet');

        $mform->addElement('float', 'grademax', get_string('grademax', 'local_gradesheet'));
        $mform->setDefault('grademax', 100);
        $mform->addRule('grademax', null, 'required', null, 'client');

        $mform->addElement('filepicker', 'sheetfile', get_string('sheetfile', 'local_gradesheet'), null, [
            'accepted_types' => ['.csv', '.txt', '.xlsx', '.xls', '.ods'],
            'maxfiles' => 1,
        ]);
        $mform->addRule('sheetfile', null, 'required');
        $mform->addHelpButton('sheetfile', 'sheetfile', 'local_gradesheet');

        $buttons = [$mform->createElement('submit', 'preview',
            get_string($headers ? 'updatepreview' : 'preview', 'local_gradesheet'))];
        if ($headers) {
            $options = [];
            foreach ($headers as $index => $header) {
                $options[$index] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) .
                    ($header !== '' ? ': ' . $header : '');
            }
            $guess = $this->_customdata['guess'] ?? [];
            $mform->addElement('select', 'idcolumn', get_string('idcolumn', 'local_gradesheet'), $options);
            $mform->setDefault('idcolumn', $guess['id'] ?? 0);
            $mform->addHelpButton('idcolumn', 'idcolumn', 'local_gradesheet');
            $mform->addElement('select', 'scorecolumn', get_string('scorecolumn', 'local_gradesheet'), $options);
            $mform->setDefault('scorecolumn', $guess['score'] ?? min(1, count($options) - 1));
            $mform->addHelpButton('scorecolumn', 'scorecolumn', 'local_gradesheet');

            if (!empty($this->_customdata['importable'])) {
                $buttons[] = $mform->createElement('submit', 'import',
                    get_string('importgrades', 'local_gradesheet', $this->_customdata['importable']));
            }
        }
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
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
        if (!isset($data['grademax']) || $data['grademax'] <= 0) {
            $errors['grademax'] = get_string('errorgrademax', 'local_gradesheet');
        }
        if (isset($data['idcolumn'], $data['scorecolumn']) && $data['idcolumn'] == $data['scorecolumn']) {
            $errors['scorecolumn'] = get_string('errorsamecolumn', 'local_gradesheet');
        }
        return $errors;
    }
}
