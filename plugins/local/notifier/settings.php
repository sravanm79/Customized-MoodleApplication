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
 * Admin settings for local_notifier.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_notifier', get_string('pluginname', 'local_notifier'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_notifier/enabled',
            get_string('enabled', 'local_notifier'),
            get_string('enabled_desc', 'local_notifier'),
            1
        ));

        $settings->add(new admin_setting_heading('local_notifier/lead_times_heading',
            get_string('lead_times_heading', 'local_notifier'), get_string('lead_times_heading_desc', 'local_notifier')));

        $settings->add(new admin_setting_configtext(
            'local_notifier/assign_lead_times',
            get_string('assign_lead_times', 'local_notifier'),
            get_string('assign_lead_times_desc', 'local_notifier'),
            '1440,60',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'local_notifier/live_lead_times',
            get_string('live_lead_times', 'local_notifier'),
            get_string('live_lead_times_desc', 'local_notifier'),
            '30,15',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'local_notifier/quiz_lead_times',
            get_string('quiz_lead_times', 'local_notifier'),
            get_string('quiz_lead_times_desc', 'local_notifier'),
            '1440,60',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_heading('local_notifier/gcal_heading',
            get_string('gcalheading', 'local_notifier'), get_string('gcal_heading_desc', 'local_notifier')));

        $settings->add(new admin_setting_configtext(
            'local_notifier/gcal_client_id',
            get_string('gcal_client_id', 'local_notifier'),
            get_string('gcal_client_id_desc', 'local_notifier'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configpasswordunmask(
            'local_notifier/gcal_client_secret',
            get_string('gcal_client_secret', 'local_notifier'),
            get_string('gcal_client_secret_desc', 'local_notifier'),
            ''
        ));

        // Show the redirect URI admins need to register in Google Cloud Console.
        global $CFG;
        $redirecturi = (isset($CFG->wwwroot) ? $CFG->wwwroot : '') . '/local/notifier/gcal_callback.php';
        $settings->add(new admin_setting_description(
            'local_notifier/gcal_redirect_uri_info',
            get_string('gcalredirecturi', 'local_notifier'),
            html_writer::tag('code', s($redirecturi))
        ));
    }
}
