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

namespace local_studentportal\local;

/**
 * One batch of freshly issued credentials, kept only in the issuing admin's session for a short time so they can be
 * shown, downloaded, printed and emailed. Nothing is written to the database or disk.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {

    /** @var int Seconds a batch stays available. */
    const LIFETIME = 15 * MINSECS;

    /**
     * Stores a batch and returns its key.
     *
     * @param array[] $results From registrar::execute().
     * @return string
     */
    public static function store(array $results): string {
        global $SESSION;
        self::expire();
        $key = random_string(16);
        $SESSION->local_studentportal_batches[$key] = ['time' => time(), 'results' => $results];
        return $key;
    }

    /**
     * A stored batch, or null when it expired or does not exist.
     *
     * @param string $key
     * @return array[]|null
     */
    public static function get(string $key): ?array {
        global $SESSION;
        self::expire();
        return $SESSION->local_studentportal_batches[$key]['results'] ?? null;
    }

    /**
     * Removes a batch (after "Done" or when the admin leaves).
     *
     * @param string $key
     */
    public static function forget(string $key): void {
        global $SESSION;
        unset($SESSION->local_studentportal_batches[$key]);
    }

    /**
     * Drops expired batches.
     */
    protected static function expire(): void {
        global $SESSION;
        foreach ($SESSION->local_studentportal_batches ?? [] as $key => $batch) {
            if ($batch['time'] < time() - self::LIFETIME) {
                unset($SESSION->local_studentportal_batches[$key]);
            }
        }
    }

    /**
     * The login address students are given.
     *
     * @return string
     */
    public static function login_url(): string {
        return (new \moodle_url('/login/index.php'))->out(false);
    }

    /**
     * Where students download the LMS certificate (plain http on port 9999, see docs/https-and-student-devices.md).
     *
     * @return string
     */
    public static function certificate_url(): string {
        global $CFG;
        $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
        return 'http://' . $host . ':9999/lms-ca.crt';
    }

    /**
     * CSV of a batch (sent as a download).
     *
     * @param array[] $results
     * @return string
     */
    public static function csv(array $results): string {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Roll number', 'Name', 'Email', 'Username', 'Password', 'Courses', 'Login'], ',', '"', '\\');
        foreach ($results as $r) {
            fputcsv($out, [$r['idnumber'], $r['fullname'], $r['email'], $r['username'],
                $r['password'] ?? get_string('passwordunchanged', 'local_studentportal'),
                implode('; ', $r['courses']), self::login_url()], ',', '"', '\\');
        }
        rewind($out);
        return "\xEF\xBB\xBF" . stream_get_contents($out);
    }

    /**
     * Emails each student their own credentials (or, for existing accounts without a new password, the courses they
     * were added to).
     *
     * @param array[] $results
     * @return array ['sent' => int, 'failed' => string[] names]
     */
    public static function email(array $results): array {
        global $SITE;
        $from = \core_user::get_noreply_user();
        $sent = 0;
        $failed = [];
        foreach ($results as $r) {
            $user = \core_user::get_user($r['userid']);
            // Moodle never emails the reserved .invalid domain (it silently reports success), so say so instead.
            if (!$user || $user->deleted || $user->suspended || substr($user->email, -8) === '.invalid') {
                $failed[] = $r['fullname'];
                continue;
            }
            $a = (object) [
                'firstname' => $user->firstname,
                'site' => format_string($SITE->fullname),
                'courses' => implode(', ', $r['courses']),
                'username' => $r['username'],
                'password' => $r['password'],
                'loginurl' => self::login_url(),
                'certurl' => self::certificate_url(),
            ];
            $key = $r['password'] === null ? 'emailenrolled' : 'emailcredentials';
            $subject = get_string($key . 'subject', 'local_studentportal', $a);
            $text = get_string($key . 'body', 'local_studentportal', $a);
            $html = text_to_html(s($text), false, false, true);
            if (email_to_user($user, $from, $subject, $text, $html)) {
                $sent++;
            } else {
                $failed[] = $r['fullname'];
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }
}
