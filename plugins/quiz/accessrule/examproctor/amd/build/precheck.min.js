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
 * Student device pre-check: Safe Exam Browser detection and key validation, secure context, camera preview
 * (with a black-frame test for covered lenses), microphone level, connection to Moodle, and a copyable report.
 *
 * Written as plain ES5 AMD so the same file works as src and build without grunt.
 *
 * @module     quizaccess_examproctor/precheck
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    /** Order the browser checks are listed in. */
    var ORDER = ['seb', 'secure', 'media', 'camera', 'mic', 'keys', 'net'];
    /** Average frame brightness (0-255) below which the camera is treated as covered or shuttered. */
    var DARK_THRESHOLD = 12;
    /** How long to wait for SafeExamBrowser.security.updateKeys() to call back. */
    var KEYS_TIMEOUT_MS = 5000;
    /** Microphone listening time. */
    var MIC_MS = 4000;
    /** A round trip slower than this is reported as a slow connection. */
    var SLOW_MS = 1500;

    var cfg = null;
    var root = null;
    var checks = {};
    var stream = null;

    var str = function(key, a) {
        var s = cfg.strings[key] || key;
        if (a !== undefined && a !== null) {
            if (typeof a === 'object') {
                Object.keys(a).forEach(function(k) {
                    s = s.split('{$a->' + k + '}').join(a[k]);
                });
            } else {
                s = s.split('{$a}').join(a);
            }
        }
        return s;
    };

    var region = function(name) {
        return root.querySelector('[data-region="' + name + '"]');
    };

    /** SEB adds "SEB" (usually "SEB/3.x") to its user agent; quizaccess_seb detects it the same way. */
    var sebVersion = function() {
        var ua = navigator.userAgent || '';
        if (ua.indexOf('SEB') === -1) {
            return null;
        }
        var m = ua.match(/SEB\/([\d.]+)/);
        return m ? m[1] : '?';
    };

    var set = function(id, status, message) {
        checks[id] = {status: status, message: message};
        render();
    };

    // ---- Rendering ------------------------------------------------------------------------------

    var render = function() {
        var list = region('client-checks');
        list.innerHTML = '';
        ORDER.forEach(function(id) {
            if (!checks[id]) {
                return;
            }
            var li = document.createElement('li');
            li.className = 'examproctor-check-' + checks[id].status;
            li.setAttribute('data-check', id);
            var badge = document.createElement('span');
            badge.className = 'examproctor-check-badge';
            badge.textContent = str('status_' + checks[id].status);
            var text = document.createElement('span');
            text.textContent = checks[id].message;
            li.appendChild(badge);
            li.appendChild(text);
            list.appendChild(li);
        });

        var all = ORDER.filter(function(id) {
            return checks[id];
        }).map(function(id) {
            return checks[id].status;
        }).concat(cfg.servercheck.map(function(c) {
            return c.status;
        }));
        var summary = region('summary');
        var cls = 'alert-success';
        var msg = str('summary_ready');
        if (all.indexOf('error') !== -1) {
            cls = 'alert-danger';
            msg = str('summary_notready');
        } else if (all.indexOf('warn') !== -1 || all.indexOf('pending') !== -1) {
            cls = 'alert-warning';
            msg = str('summary_warn');
        }
        summary.className = 'examproctor-precheck-summary alert ' + cls;
        summary.textContent = msg;
        summary.setAttribute('data-status', cls.replace('alert-', ''));
        region('report').value = report();
    };

    var report = function() {
        var lines = [
            'Quiz: ' + cfg.quizname + ' (cmid ' + cfg.cmid + ')',
            'Time: ' + new Date().toISOString(),
            'Page: ' + window.location.origin + window.location.pathname,
            'User agent: ' + navigator.userAgent,
            'Screen: ' + window.screen.width + 'x' + window.screen.height + ' @' + (window.devicePixelRatio || 1),
            '',
            'Browser checks:',
        ];
        ORDER.forEach(function(id) {
            if (checks[id]) {
                lines.push('  [' + checks[id].status.toUpperCase() + '] ' + id + ': ' + checks[id].message);
            }
        });
        lines.push('', 'Server checks:');
        cfg.servercheck.forEach(function(c) {
            lines.push('  [' + c.status.toUpperCase() + '] ' + c.id + ': ' + c.message);
        });
        return lines.join('\n');
    };

    // ---- Environment ----------------------------------------------------------------------------

    var checkEnvironment = function() {
        var version = sebVersion();
        if (version) {
            set('seb', 'ok', str('c_seb_yes', version));
        } else if (cfg.sebmode > 0) {
            set('seb', 'info', str('c_seb_required_outside'));
        } else {
            set('seb', 'info', str('c_seb_notrequired'));
        }

        var problem = cfg.camerarequired ? 'error' : 'warn';
        set('secure', window.isSecureContext ? 'ok' : problem, str(window.isSecureContext ? 'c_secure_ok' : 'c_secure_no'));

        var media = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        set('media', media ? 'ok' : problem, str(media ? 'c_media_ok' : 'c_media_no'));
        if (media) {
            set('camera', cfg.camerarequired ? 'pending' : 'info',
                str(cfg.camerarequired ? 'status_pending' : 'c_cam_notrequired'));
        }
    };

    // ---- Camera ---------------------------------------------------------------------------------

    var cameraError = function(err) {
        var name = (err && err.name) || 'Error';
        var key = 'c_cam_other';
        if (name === 'NotAllowedError' || name === 'SecurityError' || name === 'PermissionDeniedError') {
            key = sebVersion() ? 'c_cam_notallowed_seb' : 'c_cam_notallowed';
        } else if (name === 'NotFoundError' || name === 'OverconstrainedError' || name === 'DevicesNotFoundError') {
            key = 'c_cam_notfound';
        } else if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') {
            key = 'c_cam_notreadable';
        }
        return str(key, name + (err && err.message ? ': ' + err.message : ''));
    };

    /** Average brightness of the current frame, to catch a covered lens or closed privacy shutter. */
    var frameBrightness = function(video) {
        var canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 48;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        var sum = 0;
        for (var i = 0; i < data.length; i += 4) {
            sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        }
        return sum / (data.length / 4);
    };

    var stopCamera = function() {
        if (stream) {
            stream.getTracks().forEach(function(t) {
                t.stop();
            });
            stream = null;
        }
        region('video').srcObject = null;
        region('video-empty').hidden = false;
        root.querySelector('[data-action="camera"]').textContent = str('camstart');
    };

    var startCamera = function() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return;
        }
        var problem = cfg.camerarequired ? 'error' : 'warn';
        navigator.mediaDevices.getUserMedia({video: true, audio: false}).then(function(s) {
            stream = s;
            var video = region('video');
            video.srcObject = s;
            region('video-empty').hidden = true;
            root.querySelector('[data-action="camera"]').textContent = str('camstop');
            var track = s.getVideoTracks()[0];
            var settings = track && track.getSettings ? track.getSettings() : {};
            var label = (track && track.label) || '?';
            region('device').textContent = label + (settings.width ? ' · ' + settings.width + '×' + settings.height : '');

            // Give the sensor time to adjust exposure before judging the picture.
            setTimeout(function() {
                if (stream !== s) {
                    return;
                }
                var brightness = 255;
                try {
                    brightness = frameBrightness(video);
                } catch (e) {
                    // Unreadable frame (should not happen for a local stream): do not fail the check on it.
                }
                var info = label + (settings.width ? ', ' + settings.width + 'x' + settings.height : '');
                if (brightness < DARK_THRESHOLD) {
                    set('camera', 'warn', str('c_cam_dark', info));
                } else {
                    set('camera', 'ok', str('c_cam_ok', info));
                }
            }, 1500);
            return s;
        }).catch(function(err) {
            set('camera', problem, cameraError(err));
        });
    };

    // ---- Microphone -----------------------------------------------------------------------------

    var testMic = function() {
        var button = root.querySelector('[data-action="mic"]');
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !Ctx) {
            set('mic', 'warn', str('c_mic_failed', 'AudioContext'));
            return;
        }
        button.disabled = true;
        button.textContent = str('mictesting');
        navigator.mediaDevices.getUserMedia({audio: true, video: false}).then(function(s) {
            var ctx = new Ctx();
            var analyser = ctx.createAnalyser();
            analyser.fftSize = 512;
            ctx.createMediaStreamSource(s).connect(analyser);
            var buf = new Float32Array(analyser.fftSize);
            var peak = 0;
            var meter = region('meter');
            var started = Date.now();
            var tick = function() {
                analyser.getFloatTimeDomainData(buf);
                var sum = 0;
                for (var i = 0; i < buf.length; i++) {
                    sum += buf[i] * buf[i];
                }
                var rms = Math.sqrt(sum / buf.length);
                peak = Math.max(peak, rms);
                meter.style.width = Math.min(100, Math.round(rms * 400)) + '%';
                if (Date.now() - started < MIC_MS) {
                    window.requestAnimationFrame(tick);
                    return;
                }
                meter.style.width = '0';
                s.getTracks().forEach(function(t) {
                    t.stop();
                });
                ctx.close();
                button.disabled = false;
                button.textContent = str('mictest');
                var label = (s.getAudioTracks()[0] && s.getAudioTracks()[0].label) || '?';
                set('mic', peak > 0.01 ? 'ok' : 'warn', str(peak > 0.01 ? 'c_mic_ok' : 'c_mic_silent', label));
            };
            tick();
            return s;
        }).catch(function(err) {
            button.disabled = false;
            button.textContent = str('mictest');
            set('mic', 'warn', str('c_mic_failed', (err && err.name) || 'Error'));
        });
    };

    // ---- Safe Exam Browser keys -----------------------------------------------------------------

    var isKeyEmpty = function(key) {
        // SEB initialises unset keys to ':'.
        return !key || key === ':';
    };

    var checkKeys = function() {
        if (cfg.sebmode === 0) {
            return;
        }
        if (!sebVersion()) {
            set('keys', 'info', str('c_keys_notinseb'));
            return;
        }
        var seb = window.SafeExamBrowser;
        if (!seb || !seb.security) {
            set('keys', 'warn', str('c_keys_noapi'));
            return;
        }
        set('keys', 'pending', str('status_pending'));

        var done = false;
        var validate = function() {
            if (done) {
                return;
            }
            done = true;
            // SEB hashes the page URL without the fragment; a '#...' in the URL would be a false mismatch.
            var url = window.location.href.split('#')[0];
            Ajax.call([{
                methodname: 'quizaccess_seb_validate_quiz_keys',
                args: {
                    cmid: cfg.cmid,
                    url: url,
                    configkey: isKeyEmpty(seb.security.configKey) ? null : seb.security.configKey,
                    browserexamkey: isKeyEmpty(seb.security.browserExamKey) ? null : seb.security.browserExamKey,
                },
            }])[0].then(function(res) {
                if (res.configkey && res.browserexamkey) {
                    set('keys', 'ok', str('c_keys_ok'));
                } else if (!res.configkey) {
                    set('keys', 'error', str('c_keys_config_bad'));
                } else {
                    set('keys', 'error', str('c_keys_bek_bad'));
                }
                return res;
            }).catch(function(err) {
                set('keys', 'error', str('c_keys_error', (err && (err.message || err.errorcode)) || 'Error'));
            });
        };

        if (typeof seb.security.updateKeys === 'function') {
            seb.security.updateKeys(validate);
            setTimeout(function() {
                if (!done) {
                    if (isKeyEmpty(seb.security.configKey) && isKeyEmpty(seb.security.browserExamKey)) {
                        done = true;
                        set('keys', 'error', str('c_keys_timeout'));
                    } else {
                        validate();
                    }
                }
            }, KEYS_TIMEOUT_MS);
        } else {
            validate();
        }
    };

    // ---- Connection -----------------------------------------------------------------------------

    var checkNetwork = function() {
        var started = Date.now();
        fetch(cfg.pingurl, {credentials: 'same-origin', cache: 'no-store'}).then(function(r) {
            return r.json();
        }).then(function(res) {
            var ms = Date.now() - started;
            if (!res || !res.ok) {
                throw new Error('bad response');
            }
            set('net', ms > SLOW_MS ? 'warn' : 'ok', str(ms > SLOW_MS ? 'c_net_slow' : 'c_net_ok', ms));
            return res;
        }).catch(function(err) {
            set('net', 'error', str('c_net_failed', (err && err.message) || 'Error'));
        });
    };

    return {
        init: function(config) {
            cfg = config;
            root = document.querySelector('[data-region="examproctor-precheck"]');
            if (!root) {
                return;
            }
            checkEnvironment();
            checkKeys();
            checkNetwork();
            render();

            root.querySelector('[data-action="camera"]').addEventListener('click', function() {
                if (stream) {
                    stopCamera();
                } else {
                    startCamera();
                }
            });
            root.querySelector('[data-action="mic"]').addEventListener('click', testMic);
            root.querySelector('[data-action="copy"]').addEventListener('click', function(e) {
                var area = region('report');
                area.select();
                var button = e.currentTarget;
                var copied = function() {
                    button.textContent = str('copied');
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(area.value).then(copied).catch(function() {
                        document.execCommand('copy');
                        copied();
                    });
                } else {
                    document.execCommand('copy');
                    copied();
                }
            });
            // Release the camera when leaving, so the quiz page (or the proctoring rule) can open it.
            window.addEventListener('pagehide', stopCamera);
        },
    };
});
