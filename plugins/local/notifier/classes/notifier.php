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

    /** Reminder types: site setting <type>_lead_times and course override column <type>_lead_times. */
    const TYPES = ['assign', 'quiz', 'live'];

    /** @var array Course overrides loaded in this request, by course id (false when the course has none). */
    protected static $courseoverrides = [];

    /**
     * Lead times for a reminder type: the course's own, else the site default.
     *
     * @param string $type  'assign' | 'quiz' | 'live' (Zoom and Google Meet classes)
     * @param int    $courseid  0 for the site default.
     * @return int[]  Sorted descending list of minute values (e.g. [1440, 60]); empty means no reminders.
     */
    public static function get_lead_minutes(string $type, int $courseid = 0): array {
        $override = $courseid ? self::course_override($courseid) : null;
        $column = $type . '_lead_times';
        if ($override && $override->$column !== null && $override->$column !== '') {
            return self::parse_leads($override->$column);
        }
        return self::parse_leads(get_config('local_notifier', $column) ?: '');
    }

    /**
     * Parses "1440, 60" into [1440, 60]; "0" or "off" gives [] (no reminders).
     *
     * @param string $raw
     * @return int[]
     */
    public static function parse_leads(string $raw): array {
        $mins = [];
        foreach (explode(',', $raw) as $part) {
            $v = (int) trim($part);
            if ($v > 0) {
                $mins[$v] = $v;
            }
        }
        rsort($mins);
        return array_values($mins);
    }

    /**
     * Whether a lead-time field is valid: empty, "0"/"off", or comma-separated positive minutes.
     *
     * @param string $raw
     * @return bool
     */
    public static function valid_leads(string $raw): bool {
        $raw = trim($raw);
        return $raw === '' || strtolower($raw) === 'off' || preg_match('/^\d+(\s*,\s*\d+)*$/', $raw);
    }

    /**
     * The course's lead-time overrides, or null.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function course_override(int $courseid): ?\stdClass {
        global $DB;
        if (!array_key_exists($courseid, self::$courseoverrides)) {
            self::$courseoverrides[$courseid] = $DB->get_record('local_notifier_course', ['courseid' => $courseid]) ?: false;
        }
        return self::$courseoverrides[$courseid] ?: null;
    }

    /**
     * Saves the course's lead-time overrides (empty fields fall back to the site default).
     *
     * @param int $courseid
     * @param array $leads type => raw text
     */
    public static function save_course_override(int $courseid, array $leads): void {
        global $DB;
        $record = $DB->get_record('local_notifier_course', ['courseid' => $courseid]) ?: (object) ['courseid' => $courseid];
        foreach (self::TYPES as $type) {
            $raw = trim($leads[$type] ?? '');
            $record->{$type . '_lead_times'} = $raw === '' ? null : (strtolower($raw) === 'off' ? '0' : $raw);
        }
        $record->timemodified = time();
        if (!empty($record->id)) {
            $DB->update_record('local_notifier_course', $record);
        } else {
            $DB->insert_record('local_notifier_course', $record);
        }
        unset(self::$courseoverrides[$courseid]);
    }

    /**
     * Whether we have already sent this exact notification.
     *
     * @param int    $userid
     * @param string $component  'assign'|'quiz'|'zoom'|'googlemeet'
     * @param int    $instanceid  cmid for assign/quiz; calendar event id for live classes (one per occurrence)
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
