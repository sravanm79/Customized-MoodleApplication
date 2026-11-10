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
 * AJAX endpoint: create a new support ticket.
 *
 * Accepts POST with sesskey, returns JSON {success, ticketid, message}.
 *
 * @package   local_helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require('../../config.php');

use local_helpdesk\ticket_manager;

require_login(null, false);
require_sesskey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$type        = required_param('type',        PARAM_ALPHA);
$subject     = required_param('subject',     PARAM_TEXT);
$description = required_param('description', PARAM_RAW);
$pageurl     = optional_param('pageurl',     '', PARAM_URL);
$userroles   = optional_param('userroles',   '', PARAM_TEXT);
$useragent   = core_text::substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1333);

// Basic validation.
if (clean_text(trim($subject)) === '' || clean_text(trim($description)) === '') {
    echo json_encode(['success' => false, 'message' => get_string('error_msg', 'local_helpdesk')]);
    exit;
}

try {
    $ticketid = ticket_manager::create(
        (int) $USER->id,
        $type,
        clean_text(trim($subject)),
        clean_text(trim($description)),
        $pageurl,
        $userroles,
        $useragent
    );
    echo json_encode([
        'success'  => true,
        'ticketid' => $ticketid,
        'message'  => get_string('success_msg', 'local_helpdesk', $ticketid),
        'url'      => (new moodle_url('/local/helpdesk/ticket.php', ['id' => $ticketid]))->out(false),
        'linktext' => get_string('viewticket', 'local_helpdesk'),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => get_string('error_msg', 'local_helpdesk')]);
}
