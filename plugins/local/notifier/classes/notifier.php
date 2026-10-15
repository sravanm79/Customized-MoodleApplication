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
 * Core helper: lead-time parsing, deduplication, and message dispatch.
 *
 * @package   local_notifier
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {

    /**
     * Returns parsed lead times for the given event type.
     *
     * @param string $type  'assign' | 'zoom' | 'quiz'
     * @return int[]  Sorted descending list of minute values (e.g. [1440, 60]).
     */
    public static function get_lead_minutes(string $type): array {
        $raw = get_config('local_notifier', $type . '_lead_times') ?: '';
        $mins = [];
        foreach (explode(',', $raw) as $part) {
            $v = (int) trim($part);
            if ($v > 0) {
                $mins[] = $v;
            }
        }
        rsort($mins);
        return $mins;
    }

    /**
     * Whether we have already sent this exact notification.
     *
     * @param int    $userid
     * @param string $component  'assign'|'zoom'|'quiz'
     * @param int    $instanceid  cmid for assign/quiz; zoom.id for zoom
     * @param int    $leadmins
     * @return bool
     */
    public static function is_sent(int $userid, string $component, int $instanceid, int $leadmins): bool {
        global $DB;
        return $DB->record_exists('local_notifier_sent', [
            'userid'     => $userid,
            'component'  => $component,
            'instanceid' => $instanceid,
            'leadmins'   => $leadmins,
        ]);
    }

    /**
     * Records that a notification was sent.
     *
     * @param int    $userid
     * @param string $component
     * @param int    $instanceid
     * @param int    $leadmins
     */
    public static function mark_sent(int $userid, string $component, int $instanceid, int $leadmins): void {
        global $DB;
        $DB->insert_record('local_notifier_sent', (object) [
            'userid'     => $userid,
            'component'  => $component,
            'instanceid' => $instanceid,
            'leadmins'   => $leadmins,
            'timesent'   => time(),
        ]);
    }

    /**
     * Whether this lead time should fire right now.
     *
     * Fires when: the lead window has opened (eventtime - lead has passed) and the event
     * hasn't happened yet. Dedup prevents re-fire on subsequent task runs.
     *
     * @param int $eventtime  UNIX timestamp of the event (due date / start time / close time).
     * @param int $leadmins   Lead time in minutes.
     * @param int $now        Current UNIX timestamp.
     * @return bool
     */
    public static function should_fire(int $eventtime, int $leadmins, int $now): bool {
        $fireafter = $eventtime - ($leadmins * MINSECS);
        return $fireafter <= $now && $now < $eventtime;
    }

    /**
     * Sends a Moodle message to a user (in-app notification + email + mobile push via airnotifier).
     *
     * @param int        $userid
     * @param string     $subject
     * @param string     $body     Plain text.
     * @param \moodle_url $url
     */
    public static function send_message(int $userid, string $subject, string $body, \moodle_url $url): void {
        $message = new \core\message\message();
        $message->component         = 'local_notifier';
        $message->name              = 'deadline_reminder';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $userid;
        $message->subject           = $subject;
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = text_to_html($body, false, false, true);
        $message->smallmessage      = $subject;
        $message->notification      = 1;
        $message->contexturl        = $url->out(false);
        $message->contexturlname    = $subject;
        message_send($message);
    }

    /**
     * Human-readable lead time string (e.g. "24 hours", "30 minutes").
     *
     * @param int $minutes
     * @return string
     */
    public static function format_lead(int $minutes): string {
        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $days = $minutes / 1440;
            return $days === 1 ? '1 day' : "{$days} days";
        }
        if ($minutes >= 60 && $minutes % 60 === 0) {
            $hours = $minutes / 60;
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }
        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
