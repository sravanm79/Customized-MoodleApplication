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


namespace mod_googlemeet\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/googlemeet/locallib.php');

use mod_googlemeet\client;

/**
 * Imports Google Drive recordings of recent meetings without anyone pressing "Sync with Google Drive"
 * (IIIT Dharwad addition).
 *
 * Recordings live in the organiser's Drive, so the sync runs as a teacher of the course who has connected their
 * Google account once (Moodle keeps their refresh token), preferring the one whose email is the meeting's creator.
 *
 * @package     mod_googlemeet
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_recordings extends \core\task\scheduled_task {

    /** Meetings whose last class ended within this many days are synced (recordings can take hours to appear). */
    const LOOKBACK_DAYS = 3;

    /**
     * Returns localised task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('syncrecordingstask', 'mod_googlemeet');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $issuerid = (int) get_config('googlemeet', 'issuerid');
        $issuer = $issuerid ? \core\oauth2\api::get_issuer($issuerid) : null;
        if (!$issuer || !$issuer->get('enabled')) {
            mtrace('mod_googlemeet: no Google OAuth 2 service selected in the plugin settings, skipping.');
            return;
        }

        $now = time();
        $sql = "SELECT DISTINCT g.*
                  FROM {googlemeet} g
                  JOIN {googlemeet_events} e ON e.googlemeetid = g.id
                 WHERE g.url IS NOT NULL AND g.url <> ''
                   AND e.eventdate + e.duration BETWEEN :from AND :now";
        $meetings = $DB->get_records_sql($sql, ['from' => $now - self::LOOKBACK_DAYS * DAYSECS, 'now' => $now]);

        foreach ($meetings as $googlemeet) {
            try {
                $this->sync($googlemeet, $issuerid);
            } catch (\Throwable $e) {
                mtrace("  meeting {$googlemeet->id}: " . $e->getMessage());
            } finally {
                \core\cron::setup_user();
            }
        }
    }

    /**
     * Syncs one meeting as the first suitable teacher whose Drive has its recordings.
     *
     * @param \stdClass $googlemeet
     * @param int $issuerid
     */
    protected function sync(\stdClass $googlemeet, int $issuerid): void {
        global $DB;

        $cm = get_coursemodule_from_instance('googlemeet', $googlemeet->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $teachers = get_enrolled_users($context, 'mod/googlemeet:syncgoogledrive', 0, 'u.*', null, 0, 0, true);
        foreach (get_admins() as $admin) {
            $teachers[$admin->id] = $admin;
        }
        // Only users who connected Google (a stored refresh token), the meeting's creator first.
        $connected = $DB->get_fieldset_select('oauth2_refresh_token', 'userid', 'issuerid = ?', [$issuerid]);
        $teachers = array_filter($teachers, fn($u) => in_array($u->id, $connected));
        usort($teachers, fn($a, $b) => (int) (strcasecmp($b->email, (string) $googlemeet->creatoremail) === 0)
            - (int) (strcasecmp($a->email, (string) $googlemeet->creatoremail) === 0));

        if (!$teachers) {
            mtrace("  meeting {$googlemeet->id}: no teacher of the course has connected Google Drive yet.");
            return;
        }
        foreach ($teachers as $teacher) {
            \core\cron::setup_user($teacher);
            $recordings = (new client())->fetch_recordings($googlemeet);
            if ($recordings) {
                sync_recordings($googlemeet->id, $recordings);
                mtrace("  meeting {$googlemeet->id}: " . count($recordings) . " recording(s) synced as user {$teacher->id}.");
                return;
            }
        }
        mtrace("  meeting {$googlemeet->id}: no recordings found yet.");
    }
}
