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
 * Day/Night switch: flips <html data-theme> between "dark" and "light" and remembers the choice:
 * - logged-in users: the theme_iiitdwd_colormode user preference, so it follows them across sessions and devices;
 * - this browser: a cookie of the same name, which the server renders from when there is no saved preference
 *   (logged out, the login page), and localStorage, which keeps other open tabs in step.
 * The server writes the mode into the page, so pages never flash the other one.
 *
 * Written as plain AMD so the same file works as src and build without grunt.
 *
 * @module     theme_iiitdwd/theme_toggle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/repository', 'core/notification', 'core/config'], function(Repository, Notification, Config) {
    'use strict';

    var PREFERENCE = 'theme_iiitdwd_colormode';
    var SELECTOR = '[data-action="iiitdwd-toggle-color-mode"]';
    /** How long colours animate when switching (matches .iiitdwd-color-mode-switching in shell.scss). */
    var TRANSITION_MS = 250;
    /** Cookie lifetime: one year. */
    var COOKIE_MAX_AGE = 365 * 24 * 60 * 60;

    var root = document.documentElement;
    var initialised = false;
    var transitionTimer = null;

    var isMode = function(mode) {
        return mode === 'dark' || mode === 'light';
    };

    var current = function() {
        return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    };

    var remember = function(mode) {
        // Scoped to the site path, so other sites on the same host are not affected.
        var url = new URL(Config.wwwroot);
        document.cookie = PREFERENCE + '=' + mode + '; path=' + (url.pathname || '/') + '; max-age=' + COOKIE_MAX_AGE +
            '; SameSite=Lax' + (url.protocol === 'https:' ? '; Secure' : '');
        try {
            window.localStorage.setItem(PREFERENCE, mode);
        } catch (e) {
            // Storage disabled (private mode, blocked site data): the cookie and preference still work.
        }
    };

    var updateButtons = function(mode) {
        document.querySelectorAll(SELECTOR).forEach(function(button) {
            button.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
            button.title = mode === 'dark' ? button.dataset.titleDay : button.dataset.titleNight;
        });
    };

    var apply = function(mode, animate) {
        if (animate) {
            // Briefly animate colours so the switch is a fade, not a flash.
            root.classList.add('iiitdwd-color-mode-switching');
            clearTimeout(transitionTimer);
            transitionTimer = setTimeout(function() {
                root.classList.remove('iiitdwd-color-mode-switching');
            }, TRANSITION_MS);
        }
        root.setAttribute('data-theme', mode);
        updateButtons(mode);
    };

    return {
        /**
         * @param {Object} config
         * @param {boolean} config.persist Save to the user preference (logged-in users).
         */
        init: function(config) {
            if (initialised) {
                return;
            }
            initialised = true;

            // Keep this browser's copies in line with the mode the page was rendered in (the saved preference wins).
            remember(current());
            updateButtons(current());

            document.addEventListener('click', function(e) {
                if (!e.target.closest(SELECTOR)) {
                    return;
                }
                var mode = current() === 'dark' ? 'light' : 'dark';
                apply(mode, true);
                remember(mode);
                if (config.persist) {
                    Repository.setUserPreference(PREFERENCE, mode).catch(Notification.exception);
                }
            });

            // Other tabs of this site follow the switch.
            window.addEventListener('storage', function(e) {
                if (e.key === PREFERENCE && isMode(e.newValue) && e.newValue !== current()) {
                    apply(e.newValue, true);
                }
            });
        },
    };
});
