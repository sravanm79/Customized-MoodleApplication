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
 * theme_vocab callbacks. Styling settings are read from theme_boost, so the
 * existing Boost settings page (brand colour, preset, raw SCSS) remains the single place to edit them.
 *
 * @package    theme_vocab
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Boost's theme config, whose settings this theme reuses.
 *
 * @return theme_config
 */
function theme_vocab_boost_config() {
    return theme_config::load('boost');
}

/**
 * Main SCSS: Boost's selected preset.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_vocab_get_main_scss_content($theme) {
    return theme_boost_get_main_scss_content(theme_vocab_boost_config());
}

/**
 * Pre SCSS: this theme's variables, then Boost's brand colour and raw initial SCSS.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_vocab_get_pre_scss($theme) {
    return file_get_contents(__DIR__ . '/scss/pre.scss') . "\n" . theme_boost_get_pre_scss(theme_vocab_boost_config());
}

/**
 * Extra SCSS: Boost's raw SCSS and background images, then this theme's own styles.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_vocab_get_extra_scss($theme) {
    return theme_boost_get_extra_scss(theme_vocab_boost_config()) . "\n" . file_get_contents(__DIR__ . '/scss/vocab.scss');
}
