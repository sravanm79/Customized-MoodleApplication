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
 * Google Calendar connection page.
 *
 * Shows connection status and a Connect / Disconnect button.
 * The "disconnect" action is handled here; "connect" redirects to Google OAuth.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_notifier\gcal;
use core\output\notification;

require_login();
$context = context_system::instance();

$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/notifier/gcal_connect.php'));
$PAGE->set_title(get_string('gcalheading', 'local_notifier'));
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->set_pagelayout('standard');

// Handle disconnect.
if ($action === 'disconnect' && confirm_sesskey()) {
    gcal::revoke((int) $USER->id);
    redirect($PAGE->url, get_string('gcaldisconnected', 'local_notifier'), null, notification::NOTIFY_SUCCESS);
}

// Handle connect: generate state token, store in session, redirect to Google.
if ($action === 'connect' && confirm_sesskey()) {
    $state = random_string(32);
    $SESSION->local_notifier_gcal_state = $state;
    redirect(gcal::get_auth_url($state));
}

// Render the page.
$clientid = get_config('local_notifier', 'gcal_client_id');
$connected = gcal::is_connected((int) $USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gcalheading', 'local_notifier'));

if (empty($clientid)) {
    echo $OUTPUT->notification(
        'Google Calendar sync is not configured. Ask your site admin to add the OAuth credentials in ' .
        html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'local_notifier']), 'plugin settings') . '.',
        'warning'
    );
    echo $OUTPUT->footer();
    exit;
}

if ($connected) {
    echo $OUTPUT->notification(get_string('gcalconnected', 'local_notifier'), 'success');
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'disconnect', 'sesskey' => sesskey()]),
        get_string('disconnectgcal', 'local_notifier'),
        'post',
        ['type' => 'danger']
    );
} else {
    echo html_writer::tag('p', get_string('gcalnotconnected', 'local_notifier'));
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'connect', 'sesskey' => sesskey()]),
        get_string('connectgcal', 'local_notifier'),
        'post',
        ['type' => 'primary']
    );
}

echo $OUTPUT->footer();
