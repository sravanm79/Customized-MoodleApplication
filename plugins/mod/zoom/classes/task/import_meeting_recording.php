<?php
// This file is part of the Zoom plugin for Moodle - http://moodle.org/
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
 * Adhoc task: import cloud recordings for a single meeting shortly after it ends.
 *
 * Scheduled automatically when a meeting with option_auto_recording=cloud is
 * created or updated. Runs 30 minutes after the meeting's end time so Zoom
 * has time to process the recording. Recordings are inserted with
 * showrecording=1 so students can access them immediately.
 *
 * @package    mod_zoom
 * @copyright  2026 IIIT Dharwad LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/locallib.php');

use core\task\adhoc_task;
use moodle_exception;
use stdClass;

/**
 * Adhoc task that imports cloud recordings for one specific Zoom meeting.
 */
class import_meeting_recording extends adhoc_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('importmeetingrecording', 'mod_zoom');
    }

    /**
     * Fetch and store cloud recordings for the meeting stored in custom data.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data       = $this->get_custom_data();
        $zoomid     = (int) $data->zoomid;
        $retrycount = (int) ($data->retry_count ?? 0);

        $zoom = $DB->get_record('zoom', ['id' => $zoomid]);
        if (!$zoom) {
            mtrace("mod_zoom import_meeting_recording: meeting id {$zoomid} not found, skipping.");
            return;
        }

        try {
            $service = zoom_webservice();
        } catch (moodle_exception $e) {
            mtrace('mod_zoom import_meeting_recording: skipping — ' . $e->getMessage());
            return;
        }

        $config = get_config('zoom');
        if (empty($config->viewrecordings)) {
            mtrace('mod_zoom import_meeting_recording: viewrecordings feature is disabled, skipping.');
            return;
        }

        // Check required OAuth scopes.
        $requiredscopes = [
            'classic'  => ['recording:read:admin'],
            'granular' => [
                'cloud_recording:read:list_user_recordings:admin',
                'cloud_recording:read:recording_settings:admin',
            ],
        ];
        $missingscopes = $service->check_scopes($requiredscopes);
        if (!empty($missingscopes)) {
            foreach ($missingscopes as $scope) {
                mtrace('mod_zoom import_meeting_recording: missing OAuth scope — ' . $scope);
            }
            return;
        }

        // Respect API rate-limit back-off set by other tasks.
        $retryafter = get_config('zoom', 'retry-after');
        if (!empty($retryafter) && time() < $retryafter) {
            mtrace('mod_zoom import_meeting_recording: out of API calls, requeueing after back-off.');
            $this->set_next_run_time($retryafter + 60);
            \core\task\manager::reschedule_or_queue_adhoc_task($this);
            return;
        }

        // Scan a 2-day window centred on the meeting end time.
        $meetingend = intval($zoom->start_time) + intval($zoom->duration);
        $from = gmdate('Y-m-d', $meetingend - DAYSECS);
        $to   = gmdate('Y-m-d', $meetingend + DAYSECS);

        mtrace("mod_zoom import_meeting_recording: fetching recordings for '{$zoom->name}' (meeting_id={$zoom->meeting_id})");

        $localrecordings = zoom_get_meeting_recordings_grouped($zoomid);

        try {
            $zoomrecordings = $service->get_user_recordings($zoom->host_id, $from, $to);
        } catch (moodle_exception $e) {
            mtrace('mod_zoom import_meeting_recording: API error — ' . $e->getMessage());
            return;
        }

        $now              = time();
        $meetingpasscodes = [];
        $imported         = 0;

        foreach ($zoomrecordings as $recordingid => $recording) {
            // Only import recordings that belong to this specific meeting.
            if ($recording->meetingid != $zoom->meeting_id) {
                continue;
            }

            if (isset($localrecordings[$recording->meetinguuid][$recordingid])) {
                mtrace("mod_zoom import_meeting_recording: recording {$recordingid} already exists, skipping.");
                continue;
            }

            if (empty($meetingpasscodes[$recording->meetinguuid])) {
                try {
                    $settings = $service->get_recording_settings($recording->meetinguuid);
                    $meetingpasscodes[$recording->meetinguuid] = $settings->password;
                } catch (moodle_exception $e) {
                    mtrace("mod_zoom import_meeting_recording: could not get passcode for {$recording->meetinguuid}, skipping.");
                    continue;
                }
            }

            $record = new stdClass();
            $record->zoomid          = $zoomid;
            $record->meetinguuid     = $recording->meetinguuid;
            $record->zoomrecordingid = $recordingid;
            $record->name            = $zoom->name;
            $record->externalurl     = $recording->url;
            $record->passcode        = $meetingpasscodes[$recording->meetinguuid];
            $record->recordingtype   = $recording->recordingtype;
            $record->recordingstart  = $recording->recordingstart;
            $record->showrecording   = 1; // Always visible immediately on auto-import.
            $record->timecreated     = $now;
            $record->timemodified    = $now;

            $DB->insert_record('zoom_meeting_recordings', $record);
            $imported++;
            mtrace("mod_zoom import_meeting_recording: imported {$recordingid} ({$recording->recordingtype}) for '{$zoom->name}'");
        }

        if ($imported === 0) {
            // Zoom cloud processing can take 30-90 min. Retry up to 3 more times (30 min apart)
            // before giving up and letting the scheduled task handle it as a fallback.
            if ($retrycount < 3) {
                $retrytask = new \mod_zoom\task\import_meeting_recording();
                $retrytask->set_custom_data(['zoomid' => $zoomid, 'retry_count' => $retrycount + 1]);
                $retrytask->set_next_run_time(time() + 30 * MINSECS);
                \core\task\manager::queue_adhoc_task($retrytask);
                mtrace("mod_zoom import_meeting_recording: no recordings yet for '{$zoom->name}', retry " .
                    ($retrycount + 1) . "/3 queued in 30 min.");
            } else {
                mtrace("mod_zoom import_meeting_recording: no recordings found for '{$zoom->name}' after all retries." .
                    ' The scheduled task will handle it as a fallback.');
            }
        } else {
            mtrace("mod_zoom import_meeting_recording: {$imported} recording(s) imported for '{$zoom->name}'.");
        }
    }
}
