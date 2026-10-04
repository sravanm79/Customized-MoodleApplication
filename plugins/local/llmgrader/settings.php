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
 * Admin settings for local_llmgrader.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_llmgrader', get_string('pluginname', 'local_llmgrader'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox('local_llmgrader/enabled',
            get_string('enabled', 'local_llmgrader'), get_string('enabled_desc', 'local_llmgrader'), 1));
        $settings->add(new admin_setting_configcheckbox('local_llmgrader/requirereview',
            get_string('requirereviewdefault', 'local_llmgrader'), get_string('requirereviewdefault_desc', 'local_llmgrader'), 1));
        $providers = [];
        foreach (\local_llmgrader\provider\factory::available() as $name => $class) {
            $providers[$name] = $class::get_name();
        }
        $settings->add(new admin_setting_configselect('local_llmgrader/provider',
            get_string('provider', 'local_llmgrader'), get_string('provider_desc', 'local_llmgrader'),
            'openai_compatible', $providers));
        $settings->add(new admin_setting_configcheckbox('local_llmgrader/stream',
            get_string('stream', 'local_llmgrader'), get_string('stream_desc', 'local_llmgrader'), 0));
        $settings->add(new admin_setting_configtext('local_llmgrader/llmurl',
            get_string('llmurl', 'local_llmgrader'), get_string('llmurl_desc', 'local_llmgrader'),
            'http://45.194.46.66:9010/v1', PARAM_URL));
        $settings->add(new admin_setting_configtext('local_llmgrader/model',
            get_string('model', 'local_llmgrader'), get_string('model_desc', 'local_llmgrader'), 'surya2', PARAM_TEXT));
        $settings->add(new admin_setting_configpasswordunmask('local_llmgrader/apikey',
            get_string('apikey', 'local_llmgrader'), get_string('apikey_desc', 'local_llmgrader'), ''));
        $settings->add(new admin_setting_configtext('local_llmgrader/timeout',
            get_string('timeout', 'local_llmgrader'), get_string('timeout_desc', 'local_llmgrader'), 180, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_llmgrader/maxchars',
            get_string('maxchars', 'local_llmgrader'), get_string('maxchars_desc', 'local_llmgrader'), 16000, PARAM_INT));
        $settings->add(new admin_setting_configtextarea('local_llmgrader/systemprompt',
            get_string('systemprompt', 'local_llmgrader'), get_string('systemprompt_desc', 'local_llmgrader'),
            \local_llmgrader\prompt_builder::DEFAULT_PROMPT, PARAM_RAW, 80, 20));
    }
}
