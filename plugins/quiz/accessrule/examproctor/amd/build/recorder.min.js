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
 * Records low-resolution webcam video during a proctored attempt and uploads it while the student works.
 *
 * Video is recorded in self-contained segments (a new MediaRecorder every cfg.segmentms) and uploaded in
 * cfg.chunkms pieces, in order, so leaving the page loses at most one piece. See classes/local/video.php.
 *
 * Written as plain ES5 AMD so the same file works as src and build without grunt.
 *
 * @module     quizaccess_examproctor/recorder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    /** Container formats in order of preference; mp4 is what Safari records. */
    var MIMETYPES = ['video/webm;codecs=vp8', 'video/webm', 'video/mp4'];
    /** Keepalive requests (sent while the page unloads) are limited to 64 KB by browsers. */
    var KEEPALIVE_LIMIT = 60000;

    var cfg = null;
    var stream = null;
    var mimetype = '';
    var format = 'webm';
    var recorder = null;
    var rotateTimer = null;
    var queue = Promise.resolve();
    var stopped = false;
    var unloading = false;
    var pill = null;

    var log = function(eventtype, details) {
        Ajax.call([{
            methodname: 'quizaccess_examproctor_log_event',
            args: {attemptid: cfg.attemptid, eventtype: eventtype, details: details ? String(details).substring(0, 250) : ''},
        }])[0].catch(function() {
            // Logging is best effort; the monitor also sees missing uploads.
        });
    };

    var setStatus = function(recording) {
        if (!pill) {
            pill = document.createElement('span');
            pill.className = 'examproctor-rec';
            var bar = document.querySelector('.examproctor-bar');
            (bar || document.body).appendChild(pill);
            if (!bar) {
                pill.classList.add('examproctor-rec-floating');
            }
        }
        pill.classList.toggle('examproctor-rec-off', !recording);
        pill.textContent = recording ? cfg.strings.recording : cfg.strings.notrecording;
    };

    var stopAll = function() {
        stopped = true;
        clearTimeout(rotateTimer);
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
        if (stream) {
            stream.getTracks().forEach(function(t) {
                t.stop();
            });
        }
        setStatus(false);
    };

    var upload = function(blob, segment, seq, isfinal) {
        var send = function() {
            var data = new FormData();
            data.append('sesskey', cfg.sesskey);
            data.append('attemptid', cfg.attemptid);
            data.append('segment', segment);
            data.append('seq', seq);
            data.append('format', format);
            data.append('final', isfinal ? 1 : 0);
            data.append('chunk', blob, segment + '-' + seq + '.' + format);
            return fetch(cfg.uploadurl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                keepalive: unloading && blob.size < KEEPALIVE_LIMIT,
            }).then(function(resp) {
                return resp.json();
            });
        };
        // Uploads go one after another so the server receives each segment's pieces in order.
        queue = queue.then(function() {
            return send().catch(function() {
                return send();
            }).then(function(res) {
                if (res && res.status === 'stop') {
                    stopAll();
                }
            }).catch(function() {
                setStatus(false);
            });
        });
    };

    var startSegment = function() {
        if (stopped) {
            return;
        }
        var segment = Date.now();
        var seq = 0;
        var ending = false;
        var rec;
        try {
            rec = new MediaRecorder(stream, {mimeType: mimetype, videoBitsPerSecond: cfg.bitrate});
        } catch (e) {
            log('videofailed', e.name || e);
            stopAll();
            return;
        }
        rec.ondataavailable = function(e) {
            if (e.data && e.data.size > 0) {
                upload(e.data, segment, seq++, ending);
            }
        };
        rec.onerror = function(e) {
            log('videofailed', e.error ? e.error.name : 'recorder error');
        };
        rec.start(cfg.chunkms);
        recorder = rec;
        setStatus(true);

        rotateTimer = setTimeout(function() {
            // Close this segment (its last piece is sent as final) and start the next one at once.
            ending = true;
            rec.stop();
            startSegment();
        }, cfg.segmentms);
    };

    var onPageHide = function() {
        unloading = true;
        clearTimeout(rotateTimer);
        if (recorder && recorder.state === 'recording') {
            recorder.requestData();
        }
    };

    return {
        init: function(config) {
            cfg = config;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
                setStatus(false);
                log('videofailed', 'browser cannot record video');
                return;
            }
            mimetype = MIMETYPES.filter(function(m) {
                return MediaRecorder.isTypeSupported(m);
            })[0] || '';
            format = mimetype.indexOf('mp4') !== -1 ? 'mp4' : 'webm';

            navigator.mediaDevices.getUserMedia({
                video: {width: {ideal: 320}, height: {ideal: 240}, frameRate: {ideal: 10, max: 15}},
                audio: false,
            }).then(function(s) {
                stream = s;
                stream.getVideoTracks().forEach(function(track) {
                    track.addEventListener('ended', function() {
                        if (!stopped && !unloading) {
                            log('videostopped', track.label);
                            stopAll();
                        }
                    });
                });
                log('videostart', (stream.getVideoTracks()[0] || {}).label || '');
                startSegment();
                window.addEventListener('pagehide', onPageHide);
            }).catch(function(e) {
                setStatus(false);
                log('videofailed', e.name || e);
            });
        },
    };
});
