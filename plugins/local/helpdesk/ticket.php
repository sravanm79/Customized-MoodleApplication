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
 * Single ticket view: details, reply thread, admin controls.
 *
 * @package   local_helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
use local_helpdesk\ticket_manager;
use core\output\notification;

$id     = required_param('id', PARAM_INT);
$ticket = $DB->get_record('local_helpdesk_ticket', ['id' => $id], '*', MUST_EXIST);

require_login();
$syscontext = context_system::instance();
$isadmin    = has_capability('moodle/site:config', $syscontext);

// Submitter can see their own; admins see all.
if (!$isadmin && (int) $ticket->userid !== (int) $USER->id) {
    throw new moodle_exception('accessdenied', 'error');
}

$url        = new moodle_url('/local/helpdesk/ticket.php', ['id' => $id]);
$dashurl    = new moodle_url('/local/helpdesk/dashboard.php');
$PAGE->set_url($url);
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('ticketdetail', 'local_helpdesk', $id));
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->set_pagelayout($isadmin ? 'admin' : 'standard');

// ---- Handle admin actions (POST) ----
if ($isadmin && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'reply') {
        $message   = required_param('replymessage', PARAM_RAW);
        $isprivate = optional_param('isprivate', 0, PARAM_INT);
        if (trim($message) !== '') {
            ticket_manager::add_reply($id, (int) $USER->id, clean_text(trim($message)), (bool) $isprivate);
        }
        redirect($url, get_string('replysent', 'local_helpdesk'), null, notification::NOTIFY_SUCCESS);
    }

    if ($action === 'update') {
        $newstatus   = optional_param('status',    '',  PARAM_ALPHA);
        $newpriority = optional_param('priority',  '',  PARAM_ALPHA);
        $newassignee = optional_param('assigneeid', 0,  PARAM_INT);

        if ($newstatus   && $newstatus   !== $ticket->status)   ticket_manager::update_status($id, $newstatus);
        if ($newpriority && $newpriority !== $ticket->priority) ticket_manager::update_priority($id, $newpriority);
        if ($newassignee && $newassignee !== (int) $ticket->assigneeid) ticket_manager::assign($id, $newassignee);

        redirect($url, get_string('updatedsuccess', 'local_helpdesk'), null, notification::NOTIFY_SUCCESS);
    }
}

// Re-fetch in case of updates.
$ticket  = $DB->get_record('local_helpdesk_ticket', ['id' => $id], '*', MUST_EXIST);
$replies = $DB->get_records('local_helpdesk_reply', ['ticketid' => $id], 'timecreated ASC');

echo $OUTPUT->header();
echo html_writer::link($dashurl, get_string('backtolist', 'local_helpdesk'), ['class' => 'd-inline-block mb-3 text-decoration-none']);
echo $OUTPUT->heading(get_string('ticketdetail', 'local_helpdesk', $id));

// ---- Ticket header card ----
$submitter = core_user::get_user($ticket->userid);
$assignee  = $ticket->assigneeid ? core_user::get_user($ticket->assigneeid) : null;

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
echo html_writer::tag('h5', s($ticket->subject), ['class' => 'mb-0']);
echo html_writer::div(
    ticket_manager::priority_badge($ticket->priority) . ' ' .
    ticket_manager::status_badge($ticket->status),
    ''
);
echo html_writer::end_div(); // card-header

echo html_writer::start_div('card-body');
echo html_writer::tag('p', format_text($ticket->description, FORMAT_PLAIN), ['class' => 'mb-3']);

// Context metadata.
echo html_writer::start_div('row g-2 small text-muted border-top pt-3');
$meta = [
    get_string('type_label',   'local_helpdesk') => ticket_manager::type_badge($ticket->type),
    get_string('submittedby',  'local_helpdesk') => $submitter ? fullname($submitter) : '?',
    get_string('assignedto',   'local_helpdesk') => $assignee  ? fullname($assignee)
        : html_writer::tag('em', get_string('unassigned', 'local_helpdesk')),
    get_string('submitted',    'local_helpdesk') => userdate($ticket->timecreated),
    get_string('pageurl',      'local_helpdesk') => $ticket->pageurl
        ? html_writer::tag('code', s($ticket->pageurl)) : '—',
    get_string('userroles',    'local_helpdesk') => s($ticket->userroles ?: '—'),
    get_string('useragent',    'local_helpdesk') => html_writer::tag('small',
        s(substr($ticket->useragent ?? '', 0, 80))),
];
foreach ($meta as $label => $val) {
    echo html_writer::div(
        html_writer::tag('strong', $label . ': ') . $val,
        'col-md-6 col-12'
    );
}
echo html_writer::end_div();
echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// ---- Admin controls ----
if ($isadmin) {
    // Collect staff users for assignee dropdown.
    $admins = get_admins();
    $staffopts = [0 => get_string('unassigned', 'local_helpdesk')];
    foreach ($admins as $a) {
        $staffopts[$a->id] = fullname($a);
    }

    $statusopts   = [];
    foreach (ticket_manager::STATUSES   as $s) { $statusopts[$s]   = get_string('status_'   . $s, 'local_helpdesk'); }
    $priorityopts = [];
    foreach (ticket_manager::PRIORITIES as $p) { $priorityopts[$p] = get_string('priority_' . $p, 'local_helpdesk'); }

    echo html_writer::start_div('card mb-4 border-warning');
    echo html_writer::div(html_writer::tag('h6', 'Admin Controls', ['class' => 'mb-0']), 'card-header bg-warning bg-opacity-25');
    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-flex flex-wrap gap-3 align-items-end']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'update']);

    foreach ([
        ['status',    get_string('changestatus',   'local_helpdesk'), $statusopts,   $ticket->status],
        ['priority',  get_string('changepriority', 'local_helpdesk'), $priorityopts, $ticket->priority],
        ['assigneeid', get_string('assignticket',  'local_helpdesk'), $staffopts,    (int) $ticket->assigneeid],
    ] as [$name, $label, $opts, $selected]) {
        echo html_writer::div(
            html_writer::tag('label', $label, ['class' => 'form-label small mb-1']) .
            html_writer::select($opts, $name, $selected, false, ['class' => 'form-select form-select-sm']),
            ''
        );
    }
    echo html_writer::tag('button', get_string('save', 'local_helpdesk'),
        ['type' => 'submit', 'class' => 'btn btn-sm btn-warning align-self-end']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// ---- Replies ----
echo $OUTPUT->heading(get_string('replies', 'local_helpdesk'), 3);

if (empty($replies)) {
    echo html_writer::tag('p', get_string('noreplies', 'local_helpdesk'), ['class' => 'text-muted']);
} else {
    foreach ($replies as $reply) {
        // Non-admins don't see private notes.
        if ($reply->isprivate && !$isadmin) {
            continue;
        }
        $author  = core_user::get_user($reply->userid);
        $ismine  = (int) $reply->userid === (int) $USER->id;
        $bgcolor = $reply->isprivate ? 'border-warning bg-warning bg-opacity-10'
                 : ($ismine           ? 'border-primary bg-primary bg-opacity-10' : '');
        echo html_writer::start_div('card mb-2 ' . $bgcolor);
        echo html_writer::start_div('card-body py-2');
        echo html_writer::div(
            html_writer::tag('strong', $author ? fullname($author) : '?') . ' · ' .
            html_writer::tag('small', userdate($reply->timecreated), ['class' => 'text-muted']) .
            ($reply->isprivate ? ' ' . html_writer::span('internal note', 'badge bg-warning text-dark ms-1') : ''),
            'mb-1'
        );
        echo html_writer::tag('p', format_text($reply->message, FORMAT_PLAIN), ['class' => 'mb-0']);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

// ---- Reply form (admin only) ----
if ($isadmin) {
    echo html_writer::start_div('card mt-3 border-success');
    echo html_writer::div(html_writer::tag('h6', get_string('addreply', 'local_helpdesk'), ['class' => 'mb-0']),
        'card-header bg-success bg-opacity-10');
    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'reply']);
    echo html_writer::div(
        html_writer::tag('label', get_string('replymessage', 'local_helpdesk'), ['for' => 'replymessage', 'class' => 'form-label']) .
        html_writer::tag('textarea', '', ['class' => 'form-control', 'id' => 'replymessage',
            'name' => 'replymessage', 'rows' => 4, 'required' => 'required']),
        'mb-3'
    );
    echo html_writer::div(
        html_writer::tag('div',
            html_writer::empty_tag('input', ['class' => 'form-check-input', 'type' => 'checkbox',
                'name' => 'isprivate', 'id' => 'isprivate', 'value' => '1']) .
            html_writer::tag('label', get_string('isprivate', 'local_helpdesk'),
                ['class' => 'form-check-label', 'for' => 'isprivate']),
            'form-check'
        ),
        'mb-3'
    );
    echo html_writer::tag('button', get_string('postreply', 'local_helpdesk'),
        ['type' => 'submit', 'class' => 'btn btn-success']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
