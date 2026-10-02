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

use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * Proctored exam mode: distraction-free rendering, tab-switch / focus-loss / fullscreen-exit
 * detection, violation logging and auto-submit when the violation limit is reached.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_examproctor extends access_rule_base {

    /** @var string[] Event types that count towards the violation limit. */
    const VIOLATION_TYPES = ['tabhidden', 'windowblur', 'fullscreenexit'];

    /** @var string[] Event types that are only recorded. */
    const INFO_TYPES = ['returned', 'fullscreenenter', 'copy', 'cut', 'paste', 'contextmenu'];

    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->examproctor_enabled)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    public function description() {
        $messages = [get_string('studentdescription', 'quizaccess_examproctor', $this->rules_summary())];

        $context = $this->quizobj->get_context();
        if (has_capability('mod/quiz:viewreports', $context)) {
            $url = new moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $this->quizobj->get_cmid()]);
            $messages[] = html_writer::link($url, get_string('viewreport', 'quizaccess_examproctor'));
        }
        return $messages;
    }

    /**
     * Human-readable list of the rules in force for this quiz.
     *
     * @return string
     */
    protected function rules_summary(): string {
        $items = [get_string('ruletabswitch', 'quizaccess_examproctor')];
        if (!empty($this->quiz->examproctor_detectblur)) {
            $items[] = get_string('rulewindowblur', 'quizaccess_examproctor');
        }
        if (!empty($this->quiz->examproctor_requirefullscreen)) {
            $items[] = get_string('rulefullscreen', 'quizaccess_examproctor');
        }
        if (!empty($this->quiz->examproctor_blockcopypaste)) {
            $items[] = get_string('rulecopypaste', 'quizaccess_examproctor');
        }
        $max = (int) $this->quiz->examproctor_maxviolations;
        $items[] = $max > 0
            ? get_string('rulemaxviolations', 'quizaccess_examproctor', $max)
            : get_string('rulenolimit', 'quizaccess_examproctor');
        return html_writer::alist($items);
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION;
        return empty($SESSION->examproctoragreed[$this->quiz->id]);
    }

    public function add_preflight_check_form_fields(preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {
        $mform->addElement('header', 'examproctorheader', get_string('preflightheader', 'quizaccess_examproctor'));
        $mform->addElement('static', 'examproctorrules', '',
                get_string('studentdescription', 'quizaccess_examproctor', $this->rules_summary()));
        $mform->addElement('checkbox', 'examproctor_agree', '', get_string('preflightagree', 'quizaccess_examproctor'));
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['examproctor_agree'])) {
            $errors['examproctor_agree'] = get_string('preflightrequired', 'quizaccess_examproctor');
        }
        return $errors;
    }

    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        $SESSION->examproctoragreed[$this->quiz->id] = true;
    }

    public function current_attempt_finished() {
        global $SESSION;
        unset($SESSION->examproctoragreed[$this->quiz->id]);
    }

    public function setup_attempt_page($page) {
        global $DB;

        // Only the pages where the student is actively answering are proctored.
        if (!in_array($page->pagetype, ['mod-quiz-attempt', 'mod-quiz-summary'])) {
            return;
        }
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return;
        }

        // Distraction-free exam rendering.
        $page->set_popup_notification_allowed(false);
        $page->set_pagelayout('secure');
        $page->add_body_class('quizaccess-examproctor');
        if (!empty($this->quiz->examproctor_blockcopypaste)) {
            $page->add_body_class('quizaccess-examproctor-noselect');
        }

        [$insql, $params] = $DB->get_in_or_equal(self::VIOLATION_TYPES, SQL_PARAMS_NAMED);
        $params['attemptid'] = $attemptid;
        $violations = $DB->count_records_select('quizaccess_examproctor_log',
                "attemptid = :attemptid AND eventtype $insql", $params);

        $max = (int) $this->quiz->examproctor_maxviolations;
        $strings = [];
        foreach (['bartitle', 'barviolations', 'barnolimit', 'warningtitle', 'warningbody', 'warninglimit',
                  'warningok', 'fullscreentitle', 'fullscreenbody', 'fullscreenbutton', 'submittingtitle',
                  'submittingbody', 'blocked', 'ispreview'] as $key) {
            $strings[$key] = get_string('js_' . $key, 'quizaccess_examproctor');
        }

        $page->requires->js_call_amd('quizaccess_examproctor/proctor', 'init', [[
            'attemptid' => $attemptid,
            'violations' => $violations,
            'maxviolations' => $max,
            'requirefullscreen' => !empty($this->quiz->examproctor_requirefullscreen),
            'blockcopypaste' => !empty($this->quiz->examproctor_blockcopypaste),
            'detectblur' => !empty($this->quiz->examproctor_detectblur),
            'ispreview' => $this->quizobj->is_preview_user(),
            'strings' => $strings,
        ]]);
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('selectyesno', 'examproctor_enabled', get_string('enabled', 'quizaccess_examproctor'));
        $mform->addHelpButton('examproctor_enabled', 'enabled', 'quizaccess_examproctor');
        $mform->setDefault('examproctor_enabled', 0);

        $mform->addElement('text', 'examproctor_maxviolations', get_string('maxviolations', 'quizaccess_examproctor'),
                ['size' => 4]);
        $mform->setType('examproctor_maxviolations', PARAM_INT);
        $mform->setDefault('examproctor_maxviolations', 3);
        $mform->addHelpButton('examproctor_maxviolations', 'maxviolations', 'quizaccess_examproctor');
        $mform->hideIf('examproctor_maxviolations', 'examproctor_enabled', 'eq', 0);

        foreach (['requirefullscreen', 'detectblur', 'blockcopypaste'] as $field) {
            $mform->addElement('selectyesno', 'examproctor_' . $field, get_string($field, 'quizaccess_examproctor'));
            $mform->setDefault('examproctor_' . $field, 1);
            $mform->addHelpButton('examproctor_' . $field, $field, 'quizaccess_examproctor');
            $mform->hideIf('examproctor_' . $field, 'examproctor_enabled', 'eq', 0);
        }
    }

    public static function validate_settings_form_fields(array $errors, array $data, $files, mod_quiz_mod_form $quizform) {
        if (!empty($data['examproctor_enabled'])) {
            $max = $data['examproctor_maxviolations'] ?? 0;
            if (!is_numeric($max) || $max < 0 || $max > 100) {
                $errors['examproctor_maxviolations'] = get_string('maxviolationserror', 'quizaccess_examproctor');
            }
        }
        return $errors;
    }

    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->examproctor_enabled)) {
            $DB->delete_records('quizaccess_examproctor', ['quizid' => $quiz->id]);
            return;
        }
        $record = (object) [
            'quizid' => $quiz->id,
            'maxviolations' => (int) ($quiz->examproctor_maxviolations ?? 3),
            'requirefullscreen' => (int) !empty($quiz->examproctor_requirefullscreen),
            'detectblur' => (int) !empty($quiz->examproctor_detectblur),
            'blockcopypaste' => (int) !empty($quiz->examproctor_blockcopypaste),
        ];
        if ($existing = $DB->get_record('quizaccess_examproctor', ['quizid' => $quiz->id])) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_examproctor', $record);
        } else {
            $DB->insert_record('quizaccess_examproctor', $record);
        }
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_examproctor', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_examproctor_log', ['quizid' => $quiz->id]);
    }

    public static function get_settings_sql($quizid) {
        return [
            'CASE WHEN examproctor.id IS NULL THEN 0 ELSE 1 END AS examproctor_enabled, '
                // Defaults apply when the row is missing, so the settings form shows sensible values.
                . 'COALESCE(examproctor.maxviolations, 3) AS examproctor_maxviolations, '
                . 'COALESCE(examproctor.requirefullscreen, 1) AS examproctor_requirefullscreen, '
                . 'COALESCE(examproctor.detectblur, 1) AS examproctor_detectblur, '
                . 'COALESCE(examproctor.blockcopypaste, 1) AS examproctor_blockcopypaste',
            'LEFT JOIN {quizaccess_examproctor} examproctor ON examproctor.quizid = quiz.id',
            [],
        ];
    }
}
