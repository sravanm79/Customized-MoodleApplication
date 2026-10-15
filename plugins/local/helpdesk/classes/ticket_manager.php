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

namespace local_helpdesk;

defined('MOODLE_INTERNAL') || die();

/**
 * Ticket CRUD, notifications, and status helpers.
 *
 * @package   local_helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ticket_manager {

    const STATUSES   = ['open', 'assigned', 'in_progress', 'resolved', 'closed'];
    const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    const TYPES      = ['bug', 'feature', 'other'];

    // -------------------------------------------------------------------------
    // Ticket creation
    // -------------------------------------------------------------------------

    /**
     * Creates a new ticket and notifies all site admins.
     *
     * @param int    $userid
     * @param string $type        'bug'|'feature'|'other'
     * @param string $subject
     * @param string $description  Raw (stored as-is; escaped on output).
     * @param string $pageurl
     * @param string $userroles   Comma-separated shortnames.
     * @param string $useragent
     * @return int  New ticket ID.
     */
    public static function create(
        int $userid,
        string $type,
        string $subject,
        string $description,
        string $pageurl = '',
        string $userroles = '',
        string $useragent = ''
    ): int {
        global $DB;
        $now = time();
        $type = in_array($type, self::TYPES) ? $type : 'other';
        $id   = $DB->insert_record('local_helpdesk_ticket', (object) [
            'userid'       => $userid,
            'subject'      => core_text::substr($subject, 0, 255),
            'description'  => $description,
            'type'         => $type,
            'status'       => 'open',
            'priority'     => 'normal',
            'assigneeid'   => null,
            'pageurl'      => core_text::substr($pageurl, 0, 1333),
            'userroles'    => core_text::substr($userroles, 0, 255),
            'useragent'    => core_text::substr($useragent, 0, 1333),
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        self::notify_admins_new_ticket($id);
        return $id;
    }

    // -------------------------------------------------------------------------
    // Updates
    // -------------------------------------------------------------------------

    public static function update_status(int $ticketid, string $status): void {
        global $DB;
        if (!in_array($status, self::STATUSES)) {
            return;
        }
        $DB->set_field('local_helpdesk_ticket', 'status',       $status, ['id' => $ticketid]);
        $DB->set_field('local_helpdesk_ticket', 'timemodified', time(),  ['id' => $ticketid]);
    }

    public static function update_priority(int $ticketid, string $priority): void {
        global $DB;
        if (!in_array($priority, self::PRIORITIES)) {
            return;
        }
        $DB->set_field('local_helpdesk_ticket', 'priority',     $priority, ['id' => $ticketid]);
        $DB->set_field('local_helpdesk_ticket', 'timemodified', time(),    ['id' => $ticketid]);
    }

    public static function assign(int $ticketid, int $assigneeid): void {
        global $DB;
        $DB->set_field('local_helpdesk_ticket', 'assigneeid',   $assigneeid, ['id' => $ticketid]);
        $DB->set_field('local_helpdesk_ticket', 'status',       'assigned',  ['id' => $ticketid]);
        $DB->set_field('local_helpdesk_ticket', 'timemodified', time(),      ['id' => $ticketid]);
        // Notify assignee.
        $ticket = $DB->get_record('local_helpdesk_ticket', ['id' => $ticketid]);
        if ($ticket) {
            self::notify_assigned($ticket, $assigneeid);
        }
    }

    // -------------------------------------------------------------------------
    // Replies
    // -------------------------------------------------------------------------

    /**
     * Adds a reply to a ticket, then notifies the submitter (unless private).
     *
     * @param int    $ticketid
     * @param int    $userid     The staff member replying.
     * @param string $message
     * @param bool   $isprivate  True = internal note, not shown to the submitter.
     * @return int  New reply ID.
     */
    public static function add_reply(int $ticketid, int $userid, string $message, bool $isprivate = false): int {
        global $DB;
        $id = $DB->insert_record('local_helpdesk_reply', (object) [
            'ticketid'    => $ticketid,
            'userid'      => $userid,
            'message'     => $message,
            'isprivate'   => $isprivate ? 1 : 0,
            'timecreated' => time(),
        ]);
        $DB->set_field('local_helpdesk_ticket', 'timemodified', time(), ['id' => $ticketid]);

        if (!$isprivate) {
            $ticket = $DB->get_record('local_helpdesk_ticket', ['id' => $ticketid]);
            if ($ticket) {
                self::notify_reply($ticket, $message);
            }
        }
        return $id;
    }

    // -------------------------------------------------------------------------
    // Display helpers
    // -------------------------------------------------------------------------

    public static function status_badge(string $status): string {
        $classes = [
            'open'        => 'bg-primary',
            'assigned'    => 'bg-info text-dark',
            'in_progress' => 'bg-warning text-dark',
            'resolved'    => 'bg-success',
            'closed'      => 'bg-secondary',
        ];
        $label = get_string('status_' . $status, 'local_helpdesk');
        return \html_writer::span($label, 'badge ' . ($classes[$status] ?? 'bg-secondary'));
    }

    public static function priority_badge(string $priority): string {
        $classes = [
            'low'    => 'bg-secondary',
            'normal' => 'bg-info text-dark',
            'high'   => 'bg-warning text-dark',
            'urgent' => 'bg-danger',
        ];
        $label = get_string('priority_' . $priority, 'local_helpdesk');
        return \html_writer::span($label, 'badge ' . ($classes[$priority] ?? 'bg-secondary'));
    }

    public static function type_badge(string $type): string {
        $icons = ['bug' => '🐛', 'feature' => '✨', 'other' => '❓'];
        $label = get_string('type_' . $type, 'local_helpdesk');
        return ($icons[$type] ?? '') . ' ' . $label;
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    private static function notify_admins_new_ticket(int $ticketid): void {
        global $DB;
        $ticket = $DB->get_record('local_helpdesk_ticket', ['id' => $ticketid]);
        if (!$ticket) {
            return;
        }
        $submitter = core_user::get_user($ticket->userid);
        $url       = new \moodle_url('/local/helpdesk/ticket.php', ['id' => $ticketid]);
        $a = (object) [
            'id'          => $ticketid,
            'subject'     => $ticket->subject,
            'type'        => get_string('type_' . $ticket->type, 'local_helpdesk'),
            'username'    => $submitter ? fullname($submitter) : '?',
            'pageurl'     => $ticket->pageurl ?: '—',
            'description' => $ticket->description,
            'url'         => $url->out(false),
        ];
        foreach (get_admins() as $admin) {
            self::send_notification(
                $admin->id,
                get_string('notify_new_subject', 'local_helpdesk', $a),
                get_string('notify_new_body',    'local_helpdesk', $a),
                $url
            );
        }
    }

    private static function notify_reply(\stdClass $ticket, string $reply): void {
        $url = new \moodle_url('/local/helpdesk/ticket.php', ['id' => $ticket->id]);
        $a   = (object) [
            'id'      => $ticket->id,
            'subject' => $ticket->subject,
            'reply'   => $reply,
            'url'     => $url->out(false),
        ];
        self::send_notification(
            (int) $ticket->userid,
            get_string('notify_reply_subject', 'local_helpdesk', $a),
            get_string('notify_reply_body',    'local_helpdesk', $a),
            $url
        );
    }

    private static function notify_assigned(\stdClass $ticket, int $assigneeid): void {
        $submitter = \core_user::get_user($ticket->userid);
        $url       = new \moodle_url('/local/helpdesk/ticket.php', ['id' => $ticket->id]);
        $a         = (object) [
            'id'       => $ticket->id,
            'subject'  => $ticket->subject,
            'username' => $submitter ? fullname($submitter) : '?',
            'url'      => $url->out(false),
        ];
        self::send_notification(
            $assigneeid,
            get_string('notify_assigned_subject', 'local_helpdesk', $a),
            get_string('notify_assigned_body',    'local_helpdesk', $a),
            $url
        );
    }

    private static function send_notification(int $userid, string $subject, string $body, \moodle_url $url): void {
        $msg = new \core\message\message();
        $msg->component         = 'local_helpdesk';
        $msg->name              = 'ticket_notification';
        $msg->userfrom          = \core_user::get_noreply_user();
        $msg->userto            = $userid;
        $msg->subject           = $subject;
        $msg->fullmessage       = $body;
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = text_to_html($body, false, false, true);
        $msg->smallmessage      = $subject;
        $msg->notification      = 1;
        $msg->contexturl        = $url->out(false);
        $msg->contexturlname    = $subject;
        message_send($msg);
    }
}
