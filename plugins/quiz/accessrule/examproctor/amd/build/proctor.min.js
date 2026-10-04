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
 * Proctored exam client: detects tab switches, focus loss and fullscreen exit,
 * reports them to the server, warns the student and auto-submits at the limit.
 *
 * Written as plain ES5 AMD so the same file works as src and build without grunt.
 *
 * @module     quizaccess_examproctor/proctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    /** Two signals within this window (e.g. tab switch + fullscreen exit) count as one violation. */
    var MERGE_WINDOW_MS = 1500;
    /** Delay before checking focus after a blur, so focus moving into an editor iframe is not a violation. */
    var BLUR_CHECK_MS = 250;
    /** Fallback poll for focus loss that fires no blur event (e.g. from inside an iframe). */
    var POLL_MS = 1000;
    /**
     * A fullscreen exit this soon after returning belongs to the same absence. Browsers exit fullscreen when the tab is
     * hidden but only deliver fullscreenchange once the page is visible again, i.e. after the student came back.
     */
    var RETURN_GRACE_MS = 3000;

    var cfg = null;
    var state = {
        violations: 0,
        away: false,
        awayType: null,
        awaySince: 0,
        returnedAt: 0,
        lastViolationAt: 0,
        submitting: false,
        stopped: false,
        fullscreenPending: false,
        armed: false,
    };
    var ui = {};

    var str = function(key, a) {
        var s = cfg.strings[key] || key;
        if (a) {
            Object.keys(a).forEach(function(k) {
                s = s.split('{$a->' + k + '}').join(a[k]);
            });
        }
        return s;
    };

    var fullscreenSupported = function() {
        return !!(document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen);
    };

    var isFullscreen = function() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    };

    var enterFullscreen = function() {
        var el = document.documentElement;
        var req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (!req) {
            return;
        }
        try {
            var p = req.call(el);
            if (p && p.catch) {
                p.catch(function() {
                    // Browser refused (e.g. no user gesture); the overlay stays up so the student can retry.
                });
            }
        } catch (e) {
            // Ignore; overlay remains.
        }
    };

    // ---- Server communication -------------------------------------------------------------------

    var send = function(type, details) {
        if (state.stopped) {
            return Promise.resolve(null);
        }
        return Ajax.call([{
            methodname: 'quizaccess_examproctor_log_event',
            args: {attemptid: cfg.attemptid, eventtype: type, details: details ? String(details) : ''},
        }])[0].then(function(res) {
            handleResponse(res);
            return res;
        }).catch(function() {
            // Network hiccup: keep the exam usable. The event is lost but the local counter still moves.
            return null;
        });
    };

    var handleResponse = function(res) {
        if (!res) {
            return;
        }
        if (res.action === 'stop') {
            state.stopped = true;
            return;
        }
        state.violations = Math.max(state.violations, res.violations);
        updateBar();
        if (res.action === 'submit') {
            autoSubmit();
        }
    };

    // ---- Detection ------------------------------------------------------------------------------

    var recordViolation = function(type) {
        if (state.submitting || state.stopped) {
            return null;
        }
        var now = Date.now();
        if (now - state.lastViolationAt < MERGE_WINDOW_MS) {
            return null;
        }
        state.lastViolationAt = now;
        state.violations++;
        updateBar();
        return send(type);
    };

    var goneAway = function(type) {
        if (state.away || !state.armed) {
            // One absence counts once, even if it goes blur -> hidden.
            return;
        }
        state.away = true;
        state.awayType = type;
        state.awaySince = Date.now();
        recordViolation(type);
    };

    var cameBack = function() {
        if (!state.away || document.hidden || !document.hasFocus()) {
            return;
        }
        state.away = false;
        state.returnedAt = Date.now();
        var seconds = Math.round((Date.now() - state.awaySince) / 1000);
        send('returned', state.awayType + ':' + seconds + 's');
        if (!state.submitting) {
            showWarning(seconds);
        }
    };

    var checkFocus = function() {
        if (!state.armed && !document.hidden && document.hasFocus()) {
            // Only start watching once the student has actually seen the page (it may load in a background tab).
            state.armed = true;
        }
        if (document.hidden) {
            goneAway('tabhidden');
        } else if (cfg.detectblur && !document.hasFocus()) {
            // hasFocus() stays true while an iframe inside the page (e.g. the TinyMCE editor) has focus.
            goneAway('windowblur');
        } else {
            cameBack();
        }
    };

    var onFullscreenChange = function() {
        if (isFullscreen()) {
            state.fullscreenPending = false;
            hideOverlay('fullscreen');
            send('fullscreenenter');
            return;
        }
        if (state.submitting || state.stopped) {
            return;
        }
        // Leaving the tab already counted as a violation: do not count the fullscreen exit it caused as a second one.
        var partOfAbsence = state.away || document.hidden || Date.now() - state.returnedAt < RETURN_GRACE_MS;
        if (!partOfAbsence) {
            recordViolation('fullscreenexit');
        }
        state.fullscreenPending = true;
        showFullscreenGate();
    };

    var blockEvent = function(e) {
        if (state.submitting) {
            return;
        }
        e.preventDefault();
        flash(str('blocked'));
        // Informational only; throttle so holding a key doesn't flood the log.
        var now = Date.now();
        if (!state['last' + e.type] || now - state['last' + e.type] > 5000) {
            state['last' + e.type] = now;
            send(e.type);
        }
    };

    // ---- Auto-submit ----------------------------------------------------------------------------

    var autoSubmit = function() {
        if (state.submitting) {
            return;
        }
        state.submitting = true;
        hideOverlay('warning');
        hideOverlay('fullscreen');
        showOverlay('submitting', str('submittingtitle'), str('submittingbody'), null);

        var form = document.getElementById('responseform') || document.getElementById('frm-finishattempt');
        if (!form) {
            // Unknown page: the server will finish the attempt on the next reported violation.
            window.location.reload();
            return;
        }
        var input = form.querySelector('input[name="finishattempt"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'finishattempt';
            form.appendChild(input);
        }
        input.value = '1';

        var doSubmit = function() {
            HTMLFormElement.prototype.submit.call(form);
        };
        // Stop Moodle's "unsaved changes" prompt from blocking the navigation.
        require(['core_form/changechecker'], function(changechecker) {
            if (changechecker.markAllFormsSubmitted) {
                changechecker.markAllFormsSubmitted();
            }
            setTimeout(doSubmit, 1200);
        }, function() {
            setTimeout(doSubmit, 1200);
        });
    };

    // ---- UI -------------------------------------------------------------------------------------

    var el = function(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) {
            n.className = cls;
        }
        if (text !== undefined) {
            n.textContent = text;
        }
        return n;
    };

    var buildBar = function() {
        var bar = el('div', 'examproctor-bar');
        bar.setAttribute('role', 'status');
        bar.appendChild(el('span', 'examproctor-bar-dot'));
        bar.appendChild(el('span', 'examproctor-bar-title', str('bartitle')));
        if (cfg.seb) {
            // Fullscreen and focus checks are left to Safe Exam Browser's kiosk mode.
            bar.appendChild(el('span', 'examproctor-bar-seb', str('seb')));
        }
        if (cfg.ispreview) {
            bar.appendChild(el('span', 'examproctor-bar-preview', str('ispreview')));
        }
        ui.count = el('span', 'examproctor-bar-count');
        bar.appendChild(ui.count);
        document.body.appendChild(bar);
        ui.bar = bar;

        ui.toast = el('div', 'examproctor-toast');
        ui.toast.setAttribute('aria-live', 'polite');
        document.body.appendChild(ui.toast);
        updateBar();
    };

    var updateBar = function() {
        if (!ui.count) {
            return;
        }
        ui.count.textContent = cfg.maxviolations > 0
            ? str('barviolations', {count: state.violations, max: cfg.maxviolations})
            : str('barnolimit', {count: state.violations});
        ui.bar.classList.toggle('examproctor-bar-alert', state.violations > 0);
    };

    var flash = function(msg) {
        ui.toast.textContent = msg;
        ui.toast.classList.add('examproctor-toast-show');
        clearTimeout(ui.toastTimer);
        ui.toastTimer = setTimeout(function() {
            ui.toast.classList.remove('examproctor-toast-show');
        }, 2000);
    };

    var showOverlay = function(name, title, body, button) {
        hideOverlay(name);
        var overlay = el('div', 'examproctor-overlay examproctor-overlay-' + name);
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        var box = el('div', 'examproctor-dialog');
        var h = el('h2', 'examproctor-dialog-title', title);
        h.id = 'examproctor-title-' + name;
        overlay.setAttribute('aria-labelledby', h.id);
        box.appendChild(h);
        body.split(/\\n|\n/).forEach(function(line) {
            box.appendChild(el('p', null, line));
        });
        if (button) {
            var b = el('button', 'btn btn-primary btn-lg', button.label);
            b.type = 'button';
            b.addEventListener('click', button.action);
            box.appendChild(b);
            setTimeout(function() {
                b.focus();
            }, 0);
        }
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        ui[name] = overlay;
    };

    var hideOverlay = function(name) {
        if (ui[name]) {
            ui[name].remove();
            ui[name] = null;
        }
    };

    var needsFullscreen = function() {
        return cfg.requirefullscreen && fullscreenSupported() && !isFullscreen();
    };

    var showFullscreenGate = function() {
        showOverlay('fullscreen', str('fullscreentitle'), str('fullscreenbody'), {
            label: str('fullscreenbutton'),
            action: enterFullscreen,
        });
    };

    var showWarning = function(seconds) {
        var body = str('warningbody', {seconds: seconds, count: state.violations});
        if (cfg.maxviolations > 0) {
            body += '\n' + str('warninglimit', {max: cfg.maxviolations,
                remaining: Math.max(0, cfg.maxviolations - state.violations)});
        }
        showOverlay('warning', str('warningtitle'), body, {
            label: str('warningok'),
            action: function() {
                hideOverlay('warning');
                // The click is a user gesture, so fullscreen can be re-entered here.
                if (needsFullscreen()) {
                    enterFullscreen();
                }
            },
        });
    };

    // ---- Init -----------------------------------------------------------------------------------

    return {
        init: function(config) {
            cfg = config;
            state.violations = config.violations || 0;
            buildBar();

            document.addEventListener('visibilitychange', checkFocus);
            window.addEventListener('blur', function() {
                setTimeout(checkFocus, BLUR_CHECK_MS);
            });
            window.addEventListener('focus', checkFocus);
            setInterval(checkFocus, POLL_MS);

            if (cfg.requirefullscreen && fullscreenSupported()) {
                document.addEventListener('fullscreenchange', onFullscreenChange);
                document.addEventListener('webkitfullscreenchange', onFullscreenChange);
                if (!isFullscreen()) {
                    // Browsers only allow fullscreen from a click, so gate the exam behind a button.
                    showFullscreenGate();
                }
            }

            if (cfg.blockcopypaste) {
                ['copy', 'cut', 'paste', 'contextmenu'].forEach(function(type) {
                    document.addEventListener(type, blockEvent, true);
                });
            }
        },
    };
});
