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
 * Attempt video player: plays the recorded segments one after another.
 *
 * Written as plain ES5 AMD so the same file works as src and build without grunt.
 *
 * @module     quizaccess_examproctor/video
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        init: function() {
            var root = document.querySelector('[data-region="examproctor-video"]');
            if (!root) {
                return;
            }
            var player = root.querySelector('[data-region="player"]');
            var items = Array.prototype.slice.call(root.querySelectorAll('[data-action="play-segment"]'));
            var current = 0;

            var play = function(index, autoplay) {
                if (!items[index]) {
                    return;
                }
                items[current].classList.remove('active');
                current = index;
                items[current].classList.add('active');
                player.src = items[current].getAttribute('data-url');
                if (autoplay) {
                    player.play().catch(function() {
                        // Autoplay can be refused; the controls still work.
                    });
                }
            };

            items.forEach(function(item, index) {
                item.addEventListener('click', function() {
                    play(index, true);
                });
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        play(index, true);
                    }
                });
            });
            player.addEventListener('ended', function() {
                play(current + 1, true);
            });
        },
    };
});
