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
 * Global injection: adds the floating feedback button and modal to every page.
 *
 * @package   local_helpdesk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle calls this on every page just before the footer is rendered.
 * We inject the floating button, modal HTML, and load the AMD handler.
 *
 * @return string  Additional HTML appended before </body>.
 */
function local_helpdesk_before_footer() {
    global $PAGE, $USER, $CFG;

    if (!get_config('local_helpdesk', 'enabled')) {
        return '';
    }
    if (!isloggedin() || isguestuser()) {
        return '';
    }
    // Don't show on login, admin upgrade, or AJAX requests.
    if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        return '';
    }
    $pagetype = $PAGE->pagetype ?? '';
    if (strpos($pagetype, 'admin-') === 0) {
        return '';
    }

    // Collect context for the ticket.
    $context  = $PAGE->context ?? context_system::instance();
    $roles    = array_column(get_user_roles($context, $USER->id, true), 'shortname');
    $pageurl  = $PAGE->url instanceof moodle_url ? $PAGE->url->out(false) : '';

    $submiturl = (new moodle_url('/local/helpdesk/submit.php'))->out(false);

    // Load the AMD module — it handles click → modal → AJAX submit.
    $PAGE->requires->js_call_amd('local_helpdesk/modal', 'init', [[
        'submiturl' => $submiturl,
        'sesskey'   => sesskey(),
        'pageurl'   => $pageurl,
        'userroles' => implode(', ', $roles),
    ]]);

    // Build the modal + floating button HTML.
    $typeoptions = '';
    foreach (['bug' => 'type_bug', 'feature' => 'type_feature', 'other' => 'type_other'] as $val => $strkey) {
        $typeoptions .= html_writer::tag('option', get_string($strkey, 'local_helpdesk'), ['value' => $val]);
    }

    $modal = '
<div class="modal fade" id="local-helpdesk-modal" tabindex="-1"
     aria-labelledby="local-helpdesk-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="local-helpdesk-modal-label">' .
            get_string('modal_title', 'local_helpdesk') . '</h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="modal" aria-label="' . get_string('cancel', 'local_helpdesk') . '"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">' . get_string('modal_intro', 'local_helpdesk') . '</p>
        <div id="local-helpdesk-alert" class="alert d-none" role="alert"></div>
        <form id="local-helpdesk-form" novalidate>
          <div class="mb-3">
            <label for="hd-type" class="form-label fw-semibold">' .
                get_string('type', 'local_helpdesk') . '</label>
            <select class="form-select" id="hd-type" name="type" required>' .
                $typeoptions . '
            </select>
          </div>
          <div class="mb-3">
            <label for="hd-subject" class="form-label fw-semibold">' .
                get_string('subject', 'local_helpdesk') . ' <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="hd-subject" name="subject"
                   maxlength="255" required
                   placeholder="' . get_string('subject_help', 'local_helpdesk') . '">
          </div>
          <div class="mb-3">
            <label for="hd-description" class="form-label fw-semibold">' .
                get_string('description', 'local_helpdesk') . ' <span class="text-danger">*</span></label>
            <textarea class="form-control" id="hd-description" name="description"
                      rows="4" required
                      placeholder="' . get_string('description_help', 'local_helpdesk') . '"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                data-bs-dismiss="modal">' . get_string('cancel', 'local_helpdesk') . '</button>
        <button type="button" class="btn btn-primary" id="local-helpdesk-submit">' .
            get_string('submit', 'local_helpdesk') . '</button>
      </div>
    </div>
  </div>
</div>

<button type="button"
        id="local-helpdesk-fab"
        class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center"
        data-bs-toggle="modal"
        data-bs-target="#local-helpdesk-modal"
        title="' . get_string('fab_title', 'local_helpdesk') . '"
        aria-label="' . get_string('fab_title', 'local_helpdesk') . '"
        style="position:fixed;bottom:28px;right:28px;z-index:1055;
               width:54px;height:54px;font-size:1.5rem;font-weight:700;
               border-radius:50%;box-shadow:0 4px 12px rgba(0,0,0,.3);">
  ?
</button>';

    return $modal;
}
