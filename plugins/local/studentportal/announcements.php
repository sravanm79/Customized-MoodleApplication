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
 * Announcements: what a student received, what staff sent, and one announcement in full.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_studentportal\local\announcements;

$id = optional_param('id', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('guestsarenotallowed');
}
$systemcontext = context_system::instance();
$url = new moodle_url('/local/studentportal/announcements.php', $id ? ['id' => $id] : []);
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('announcements', 'local_studentportal'));
$PAGE->set_heading(get_string('announcements', 'local_studentportal'));
$PAGE->add_body_class('local-studentportal');

$export = function(stdClass $a, bool $full) use ($systemcontext): array {
    $author = core_user::get_user($a->userid);
    $html = format_text($a->message, $a->messageformat, ['context' => $systemcontext]);
    return [
        'id' => $a->id,
        'subject' => format_string($a->subject),
        'author' => $author ? fullname($author) : '',
        'audience' => announcements::audience_text($a),
        'date' => userdate($a->timesent ?: $a->timecreated, get_string('strftimedatetime', 'langconfig')),
        'isnew' => ($a->timesent ?: $a->timecreated) > time() - 3 * DAYSECS,
        'message' => $full ? $html : '',
        'snippet' => shorten_text(html_to_text($html, 0, false), 220),
        'url' => (new moodle_url('/local/studentportal/announcements.php', ['id' => $a->id]))->out(false),
        'status' => get_string('annstatus_' . $a->status, 'local_studentportal', $a->recipients),
        'queued' => $a->status !== 'sent',
    ];
};

if ($id) {
    $announcement = $DB->get_record('local_studentportal_ann', ['id' => $id], '*', MUST_EXIST);
    if (!announcements::can_view($announcement, $USER->id)) {
        throw new moodle_exception('error_noannouncement', 'local_studentportal');
    }
    $PAGE->navbar->add(get_string('announcements', 'local_studentportal'), new moodle_url('/local/studentportal/announcements.php'));
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_studentportal/announcement', $export($announcement, true) + [
        'backurl' => (new moodle_url('/local/studentportal/announcements.php'))->out(false),
    ]);
    echo $OUTPUT->footer();
    exit;
}

$received = array_map(fn($a) => $export($a, false), announcements::for_student($USER->id, 50));
$cansend = has_capability('local/studentportal:announcesite', $systemcontext) || announcements::courses_for($USER->id);
$sent = $cansend ? array_map(fn($a) => $export($a, false), announcements::sent_visible_to($USER->id)) : [];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studentportal/announcements', [
    'received' => $received,
    'hasreceived' => !empty($received),
    'showreceived' => !empty($received) || !$cansend,
    'cansend' => (bool) $cansend,
    'sendurl' => (new moodle_url('/local/studentportal/announce.php'))->out(false),
    'sent' => $sent,
    'hassent' => !empty($sent),
]);
echo $OUTPUT->footer();
