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
 * Register students: choose courses, add one student or a list, preview, confirm; then share the credentials.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_studentportal\form\register_form;
use local_studentportal\local\credentials;
use local_studentportal\local\registrar;

$courseid = optional_param('courseid', 0, PARAM_INT);
$planid = optional_param('plan', '', PARAM_ALPHANUM);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$systemcontext = context_system::instance();
require_capability('local/studentportal:register', $systemcontext);
require_capability('moodle/user:create', $systemcontext);

$url = new moodle_url('/local/studentportal/register.php', $courseid ? ['courseid' => $courseid] : []);
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('registerstudents', 'local_studentportal'));
$PAGE->set_heading(get_string('registerstudents', 'local_studentportal'));
$PAGE->add_body_class('local-studentportal');

// Courses the user may enrol students into.
$courses = [];
foreach (get_courses('all', 'c.fullname ASC', 'c.id, c.fullname, c.shortname, c.visible') as $course) {
    if ($course->id == SITEID || !has_capability('enrol/manual:enrol', context_course::instance($course->id))) {
        continue;
    }
    $courses[$course->id] = format_string($course->fullname) . ' (' . format_string($course->shortname) . ')'
        . ($course->visible ? '' : ' · ' . get_string('hidden', 'local_studentportal'));
}

// Step 3: confirmed. Re-check the plan against the current data, then create and enrol.
if ($confirm && $planid && confirm_sesskey()) {
    $stored = $SESSION->local_studentportal_plans[$planid] ?? null;
    unset($SESSION->local_studentportal_plans[$planid]);
    if (!$stored) {
        redirect($url, get_string('error_planexpired', 'local_studentportal'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $registrar = new registrar($stored['courseids']);
    $planned = $registrar->plan($stored['rows'], $stored['resetexisting']);
    $results = $registrar->execute($planned);
    $key = credentials::store($results);
    redirect(new moodle_url('/local/studentportal/credentials.php', ['key' => $key]));
}

$form = new register_form($url, ['courses' => $courses]);
if ($courseid && isset($courses[$courseid])) {
    $form->set_data(['courses' => [$courseid], 'mode' => 'list']);
}

if ($form->is_cancelled()) {
    redirect($courseid ? new moodle_url('/local/studentportal/students.php', ['id' => $courseid]) : new moodle_url('/my/'));
}

if ($data = $form->get_data()) {
    // Step 2: preview what will happen to every row.
    $courseids = array_values(array_intersect(array_map('intval', (array) $data->courses), array_keys($courses)));
    if ($data->mode === 'single') {
        $rows = [['line' => 1, 'idnumber' => trim($data->idnumber), 'firstname' => trim($data->firstname),
            'lastname' => trim($data->lastname), 'email' => core_text::strtolower(trim($data->email))]];
    } else {
        $content = trim((string) $form->get_file_content('csvfile'));
        $content .= ($content !== '' && trim($data->pasted) !== '' ? "\n" : '') . trim($data->pasted);
        $rows = registrar::parse_csv($content);
    }
    $registrar = new registrar($courseids);
    $planned = $registrar->plan($rows, !empty($data->resetexisting));

    $planid = random_string(12);
    $SESSION->local_studentportal_plans = [$planid => ['courseids' => $courseids, 'rows' => $rows,
        'resetexisting' => !empty($data->resetexisting)]];

    $counts = array_count_values(array_column($planned, 'action'));
    $actionable = ($counts[registrar::ACTION_CREATE] ?? 0) + ($counts[registrar::ACTION_ENROL] ?? 0);
    $context = [
        'courses' => array_map(fn($id) => ['name' => $courses[$id]], $courseids),
        'rows' => array_map(fn($r) => [
            'line' => $r['line'],
            'idnumber' => $r['idnumber'],
            'name' => trim($r['firstname'] . ' ' . $r['lastname']),
            'email' => $r['email'],
            'username' => $r['username'] ?? '',
            'message' => $r['message'],
            'action' => $r['action'],
            'label' => get_string('action_' . $r['action'], 'local_studentportal'),
        ], $planned),
        'total' => count($planned),
        'create' => $counts[registrar::ACTION_CREATE] ?? 0,
        'enrol' => $counts[registrar::ACTION_ENROL] ?? 0,
        'nothing' => $counts[registrar::ACTION_NOTHING] ?? 0,
        'errors' => $counts[registrar::ACTION_ERROR] ?? 0,
        'canconfirm' => $actionable > 0,
        'confirmlabel' => get_string('confirmregister', 'local_studentportal', $actionable),
        'confirmurl' => $url->out(false),
        'planid' => $planid,
        'sesskey' => sesskey(),
        'backurl' => $url->out(false),
    ];
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_studentportal/register_preview', $context);
    echo $OUTPUT->footer();
    exit;
}

// Step 1: the form.
echo $OUTPUT->header();
echo html_writer::tag('p', get_string('registerintro', 'local_studentportal'), ['class' => 'local-studentportal-intro']);
$form->display();
echo $OUTPUT->footer();
