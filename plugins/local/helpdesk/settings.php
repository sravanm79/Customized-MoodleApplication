<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_helpdesk', get_string('pluginname', 'local_helpdesk'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_helpdesk/enabled',
            get_string('enabled', 'local_helpdesk'),
            get_string('enabled_desc', 'local_helpdesk'),
            1
        ));

        // Link to the dashboard.
        global $CFG;
        $dashurl = isset($CFG->wwwroot)
            ? html_writer::link(new moodle_url('/local/helpdesk/dashboard.php'),
                get_string('dashboard', 'local_helpdesk'))
            : '';
        if ($dashurl) {
            $settings->add(new admin_setting_description(
                'local_helpdesk/dashboard_link',
                '',
                $dashurl
            ));
        }
    }
}
