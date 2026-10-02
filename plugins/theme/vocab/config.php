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
 * theme_vocab config: a Boost child theme that keeps Boost's look and settings.
 *
 * @package    theme_vocab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Start from Boost's full configuration (layouts, renderers, navigation options) so the site behaves exactly like Boost.
require($CFG->dirroot . '/theme/boost/config.php');
require_once(__DIR__ . '/lib.php');

$THEME->name = 'vocab';
$THEME->parents = ['boost'];
// Empty, so Boost's editor styles are used.
$THEME->editor_scss = [];
$THEME->scss = function($theme) {
    return theme_vocab_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_vocab_get_pre_scss';
$THEME->extrascsscallback = 'theme_vocab_get_extra_scss';
