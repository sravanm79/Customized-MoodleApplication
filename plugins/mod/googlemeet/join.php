<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * One-click join (IIIT Dharwad addition): checks the user may open the activity, records the view (attendance in the
 * logs and completion), then sends the browser straight to the Google Meet room. Used by the calendar's "Join Google
 * Meet" action and the dashboards.
 *
 * Google Meet itself decides who is let in: the organiser and people signed in with an allowed Google account join
 * directly, others "ask to join". Moodle cannot sign anyone in to Google.
 *
 * @package     mod_googlemeet
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'googlemeet');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/googlemeet:view', $context);

$googlemeet = $DB->get_record('googlemeet', ['id' => $cm->instance], '*', MUST_EXIST);
$url = trim((string) $googlemeet->url);
if (!preg_match('~^https://meet\.google\.com/[a-z0-9-]+$~i', $url)) {
    // No valid room yet: show the activity page, which explains it.
    redirect(new moodle_url('/mod/googlemeet/view.php', ['id' => $cm->id]));
}

googlemeet_view($googlemeet, $course, $cm, $context);
redirect($url);
