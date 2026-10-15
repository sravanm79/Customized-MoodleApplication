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
 * Google OAuth callback.
 *
 * Google redirects here after the user authorises (or denies) access.
 * On success: exchange the code for tokens, save them, redirect to connect page.
 * On error/denial: redirect with an error message.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_notifier\gcal;
use core\output\notification;

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/notifier/gcal_callback.php'));

$connecturl = new moodle_url('/local/notifier/gcal_connect.php');

// User denied access.
if (optional_param('error', '', PARAM_ALPHANUMEXT) !== '') {
    redirect($connecturl, get_string('gcaloauthdenied', 'local_notifier'), null, notification::NOTIFY_WARNING);
}

$code  = required_param('code',  PARAM_RAW);
$state = optional_param('state', '', PARAM_ALPHANUMEXT);

// CSRF check: compare state with what we stored in the session.
if (empty($SESSION->local_notifier_gcal_state) || $state !== $SESSION->local_notifier_gcal_state) {
    redirect($connecturl, get_string('invalidsesskey', 'error'), null, notification::NOTIFY_ERROR);
}
unset($SESSION->local_notifier_gcal_state);

try {
    $tokendata = gcal::exchange_code($code);
    gcal::save_token((int) $USER->id, $tokendata);
    redirect($connecturl, get_string('gcalconnectedok', 'local_notifier'), null, notification::NOTIFY_SUCCESS);
} catch (\moodle_exception $e) {
    redirect($connecturl, $e->getMessage(), null, notification::NOTIFY_ERROR);
}
