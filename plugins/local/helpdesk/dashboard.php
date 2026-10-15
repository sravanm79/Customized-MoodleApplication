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
 * Admin support ticket dashboard.
 *
 * @package   local_helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
use local_helpdesk\ticket_manager;

require_login();
require_capability('moodle/site:config', context_system::instance());

$status   = optional_param('status',   '',      PARAM_ALPHA);
$type     = optional_param('type',     '',      PARAM_ALPHA);
$priority = optional_param('priority', '',      PARAM_ALPHA);
$page     = optional_param('page',     0,       PARAM_INT);
$perpage  = 25;

$url = new moodle_url('/local/helpdesk/dashboard.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('dashboard', 'local_helpdesk'));
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->set_pagelayout('admin');

// Build WHERE clause.
$params = [];
$where  = [];
if ($status && in_array($status, ticket_manager::STATUSES)) {
    $where[]          = 'status = :status';
    $params['status'] = $status;
}
if ($type && in_array($type, ticket_manager::TYPES)) {
    $where[]        = 'type = :type';
    $params['type'] = $type;
}
if ($priority && in_array($priority, ticket_manager::PRIORITIES)) {
    $where[]            = 'priority = :priority';
    $params['priority'] = $priority;
}
$wheresql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total   = $DB->count_records_sql("SELECT COUNT(*) FROM {local_helpdesk_ticket} $wheresql", $params);
$tickets = $DB->get_records_sql(
    "SELECT * FROM {local_helpdesk_ticket} $wheresql ORDER BY timecreated DESC",
    $params, $page * $perpage, $perpage
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_helpdesk'));

// ---- Summary chips ----
$opencnt  = $DB->count_records('local_helpdesk_ticket', ['status' => 'open']);
$highcnt  = $DB->count_records_select('local_helpdesk_ticket',
    "status NOT IN ('resolved','closed') AND priority IN ('high','urgent')");

echo html_writer::div(
    html_writer::span(get_string('status_open', 'local_helpdesk') . ': ' . $opencnt,
        'badge bg-primary me-2 fs-6') .
    html_writer::span('High/Urgent: ' . $highcnt, 'badge bg-danger me-2 fs-6') .
    html_writer::span('Total: ' . $total, 'badge bg-secondary fs-6'),
    'mb-3'
);

// ---- Filter bar ----
$filterurl = new moodle_url($url);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false),
    'class' => 'd-flex flex-wrap gap-2 mb-3 align-items-end']);

// Status.
$statusopts = ['' => get_string('filter_all', 'local_helpdesk')];
foreach (ticket_manager::STATUSES as $s) {
    $statusopts[$s] = get_string('status_' . $s, 'local_helpdesk');
}
echo html_writer::div(
    html_writer::label(get_string('filter_status', 'local_helpdesk'), 'filter-status', false, ['class' => 'form-label mb-1 small']) .
    html_writer::select($statusopts, 'status', $status, false, ['class' => 'form-select form-select-sm', 'id' => 'filter-status']),
    ''
);

// Type.
$typeopts = ['' => get_string('filter_all', 'local_helpdesk')];
foreach (ticket_manager::TYPES as $t) {
    $typeopts[$t] = get_string('type_' . $t, 'local_helpdesk');
}
echo html_writer::div(
    html_writer::label(get_string('filter_type', 'local_helpdesk'), 'filter-type', false, ['class' => 'form-label mb-1 small']) .
    html_writer::select($typeopts, 'type', $type, false, ['class' => 'form-select form-select-sm', 'id' => 'filter-type']),
    ''
);

// Priority.
$priopts = ['' => get_string('filter_all', 'local_helpdesk')];
foreach (ticket_manager::PRIORITIES as $p) {
    $priopts[$p] = get_string('priority_' . $p, 'local_helpdesk');
}
echo html_writer::div(
    html_writer::label(get_string('filter_priority', 'local_helpdesk'), 'filter-priority', false, ['class' => 'form-label mb-1 small']) .
    html_writer::select($priopts, 'priority', $priority, false, ['class' => 'form-select form-select-sm', 'id' => 'filter-priority']),
    ''
);

echo html_writer::tag('button', get_string('filterby', 'local_helpdesk'),
    ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary align-self-end']);
if ($status || $type || $priority) {
    echo html_writer::link($url, '✕ Clear', ['class' => 'btn btn-sm btn-link align-self-end']);
}
echo html_writer::end_tag('form');

if (empty($tickets)) {
    echo $OUTPUT->notification(get_string('notickets', 'local_helpdesk'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// ---- Ticket table ----
$table              = new html_table();
$table->head        = [
    get_string('ticketid',   'local_helpdesk'),
    get_string('type_label', 'local_helpdesk'),
    get_string('subject',    'local_helpdesk'),
    get_string('priority',   'local_helpdesk') . ' / ' . get_string('filter_status', 'local_helpdesk'),
    get_string('submittedby', 'local_helpdesk'),
    get_string('assignedto',  'local_helpdesk'),
    get_string('submitted',   'local_helpdesk'),
    '',
];
$table->attributes['class'] = 'generaltable table-hover';

foreach ($tickets as $ticket) {
    $submitter = core_user::get_user($ticket->userid);
    $assignee  = $ticket->assigneeid ? core_user::get_user($ticket->assigneeid) : null;
    $ticketurl = new moodle_url('/local/helpdesk/ticket.php', ['id' => $ticket->id]);

    $table->data[] = [
        html_writer::tag('span', '#' . $ticket->id, ['class' => 'text-muted small']),
        ticket_manager::type_badge($ticket->type),
        html_writer::link($ticketurl, s($ticket->subject)),
        ticket_manager::priority_badge($ticket->priority) . ' ' . ticket_manager::status_badge($ticket->status),
        $submitter ? fullname($submitter) : '?',
        $assignee  ? fullname($assignee)  : html_writer::tag('em', get_string('unassigned', 'local_helpdesk'), ['class' => 'text-muted']),
        userdate($ticket->timecreated, get_string('strftimedatetimeshort')),
        $OUTPUT->single_button($ticketurl, get_string('view', 'local_helpdesk'), 'get', ['type' => 'secondary']),
    ];
}

echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($url,
    ['status' => $status, 'type' => $type, 'priority' => $priority]));
echo $OUTPUT->footer();
