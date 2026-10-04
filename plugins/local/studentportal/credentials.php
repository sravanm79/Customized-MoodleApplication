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
 * Share freshly issued credentials: view, download as CSV, print one slip per student, or email each student.
 * The passwords exist only in this admin's session for 15 minutes.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_studentportal\local\credentials;

$key = required_param('key', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
$systemcontext = context_system::instance();
require_capability('local/studentportal:register', $systemcontext);

$url = new moodle_url('/local/studentportal/credentials.php', ['key' => $key]);
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('credentials', 'local_studentportal'));
$PAGE->set_heading(get_string('credentials', 'local_studentportal'));
$PAGE->add_body_class('local-studentportal');
// Never cache a page with passwords on it.
header('Cache-Control: no-store, no-cache, must-revalidate');

$results = credentials::get($key);
if ($results === null) {
    redirect(new moodle_url('/local/studentportal/register.php'), get_string('error_batchexpired', 'local_studentportal'),
        null, \core\output\notification::NOTIFY_WARNING);
}

if ($action === 'download') {
    require_sesskey();
    $filename = 'student-credentials-' . userdate(time(), '%Y%m%d-%H%M', 99, false) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo credentials::csv($results);
    exit;
}

if ($action === 'email') {
    require_sesskey();
    $outcome = credentials::email($results);
    $message = get_string('emailsent', 'local_studentportal', $outcome['sent']);
    if ($outcome['failed']) {
        $message .= ' ' . get_string('emailfailed', 'local_studentportal', implode(', ', $outcome['failed']));
    }
    redirect($url, $message, null, $outcome['failed'] ? \core\output\notification::NOTIFY_WARNING
        : \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'done') {
    require_sesskey();
    credentials::forget($key);
    redirect(new moodle_url('/local/studentportal/register.php'), get_string('credentialsforgotten', 'local_studentportal'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$rows = array_map(fn($r) => $r + [
    'haspassword' => $r['password'] !== null,
    'coursestext' => implode(', ', $r['courses']),
    'status' => get_string($r['created'] ? 'status_created' : ($r['password'] !== null ? 'status_reset' : 'status_enrolled'),
        'local_studentportal'),
], $results);

$context = [
    'rows' => $rows,
    'count' => count($rows),
    'created' => count(array_filter($results, fn($r) => $r['created'])),
    'withpassword' => count(array_filter($results, fn($r) => $r['password'] !== null)),
    'loginurl' => credentials::login_url(),
    'certurl' => credentials::certificate_url(),
    'sitename' => format_string($SITE->fullname),
    'downloadurl' => (new moodle_url($url, ['action' => 'download', 'sesskey' => sesskey()]))->out(false),
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
    'expires' => userdate(time() + credentials::LIFETIME, get_string('strftimetime', 'langconfig')),
    'print' => $action === 'print',
    'printurl' => (new moodle_url($url, ['action' => 'print']))->out(false),
    'backurl' => $url->out(false),
];

if ($action === 'print') {
    $PAGE->set_pagelayout('print');
    $PAGE->add_body_class('local-studentportal-print');
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template($action === 'print' ? 'local_studentportal/slips' : 'local_studentportal/credentials',
    $context);
echo $OUTPUT->footer();
