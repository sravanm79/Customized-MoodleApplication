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
 * AMD module: wires the helpdesk modal submit button to the AJAX endpoint.
 *
 * Bootstrap handles modal open/close via data-bs-* attributes in the HTML
 * injected by lib.php. This module only needs to handle form submission.
 *
 * @module     local_helpdesk/modal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var Config = {};

    /**
     * Show an alert inside the modal.
     *
     * @param {string} type    Bootstrap alert type: 'success' | 'danger' | 'info'
     * @param {string} message
     */
    function showAlert(type, message, url, linktext) {
        var el = document.getElementById('local-helpdesk-alert');
        if (!el) {
            return;
        }
        el.className = 'alert alert-' + type;
        el.textContent = message;
        if (url) {
            var link = document.createElement('a');
            link.href = url;
            link.className = 'alert-link ms-1';
            link.textContent = linktext || url;
            el.appendChild(document.createTextNode(' '));
            el.appendChild(link);
        }
    }

    function hideAlert() {
        var el = document.getElementById('local-helpdesk-alert');
        if (el) {
            el.className = 'alert d-none';
        }
    }

    function setSubmitBusy(busy) {
        var btn = document.getElementById('local-helpdesk-submit');
        if (!btn) {
            return;
        }
        btn.disabled = busy;
        btn.textContent = busy ? 'Submitting…' : 'Submit';
    }

    function resetForm() {
        var form = document.getElementById('local-helpdesk-form');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }
        hideAlert();
    }

    function handleSubmit() {
        var form = document.getElementById('local-helpdesk-form');
        if (!form) {
            return;
        }

        // HTML5 validation.
        form.classList.add('was-validated');
        if (!form.checkValidity()) {
            return;
        }

        var subject     = (document.getElementById('hd-subject')     || {}).value || '';
        var description = (document.getElementById('hd-description') || {}).value || '';
        var type        = (document.getElementById('hd-type')        || {}).value || 'other';

        if (!subject.trim() || !description.trim()) {
            return;
        }

        setSubmitBusy(true);
        hideAlert();

        var body = new URLSearchParams({
            sesskey:     Config.sesskey,
            type:        type,
            subject:     subject,
            description: description,
            pageurl:     Config.pageurl  || '',
            userroles:   Config.userroles || '',
        });

        fetch(Config.submiturl, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {'Content-Type': 'application/x-www-form-urlencoded'},
            body:        body.toString(),
        })
        .then(function(resp) {
            return resp.json();
        })
        .then(function(data) {
            setSubmitBusy(false);
            if (data.success) {
                showAlert('success', data.message, data.url, data.linktext);
                // Auto-close after 6 s, leaving time to follow the ticket link.
                setTimeout(function() {
                    var modalEl = document.getElementById('local-helpdesk-modal');
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        var m = bootstrap.Modal.getInstance(modalEl);
                        if (m) {
                            m.hide();
                        }
                    }
                    resetForm();
                }, 6000);
            } else {
                showAlert('danger', data.message || 'Could not submit your ticket. Please try again.');
            }
        })
        .catch(function() {
            setSubmitBusy(false);
            showAlert('danger', 'Could not submit your ticket. Please try again.');
        });
    }

    function wireEvents() {
        var submitBtn = document.getElementById('local-helpdesk-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', handleSubmit);
        }

        // Reset form when modal is closed.
        var modalEl = document.getElementById('local-helpdesk-modal');
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', resetForm);
        }
    }

    return {
        init: function(config) {
            Config = config || {};
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', wireEvents);
            } else {
                wireEvents();
            }
        },
    };
});
