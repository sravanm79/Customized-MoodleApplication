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

defined('MOODLE_INTERNAL') || die();

$string['pluginname']      = 'Help Desk';
$string['enabled']         = 'Enable Help Desk';
$string['enabled_desc']    = 'Show the feedback button on every page so users can submit bug reports and feature requests.';

// Floating button.
$string['fab_title']       = 'Submit feedback or get support';
$string['fab_label']       = '?';

// Modal.
$string['modal_title']     = 'Feedback & Support';
$string['modal_intro']     = 'Found a bug or have a suggestion? Let us know and we\'ll get back to you.';
$string['type']            = 'Type';
$string['type_bug']        = 'Bug Report';
$string['type_feature']    = 'Feature Request';
$string['type_other']      = 'Other / Question';
$string['subject']         = 'Subject';
$string['subject_help']    = 'A short summary of the issue or request.';
$string['description']     = 'Description';
$string['description_help'] = 'Please describe the issue or request in detail.';
$string['submit']          = 'Submit';
$string['cancel']          = 'Cancel';
$string['submitting']      = 'Submitting…';
$string['success_msg']     = 'Thank you! Your ticket #{$a} has been submitted. We\'ll follow up by email.';
$string['error_msg']       = 'Could not submit your ticket. Please try again.';

// Ticket statuses.
$string['status_open']        = 'Open';
$string['status_assigned']    = 'Assigned';
$string['status_in_progress'] = 'In Progress';
$string['status_resolved']    = 'Resolved';
$string['status_closed']      = 'Closed';

// Ticket priorities.
$string['priority_low']    = 'Low';
$string['priority_normal'] = 'Normal';
$string['priority_high']   = 'High';
$string['priority_urgent'] = 'Urgent';

// Ticket types.
$string['type_label']      = 'Type';

// Dashboard.
$string['dashboard']       = 'Help Desk';
$string['alltickets']      = 'All Tickets';
$string['notickets']       = 'No tickets found.';
$string['ticketid']        = '#';
$string['submittedby']     = 'Submitted by';
$string['assignedto']      = 'Assigned to';
$string['unassigned']      = 'Unassigned';
$string['submitted']       = 'Submitted';
$string['lastupdate']      = 'Last update';
$string['actions']         = 'Actions';
$string['view']            = 'View';
$string['filterby']        = 'Filter';
$string['filter_status']   = 'Status';
$string['filter_type']     = 'Type';
$string['filter_priority'] = 'Priority';
$string['filter_all']      = 'All';
$string['mytickets']       = 'My Tickets';
$string['mytickets_intro'] = 'Tickets you have raised. Open one to read staff replies or add more information.';
$string['nomytickets']     = 'You have not raised any tickets yet.';
$string['newticket']       = 'Raise a ticket';
$string['viewticket']      = 'View ticket';
$string['priority']        = 'Priority';
$string['newtickets']      = 'New (open)';

// Ticket detail page.
$string['ticketdetail']    = 'Ticket #{$a}';
$string['backtolist']      = '← Back to dashboard';
$string['backtomytickets'] = '← Back to My Tickets';
$string['context']         = 'Context';
$string['pageurl']         = 'Page';
$string['userroles']       = 'User roles';
$string['useragent']       = 'Browser';
$string['replies']         = 'Replies';
$string['noreplies']       = 'No replies yet.';
$string['addreply']        = 'Add Reply';
$string['replymessage']    = 'Message';
$string['isprivate']       = 'Internal note (staff only)';
$string['postreply']       = 'Post Reply';
$string['replysent']       = 'Reply posted.';
$string['changestatus']    = 'Change Status';
$string['changepriority']  = 'Change Priority';
$string['assignticket']    = 'Assign to';
$string['save']            = 'Save';
$string['updatedsuccess']  = 'Ticket updated.';

// Notifications.
$string['messageprovider:ticket_notification'] = 'Help desk ticket notifications';
$string['notify_new_subject']   = 'New support ticket #{$a->id}: {$a->subject}';
$string['notify_new_body']      = "A new support ticket has been submitted.\n\nTicket: #{$a->id}\nType: {$a->type}\nFrom: {$a->username}\nPage: {$a->pageurl}\n\nSubject: {$a->subject}\n\n{$a->description}\n\nView: {$a->url}";
$string['notify_reply_subject'] = 'Reply on your ticket #{$a->id}: {$a->subject}';
$string['notify_reply_body']    = "A staff member has replied to your support ticket #{$a->id}.\n\nSubject: {$a->subject}\n\n---\n{$a->reply}\n---\n\nView your ticket: {$a->url}";
$string['notify_userreply_subject'] = 'New reply from {$a->username} on ticket #{$a->id}: {$a->subject}';
$string['notify_userreply_body']    = "{$a->username} replied to support ticket #{$a->id}.\n\nSubject: {$a->subject}\n\n---\n{$a->reply}\n---\n\nView: {$a->url}";
$string['notify_assigned_subject'] = 'Ticket #{$a->id} assigned to you: {$a->subject}';
$string['notify_assigned_body']    = "Support ticket #{$a->id} has been assigned to you.\n\nSubject: {$a->subject}\nFrom: {$a->username}\n\nView: {$a->url}";
