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
 * Admin menu: Site administration › Users › Accounts › Register students, All students, Send announcement.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('accounts', new admin_externalpage('local_studentportal_register',
    new lang_string('registerstudents', 'local_studentportal'),
    new moodle_url('/local/studentportal/register.php'), 'local/studentportal:register'));
$ADMIN->add('accounts', new admin_externalpage('local_studentportal_allstudents',
    new lang_string('allstudents', 'local_studentportal'),
    new moodle_url('/local/studentportal/allstudents.php'), 'local/studentportal:register'));
$ADMIN->add('accounts', new admin_externalpage('local_studentportal_announce',
    new lang_string('sendannouncement', 'local_studentportal'),
    new moodle_url('/local/studentportal/announce.php'), 'local/studentportal:announcesite'));
