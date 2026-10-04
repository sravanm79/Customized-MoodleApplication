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
 * theme_iiitdwd config: a Boost child theme with the IIIT Dharwad dark design system.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Start from Boost's full configuration (all layouts, renderers, course index, edit switch),
// then replace the styling and the main content layouts below.
require($CFG->dirroot . '/theme/boost/config.php');
require_once(__DIR__ . '/lib.php');

$THEME->name = 'iiitdwd';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->editor_scss = ['editor'];

// SCSS comes only from this theme (scss/preset/default.scss + scss/styles.scss), not from Boost's settings.
$THEME->scss = function($theme) {
    return theme_iiitdwd_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_iiitdwd_get_pre_scss';
$THEME->extrascsscallback = 'theme_iiitdwd_get_extra_scss';
// Day/Night modes: compiled palette colours become runtime tokens (see theme_iiitdwd_css_postprocess()).
$THEME->csspostprocess = 'theme_iiitdwd_css_postprocess';

// Main content layouts. Since Moodle 4.0, Boost's drawers.php replaces columns2.php: it renders
// the same "content + side-pre blocks" page, with the course index and block drawers.
// Layout files are looked up in this theme first, then in Boost, so a layout/drawers.php or
// layout/columns2.php added here later overrides Boost's without changing this config.
$THEME->layouts['standard'] = [
    'file' => 'drawers.php',
    'regions' => ['side-pre'],
    'defaultregion' => 'side-pre',
];
$THEME->layouts['frontpage'] = [
    'file' => 'drawers.php',
    'regions' => ['side-pre'],
    'defaultregion' => 'side-pre',
    'options' => ['nonavbar' => true],
];
$THEME->layouts['incourse'] = [
    'file' => 'drawers.php',
    'regions' => ['side-pre'],
    'defaultregion' => 'side-pre',
];
// Course pages use the same two-column (columns2) structure as incourse.
$THEME->layouts['course'] = [
    'file' => 'drawers.php',
    'regions' => ['side-pre'],
    'defaultregion' => 'side-pre',
    'options' => ['langmenu' => true],
];
