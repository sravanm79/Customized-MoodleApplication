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

namespace quizaccess_examproctor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quiz\quiz_attempt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/examproctor/rule.php');

/**
 * Records a proctoring event for the current user's in-progress quiz attempt.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_event extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt id'),
            'eventtype' => new external_value(PARAM_ALPHA, 'Event type'),
            'details' => new external_value(PARAM_TEXT, 'Extra details, e.g. seconds away', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $attemptid, string $eventtype, string $details = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
                ['attemptid' => $attemptid, 'eventtype' => $eventtype, 'details' => $details]);

        $isviolation = in_array($params['eventtype'], \quizaccess_examproctor::VIOLATION_TYPES);
        if (!$isviolation && !in_array($params['eventtype'], \quizaccess_examproctor::INFO_TYPES)) {
            throw new \invalid_parameter_exception('Unknown event type');
        }

        $attemptobj = quiz_attempt::create($params['attemptid']);
        $context = \context_module::instance($attemptobj->get_cmid());
        self::validate_context($context);

        if ($attemptobj->get_userid() != $USER->id) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }

        $settings = $DB->get_record('quizaccess_examproctor', ['quizid' => $attemptobj->get_quizid()]);
        $state = $attemptobj->get_state();
        $active = in_array($state, [quiz_attempt::IN_PROGRESS, quiz_attempt::OVERDUE]);
        if (!$settings || !$active) {
            // Nothing to proctor (disabled, or already submitted); tell the client to stand down.
            return ['violations' => 0, 'maxviolations' => 0, 'action' => 'stop'];
        }

        $DB->insert_record('quizaccess_examproctor_log', (object) [
            'quizid' => $attemptobj->get_quizid(),
            'attemptid' => $attemptobj->get_attemptid(),
            'userid' => $USER->id,
            'eventtype' => $params['eventtype'],
            'isviolation' => (int) $isviolation,
            'details' => \core_text::substr($params['details'], 0, 255),
            'timecreated' => time(),
        ]);

        $violations = $DB->count_records('quizaccess_examproctor_log',
                ['attemptid' => $attemptobj->get_attemptid(), 'isviolation' => 1]);
        $max = (int) $settings->maxviolations;

        $action = 'warn';
        if ($max > 0 && $violations >= $max) {
            // The client submits the form so that answers on the current page are saved. If a violation
            // arrives past the limit, the client ignored that instruction: submit on the server instead.
            if ($violations > $max) {
                $timenow = time();
                $attemptobj->process_submit($timenow, false, null, true);
                $attemptobj->process_grade_submission($timenow);
            }
            $action = 'submit';
        }

        return ['violations' => $violations, 'maxviolations' => $max, 'action' => $action];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'violations' => new external_value(PARAM_INT, 'Violations recorded so far for this attempt'),
            'maxviolations' => new external_value(PARAM_INT, 'Violation limit, 0 = unlimited'),
            'action' => new external_value(PARAM_ALPHA, 'warn | submit | stop'),
        ]);
    }
}
