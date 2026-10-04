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
 * theme_iiitdwd SCSS callbacks.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Main SCSS: the design-token preset, which also imports FontAwesome, Bootstrap and Moodle core from Boost.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_iiitdwd_get_main_scss_content($theme) {
    return file_get_contents(__DIR__ . '/scss/preset/default.scss');
}

/**
 * Pre SCSS: nothing yet. All variables live in the preset so there is one source of tokens.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_iiitdwd_get_pre_scss($theme) {
    return '';
}

/**
 * Extra SCSS: the dark-mode overrides, compiled after Bootstrap and Moodle core.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_iiitdwd_get_extra_scss($theme) {
    return file_get_contents(__DIR__ . '/scss/styles.scss');
}

/**
 * Serves the theme's setting files (the institution logo).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_iiitdwd_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'institutionlogo') {
        $theme = theme_config::load('iiitdwd');
        // Theme files are public and cacheable by browsers and proxies (the login page has no session).
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * User preferences this theme lets users set through the core_user preferences API (amd/src/theme_toggle.js).
 *
 * @return array[]
 */
function theme_iiitdwd_user_preferences(): array {
    return [
        \theme_iiitdwd\local\color_mode::PREFERENCE => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => \theme_iiitdwd\local\color_mode::DARK,
            'choices' => [\theme_iiitdwd\local\color_mode::DARK, \theme_iiitdwd\local\color_mode::LIGHT],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * CSS post-processing for the Day/Night modes.
 *
 * Bootstrap and Moodle core use the compile-time palette ($body-bg, $border-color, ...) directly in hundreds of
 * rules, so the compiled CSS holds the Night colours as fixed values. This swaps those exact values for the runtime
 * tokens (color_modes.scss), so every rule follows <html data-theme>. Custom property definitions (the tokens
 * themselves) and rules for dark things (.bg-dark, .btn-dark, [data-theme="dark"], ...) are left alone.
 *
 * @param string $css
 * @param theme_config $theme
 * @return string
 */
function theme_iiitdwd_css_postprocess($css, $theme) {
    // Night palette (scss/tokens.scss, preset links) => runtime token.
    $tokens = [
        '#050c18' => 'var(--iiitdwd-bg)',
        '#0c182b' => 'var(--iiitdwd-surface)',
        '#031633' => 'var(--iiitdwd-sidebar)',
        '#132742' => 'var(--iiitdwd-border)',
        '#8fa0b5' => 'var(--iiitdwd-text-secondary)',
        '#5d718a' => 'var(--iiitdwd-text-muted)',
        '#5e9fe0' => 'var(--iiitdwd-link)',
        '#8cbbe9' => 'var(--iiitdwd-link)',
    ];
    $pattern = '/(' . implode('|', array_map(fn($hex) => preg_quote($hex, '/'), array_keys($tokens))) . ')(?![0-9a-f])/i';

    // Innermost rule blocks: "selector{declarations}".
    return preg_replace_callback('/([^{}]*)\{([^{}]*)\}/', function(array $m) use ($tokens, $pattern): string {
        [, $selector, $body] = $m;
        if (stripos($selector, 'dark') !== false || stripos($body, '#') === false) {
            return $m[0];
        }
        $declarations = array_map(function(string $declaration) use ($tokens, $pattern): string {
            if (str_starts_with(ltrim($declaration), '--')) {
                return $declaration;
            }
            return preg_replace_callback($pattern, fn($hex) => $tokens[strtolower($hex[1])], $declaration);
        }, explode(';', $body));
        return $selector . '{' . implode(';', $declarations) . '}';
    }, $css);
}
