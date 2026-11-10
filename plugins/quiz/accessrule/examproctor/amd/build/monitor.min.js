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
 * Live exam monitor: reloads the monitor content every few seconds (paused while the tab is hidden).
 *
 * Written as plain ES5 AMD so the same file works as src and build without grunt.
 *
 * @module     quizaccess_examproctor/monitor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        init: function(cfg) {
            var content = document.querySelector('[data-region="monitor-content"]');
            var updated = document.querySelector('[data-region="updated"]');
            var dot = document.querySelector('[data-region="live-dot"]');
            var toggle = document.querySelector('[data-action="toggle-refresh"]');
            var paused = false;
            var timer = null;

            var schedule = function() {
                clearTimeout(timer);
                timer = setTimeout(refresh, cfg.interval);
            };

            var refresh = function() {
                if (paused || document.hidden) {
                    schedule();
                    return;
                }
                fetch(cfg.url, {credentials: 'same-origin'}).then(function(resp) {
                    if (!resp.ok) {
                        throw new Error(resp.status);
                    }
                    return resp.text();
                }).then(function(html) {
                    content.innerHTML = html;
                    var source = content.querySelector('[data-region="updated-source"]');
                    if (source) {
                        updated.textContent = source.textContent;
                    }
                    dot.classList.remove('examproctor-live-dot-error');
                }).catch(function() {
                    dot.classList.add('examproctor-live-dot-error');
                }).then(schedule);
            };

            toggle.addEventListener('click', function() {
                paused = !paused;
                toggle.textContent = toggle.getAttribute(paused ? 'data-resume' : 'data-pause');
                dot.classList.toggle('examproctor-live-dot-paused', paused);
                if (!paused) {
                    refresh();
                }
            });
            schedule();
        },
    };
});
