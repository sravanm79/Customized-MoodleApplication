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

namespace local_notifier;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Calendar OAuth helpers and event CRUD.
 *
 * Flow:
 *   1. User visits gcal_connect.php → get_auth_url() → redirected to Google.
 *   2. Google redirects to gcal_callback.php?code=… → exchange_code() → save_token().
 *   3. On each notification task run: create_or_update_event() uses get_token() (auto-refreshes).
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gcal {

    const AUTH_URI  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    const EVENTS_URI = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    const REVOKE_URI = 'https://oauth2.googleapis.com/revoke';
    const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    // -------------------------------------------------------------------------
    // OAuth flow
    // -------------------------------------------------------------------------

    /**
     * Returns the Google OAuth authorisation URL for the current site.
     *
     * @param string $state  CSRF token to verify in the callback.
     * @return string
     */
    public static function get_auth_url(string $state = ''): string {
        global $CFG;
        $clientid = get_config('local_notifier', 'gcal_client_id');
        $redirecturi = $CFG->wwwroot . '/local/notifier/gcal_callback.php';
        $params = [
            'client_id'     => $clientid,
            'redirect_uri'  => $redirecturi,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ];
        if ($state !== '') {
            $params['state'] = $state;
        }
        return self::AUTH_URI . '?' . http_build_query($params, '', '&');
    }

    /**
     * Exchanges an authorisation code for access + refresh tokens.
     *
     * @param string $code  From the OAuth callback ?code= param.
     * @return \stdClass  Token data with keys: access_token, refresh_token, expires_in.
     * @throws \moodle_exception  On HTTP or parse error.
     */
    public static function exchange_code(string $code): \stdClass {
        global $CFG;
        $clientid     = get_config('local_notifier', 'gcal_client_id');
        $clientsecret = get_config('local_notifier', 'gcal_client_secret');
        $redirecturi  = $CFG->wwwroot . '/local/notifier/gcal_callback.php';

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $raw = $curl->post(self::TOKEN_URI, http_build_query([
            'code'          => $code,
            'client_id'     => $clientid,
            'client_secret' => $clientsecret,
            'redirect_uri'  => $redirecturi,
            'grant_type'    => 'authorization_code',
        ], '', '&'));

        $data = json_decode($raw);
        if (empty($data->access_token)) {
            $err = $data->error_description ?? $data->error ?? $raw;
            throw new \moodle_exception('gcalerror', 'local_notifier', '', $err);
        }
        return $data;
    }

    /**
     * Stores (or replaces) OAuth tokens for a user.
     *
     * @param int       $userid
     * @param \stdClass $tokendata  From exchange_code() or a token refresh response.
     */
    public static function save_token(int $userid, \stdClass $tokendata): void {
        global $DB;
        $now    = time();
        $expiry = $now + (int) ($tokendata->expires_in ?? 3600);

        $existing = $DB->get_record('local_notifier_gcal', ['userid' => $userid]);
        if ($existing) {
            $existing->accesstoken  = $tokendata->access_token;
            $existing->tokenexpiry  = $expiry;
            $existing->timemodified = $now;
            // Only overwrite refresh token when a new one is issued (Google doesn't always return one on refresh).
            if (!empty($tokendata->refresh_token)) {
                $existing->refreshtoken = $tokendata->refresh_token;
            }
            $DB->update_record('local_notifier_gcal', $existing);
        } else {
            $DB->insert_record('local_notifier_gcal', (object) [
                'userid'       => $userid,
                'accesstoken'  => $tokendata->access_token,
                'refreshtoken' => $tokendata->refresh_token ?? '',
                'tokenexpiry'  => $expiry,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Returns a valid access token for the user, refreshing silently if expired.
     *
     * @param int $userid
     * @return string|null  Null if the user hasn't connected or the token can't be refreshed.
     */
    public static function get_token(int $userid): ?string {
        global $DB;
        $row = $DB->get_record('local_notifier_gcal', ['userid' => $userid]);
        if (!$row) {
            return null;
        }
        // Refresh 60 s before expiry.
        if ($row->tokenexpiry - 60 > time()) {
            return $row->accesstoken;
        }
        return self::refresh_token($userid, $row);
    }

    /**
     * Whether the user has connected Google Calendar.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_connected(int $userid): bool {
        global $DB;
        return $DB->record_exists('local_notifier_gcal', ['userid' => $userid]);
    }

    /**
     * Revokes Google access and removes stored tokens.
     *
     * @param int $userid
     */
    public static function revoke(int $userid): void {
        global $DB;
        $row = $DB->get_record('local_notifier_gcal', ['userid' => $userid]);
        if ($row) {
            // Best-effort revoke at Google.
            $curl = new \curl();
            $curl->post(self::REVOKE_URI, http_build_query(['token' => $row->accesstoken], '', '&'));
            $DB->delete_records('local_notifier_gcal', ['userid' => $userid]);
            $DB->delete_records('local_notifier_gcal_events', ['userid' => $userid]);
        }
    }

    // -------------------------------------------------------------------------
    // Calendar event CRUD
    // -------------------------------------------------------------------------

    /**
     * Creates or updates a Google Calendar event for this Moodle event.
     *
     * Silently does nothing if the user hasn't connected GCal or the token
     * can't be refreshed (rather than failing the whole notification task).
     *
     * @param int    $userid
     * @param string $component   'assign'|'zoom'|'quiz'
     * @param int    $instanceid  cmid or zoom.id
     * @param array  $eventdata   Keys: summary, description, start (UNIX ts), end (UNIX ts), url
     */
    public static function create_or_update_event(
        int $userid,
        string $component,
        int $instanceid,
        array $eventdata
    ): void {
        global $DB;

        $token = self::get_token($userid);
        if (!$token) {
            return;
        }

        $tz = \core_date::get_user_timezone_object()->getName();
        $event = [
            'summary'     => $eventdata['summary'],
            'description' => $eventdata['description'],
            'start' => ['dateTime' => date('c', $eventdata['start']), 'timeZone' => $tz],
            'end'   => ['dateTime' => date('c', $eventdata['end']),   'timeZone' => $tz],
            'source' => [
                'title' => $eventdata['summary'],
                'url'   => $eventdata['url'],
            ],
            'reminders' => ['useDefault' => false, 'overrides' => []],
        ];

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);

        $existing = $DB->get_record('local_notifier_gcal_events', [
            'userid'     => $userid,
            'component'  => $component,
            'instanceid' => $instanceid,
        ]);

        if ($existing) {
            // PATCH the existing event — only changed fields need sending.
            $raw = $curl->patch(
                self::EVENTS_URI . '/' . urlencode($existing->gcaleventid),
                json_encode($event)
            );
            $response = json_decode($raw);
            if (!empty($response->id)) {
                $DB->update_record('local_notifier_gcal_events', (object) [
                    'id'           => $existing->id,
                    'timemodified' => time(),
                ]);
            }
        } else {
            $raw      = $curl->post(self::EVENTS_URI, json_encode($event));
            $response = json_decode($raw);
            if (!empty($response->id)) {
                $DB->insert_record('local_notifier_gcal_events', (object) [
                    'userid'       => $userid,
                    'component'    => $component,
                    'instanceid'   => $instanceid,
                    'gcaleventid'  => $response->id,
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Uses the stored refresh token to obtain a new access token.
     *
     * @param int       $userid
     * @param \stdClass $row  Row from local_notifier_gcal.
     * @return string|null
     */
    private static function refresh_token(int $userid, \stdClass $row): ?string {
        $clientid     = get_config('local_notifier', 'gcal_client_id');
        $clientsecret = get_config('local_notifier', 'gcal_client_secret');

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $raw = $curl->post(self::TOKEN_URI, http_build_query([
            'client_id'     => $clientid,
            'client_secret' => $clientsecret,
            'refresh_token' => $row->refreshtoken,
            'grant_type'    => 'refresh_token',
        ], '', '&'));

        $data = json_decode($raw);
        if (empty($data->access_token)) {
            return null;
        }
        self::save_token($userid, $data);
        return $data->access_token;
    }
}
