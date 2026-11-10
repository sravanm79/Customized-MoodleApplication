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

// Plugin.
$string['pluginname'] = 'Deadline Notifier';

// Settings.
$string['enabled']              = 'Enable deadline notifications';
$string['enabled_desc']         = 'Send reminders to students before assignment due dates, quiz closes, and live classes (Zoom and Google Meet).';
$string['lead_times_heading']   = 'Default reminder lead times';
$string['lead_times_heading_desc'] = 'Site defaults. Teachers can set their own per course (course > More > Reminder settings).';
$string['assign_lead_times']    = 'Assignment reminder lead times (minutes)';
$string['assign_lead_times_desc'] = 'Comma-separated minutes before the due date to send reminders. E.g. "1440,60" sends reminders 24 h and 1 h before.';
$string['live_lead_times']      = 'Live class reminder lead times (minutes)';
$string['live_lead_times_desc'] = 'Minutes before a Zoom or Google Meet class starts (every occurrence of recurring classes). E.g. "30,15".';
$string['quiz_lead_times']      = 'Quiz reminder lead times (minutes)';
$string['quiz_lead_times_desc'] = 'Minutes before the quiz closes to send reminders. E.g. "1440,60".';
$string['gcal_client_id']       = 'Google OAuth Client ID';
$string['gcal_client_id_desc']  = 'From Google Cloud Console → APIs & Services → Credentials.';
$string['gcal_client_secret']   = 'Google OAuth Client Secret';
$string['gcal_client_secret_desc'] = 'From Google Cloud Console → APIs & Services → Credentials.';
$string['gcal_heading_desc']    = 'Once the client ID is set, every user finds "Google Calendar Sync" under their Preferences (user menu > Preferences). Google requires the site to have a domain name for the redirect URI.';

// Course reminder settings.
$string['notifier:managecourse']       = 'Set reminder lead times for a course';
$string['coursereminders']             = 'Reminder settings';
$string['coursereminders_intro']       = 'When your students are reminded about this course\'s deadlines and live classes. Enter minutes before the deadline or class, comma-separated (1440 = 24 hours, 60 = 1 hour). Leave empty to use the site default; enter "off" for no reminders.';
$string['coursereminders_sitedefault'] = 'Site default: {$a}';
$string['invalidleads']                = 'Use minutes separated by commas (e.g. 1440,60,15), "off", or leave empty.';
$string['leadtoolong']                 = 'Lead times can be at most 20160 minutes (14 days).';
$string['disabledsite']                = 'Reminders are switched off for the whole site, so these settings have no effect until an administrator turns them on.';

// Message provider.
$string['messageprovider:deadline_reminder'] = 'Deadline and meeting reminders';

// Notification subjects and bodies.
$string['reminder_assign_subject'] = 'Reminder: {$a->name} due in {$a->lead}';
$string['reminder_assign_body']    = 'This is a reminder that the assignment "{$a->name}" in {$a->course} is due in {$a->lead}.

Due: {$a->duedate}

Open assignment: {$a->url}';

$string['reminder_zoom_subject'] = 'Reminder: {$a->name} starts in {$a->lead}';
$string['reminder_zoom_body']    = 'Your Zoom meeting "{$a->name}" in {$a->course} starts in {$a->lead}.

Start time: {$a->starttime}
Join: {$a->url}';

$string['reminder_meet_subject'] = 'Reminder: {$a->name} starts in {$a->lead}';
$string['reminder_meet_body']    = 'Your Google Meet class "{$a->name}" in {$a->course} starts in {$a->lead}.

Start time: {$a->starttime}
Join: {$a->url}';

$string['reminder_quiz_subject'] = 'Reminder: {$a->name} closes in {$a->lead}';
$string['reminder_quiz_body']    = 'The quiz "{$a->name}" in {$a->course} closes in {$a->lead}.

Closes: {$a->closedate}

Open quiz: {$a->url}';

// Google Calendar connect page.
$string['gcalheading']        = 'Google Calendar Sync';
$string['gcalconnected']      = 'Your Google Calendar is connected. Upcoming deadlines and meetings are synced automatically.';
$string['gcalnotconnected']   = 'Connect your Google Calendar to have deadlines and live classes added automatically.';
$string['connectgcal']        = 'Connect Google Calendar';
$string['disconnectgcal']     = 'Disconnect Google Calendar';
$string['gcalconnectedok']    = 'Google Calendar connected successfully.';
$string['gcaldisconnected']   = 'Google Calendar disconnected.';
$string['gcalerror']          = 'Could not connect to Google Calendar: {$a}';
$string['gcaloauthdenied']    = 'Google Calendar access was denied.';
$string['gcalredirecturi']    = 'Authorised redirect URI (add this in Google Cloud Console)';

// Scheduled task.
$string['task_sendnotifications'] = 'Send deadline and meeting notifications';
