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
 * theme_iiitdwd settings: the institution logo (sidebar and login page) and the login page's support contact.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Moodle 5 creates every theme's settings page hidden (admin/settings/appearance.php) and only lists it under
// Appearance › Themes when the theme replaces it, as Boost and Classic do. Without this the page is unreachable.
$settings = new admin_settingpage('themesettingiiitdwd', new lang_string('configtitle', 'theme_iiitdwd'));

if ($ADMIN->fulltree) {
    $setting = new admin_setting_configstoredfile('theme_iiitdwd/institutionlogo',
        new lang_string('institutionlogo', 'theme_iiitdwd'),
        new lang_string('institutionlogo_desc', 'theme_iiitdwd'),
        'institutionlogo', 0, ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.webp']]);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    $setting = new admin_setting_configstoredfile('theme_iiitdwd/loginbackground',
        new lang_string('loginbackground', 'theme_iiitdwd'),
        new lang_string('loginbackground_desc', 'theme_iiitdwd'),
        'loginbackground', 0, ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.webp']]);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    $settings->add(new admin_setting_configtext('theme_iiitdwd/loginsupportemail',
        new lang_string('loginsupportemail', 'theme_iiitdwd'),
        new lang_string('loginsupportemail_desc', 'theme_iiitdwd'),
        \theme_iiitdwd\output\core_renderer::LOGIN_SUPPORT_EMAIL, PARAM_EMAIL));
}
