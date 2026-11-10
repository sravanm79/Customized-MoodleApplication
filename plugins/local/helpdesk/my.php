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
 * My Tickets: the tickets the current user has raised.
 *
 * @package   local_helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
use local_helpdesk\ticket_manager;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot raise tickets');
}

$url = new moodle_url('/local/helpdesk/my.php');
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_title(get_string('mytickets', 'local_helpdesk'));
$PAGE->set_heading(get_string('mytickets', 'local_helpdesk'));
$PAGE->set_pagelayout('standard');

$tickets = ticket_manager::for_user((int) $USER->id);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('mytickets_intro', 'local_helpdesk'), ['class' => 'text-muted']);

if (get_config('local_helpdesk', 'enabled')) {
    echo html_writer::tag('button', get_string('newticket', 'local_helpdesk'), [
        'type' => 'button', 'class' => 'btn btn-primary mb-3',
        'data-bs-toggle' => 'modal', 'data-bs-target' => '#local-helpdesk-modal',
    ]);
}

if (empty($tickets)) {
    echo $OUTPUT->notification(get_string('nomytickets', 'local_helpdesk'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table       = new html_table();
$table->head = [
    get_string('ticketid',   'local_helpdesk'),
    get_string('subject',    'local_helpdesk'),
    get_string('type_label', 'local_helpdesk'),
    get_string('filter_status', 'local_helpdesk'),
    get_string('replies',    'local_helpdesk'),
    get_string('submitted',  'local_helpdesk'),
    get_string('lastupdate', 'local_helpdesk'),
];
$table->attributes['class'] = 'generaltable table-hover';

foreach ($tickets as $ticket) {
    $ticketurl     = new moodle_url('/local/helpdesk/ticket.php', ['id' => $ticket->id]);
    $table->data[] = [
        html_writer::tag('span', '#' . $ticket->id, ['class' => 'text-muted small']),
        html_writer::link($ticketurl, s($ticket->subject)),
        ticket_manager::type_badge($ticket->type),
        ticket_manager::status_badge($ticket->status),
        (int) $ticket->replies,
        userdate($ticket->timecreated, get_string('strftimedatetimeshort')),
        userdate($ticket->timemodified, get_string('strftimedatetimeshort')),
    ];
}

echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->footer();
