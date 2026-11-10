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
 * Site settings for quizaccess_examproctor (webcam video recording).
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('quizaccess_examproctor/videoheading',
        get_string('videoheading', 'quizaccess_examproctor'), get_string('videoheading_desc', 'quizaccess_examproctor')));
    $settings->add(new admin_setting_configtext('quizaccess_examproctor/videobitrate',
        get_string('videobitrate', 'quizaccess_examproctor'), get_string('videobitrate_desc', 'quizaccess_examproctor'),
        150, PARAM_INT));
    $settings->add(new admin_setting_configtext('quizaccess_examproctor/videoretentiondays',
        get_string('videoretentiondays', 'quizaccess_examproctor'),
        get_string('videoretentiondays_desc', 'quizaccess_examproctor'), 30, PARAM_INT));
}
