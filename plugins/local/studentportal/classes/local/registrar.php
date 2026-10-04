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
 * Registers students: matches each row (roll number, names, email) to an existing account or plans a new one,
 * then creates accounts with generated passwords and enrols everyone as students in the chosen courses.
 *
 * Passwords are generated with Moodle's password policy, set as "must change at first login", and only returned to
 * the caller for one-time display: they are stored hashed like any other password.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registrar {

    /** @var string Row will create a new account. */
    const ACTION_CREATE = 'create';
    /** @var string Row matches an existing account, which is enrolled. */
    const ACTION_ENROL = 'enrol';
    /** @var string Row matches an existing account already enrolled in every chosen course. */
    const ACTION_NOTHING = 'nothing';
    /** @var string Row cannot be processed. */
    const ACTION_ERROR = 'error';

    /** @var int[] Course ids. */
    protected $courseids;

    /**
     * @param int[] $courseids Courses to enrol the students in.
     */
    public function __construct(array $courseids) {
        $this->courseids = array_values(array_unique(array_map('intval', $courseids)));
    }

    /**
     * Rows of a CSV file: roll number, first name, last name, email (header row optional, any column order when it
     * has a header).
     *
     * @param string $content
     * @return array[] ['line' => int, 'idnumber', 'firstname', 'lastname', 'email']
     */
    public static function parse_csv(string $content): array {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $first = $lines ? $lines[0] : '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';'
            : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[$i + 1] = array_map('trim', str_getcsv($line, $delimiter, '"', '\\'));
        }
        if (!$rows) {
            return [];
        }
        // Columns by header names, else the fixed order roll number, first name, last name, email.
        $map = ['idnumber' => 0, 'firstname' => 1, 'lastname' => 2, 'email' => 3];
        $header = reset($rows);
        $patterns = [
            'idnumber' => '/^(roll|roll ?no|roll ?number|id|id ?number|student ?id|usn|reg(istration)? ?(no|number)?)\.?$/i',
            'firstname' => '/^(first ?name|given ?name|name)$/i',
            'lastname' => '/^(last ?name|surname|family ?name)$/i',
            'email' => '/^(e-?mail|email ?address|mail)$/i',
        ];
        $found = [];
        foreach ($header as $col => $cell) {
            foreach ($patterns as $field => $pattern) {
                if (!isset($found[$field]) && preg_match($pattern, trim($cell))) {
                    $found[$field] = $col;
                }
            }
        }
        if (isset($found['email'])) {
            $map = $found + ['idnumber' => null, 'firstname' => null, 'lastname' => null];
            array_shift($rows);
        } else if (array_filter($header, fn($c) => stripos($c, '@') !== false) === []) {
            // No email in the first row: an unrecognised header, skip it.
            array_shift($rows);
        }
        $out = [];
        foreach ($rows as $line => $cells) {
            $get = fn($field) => $map[$field] === null ? '' : trim($cells[$map[$field]] ?? '');
            $out[] = ['line' => $line, 'idnumber' => $get('idnumber'), 'firstname' => $get('firstname'),
                'lastname' => $get('lastname'), 'email' => \core_text::strtolower($get('email'))];
        }
        return $out;
    }

    /**
     * Username for a new account: the roll number if there is one, else the part of the email before "@".
     *
     * @param array $row
     * @return string
     */
    public static function username_for(array $row): string {
        $base = $row['idnumber'] !== '' ? $row['idnumber'] : strstr($row['email'], '@', true);
        return clean_param(\core_text::strtolower(preg_replace('/[^A-Za-z0-9._@-]/', '', (string) $base)), PARAM_USERNAME);
    }

    /**
     * Works out what will happen to each row, without changing anything.
     *
     * @param array[] $rows From parse_csv() or the single-student form.
     * @param bool $resetexisting Also give existing accounts a new password.
     * @return array[] Rows with 'action', 'message', 'username', 'userid' (existing account) added.
     */
    public function plan(array $rows, bool $resetexisting = false): array {
        global $DB, $CFG;
        $seen = ['email' => [], 'idnumber' => [], 'username' => []];
        $planned = [];
        foreach ($rows as $row) {
            $row += ['line' => 0, 'idnumber' => '', 'firstname' => '', 'lastname' => '', 'email' => ''];
            $row['resetpassword'] = false;
            $error = null;
            if ($row['email'] === '' || !validate_email($row['email'])) {
                $error = get_string('error_email', 'local_studentportal');
            } else if ($row['firstname'] === '' || $row['lastname'] === '') {
                $error = get_string('error_name', 'local_studentportal');
            } else if (isset($seen['email'][$row['email']])
                    || ($row['idnumber'] !== '' && isset($seen['idnumber'][\core_text::strtolower($row['idnumber'])]))) {
                $error = get_string('error_duplicaterow', 'local_studentportal', $seen['email'][$row['email']]
                    ?? $seen['idnumber'][\core_text::strtolower($row['idnumber'])]);
            }

            $existing = null;
            if (!$error) {
                [$existing, $error] = $this->match_existing($row);
            }
            if (!$error && !$existing) {
                $row['username'] = self::username_for($row);
                if ($row['username'] === '' || isset($seen['username'][$row['username']])
                        || $DB->record_exists('user', ['username' => $row['username'], 'mnethostid' => $CFG->mnet_localhost_id])) {
                    $error = get_string('error_username', 'local_studentportal', $row['username']);
                }
            }

            if ($error) {
                $row['action'] = self::ACTION_ERROR;
                $row['message'] = $error;
            } else if ($existing) {
                $row['userid'] = (int) $existing->id;
                $row['username'] = $existing->username;
                $missing = $this->courses_not_enrolled((int) $existing->id);
                $row['resetpassword'] = $resetexisting && $existing->auth === 'manual';
                $row['action'] = $missing || $row['resetpassword'] ? self::ACTION_ENROL : self::ACTION_NOTHING;
                $row['message'] = $missing
                    ? get_string('plan_enrolexisting', 'local_studentportal', fullname($existing))
                    : get_string('plan_alreadyenrolled', 'local_studentportal', fullname($existing));
                if ($row['resetpassword']) {
                    $row['message'] .= ' ' . get_string('plan_newpassword', 'local_studentportal');
                }
            } else {
                $row['action'] = self::ACTION_CREATE;
                $row['message'] = get_string('plan_create', 'local_studentportal', $row['username']);
            }
            if ($row['action'] !== self::ACTION_ERROR) {
                $seen['email'][$row['email']] = $row['line'];
                if ($row['idnumber'] !== '') {
                    $seen['idnumber'][\core_text::strtolower($row['idnumber'])] = $row['line'];
                }
                $seen['username'][$row['username']] = $row['line'];
            }
            $planned[] = $row;
        }
        return $planned;
    }

    /**
     * The existing account a row refers to: same roll number (ID number) or same email. Both must agree.
     *
     * @param array $row
     * @return array [\stdClass|null user, string|null error]
     */
    protected function match_existing(array $row): array {
        global $DB, $CFG;
        $fields = 'id, username, auth, email, idnumber, firstname, lastname, firstnamephonetic, lastnamephonetic, '
            . 'middlename, alternatename, suspended';
        $base = ['deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id];
        $byemail = $DB->get_records_select('user', 'deleted = 0 AND mnethostid = :host AND LOWER(email) = :email',
            ['host' => $CFG->mnet_localhost_id, 'email' => $row['email']], 'id', $fields);
        $byid = $row['idnumber'] === '' ? [] : $DB->get_records('user', $base + ['idnumber' => $row['idnumber']], 'id', $fields);
        if (count($byemail) > 1 || count($byid) > 1) {
            return [null, get_string('error_ambiguous', 'local_studentportal')];
        }
        $a = $byemail ? reset($byemail) : null;
        $b = $byid ? reset($byid) : null;
        if ($a && $b && $a->id != $b->id) {
            return [null, get_string('error_conflict', 'local_studentportal')];
        }
        $user = $a ?: $b;
        if ($user && $user->suspended) {
            return [null, get_string('error_suspended', 'local_studentportal', $user->username)];
        }
        if ($user && (is_siteadmin($user) || isguestuser($user))) {
            return [null, get_string('error_privileged', 'local_studentportal')];
        }
        return [$user, null];
    }

    /**
     * Chosen courses the user is not actively enrolled in.
     *
     * @param int $userid
     * @return int[]
     */
    protected function courses_not_enrolled(int $userid): array {
        return array_values(array_filter($this->courseids,
            fn($courseid) => !is_enrolled(\context_course::instance($courseid), $userid, '', true)));
    }

    /**
     * Carries out a plan: creates accounts, resets passwords where asked, enrols as students.
     *
     * @param array[] $planned From plan().
     * @return array[] One per processed row: username, password (new accounts / resets, else null), fullname, email,
     *     idnumber, userid, created (bool), courses (names).
     */
    public function execute(array $planned): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $manual = enrol_get_plugin('manual');
        $instances = [];
        $coursenames = [];
        foreach ($this->courseids as $courseid) {
            $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
            if (!$instance) {
                $course = get_course($courseid);
                $instance = $DB->get_record('enrol', ['id' => $manual->add_default_instance($course)]);
            }
            $instances[$courseid] = $instance;
            $coursenames[$courseid] = format_string(get_course($courseid)->fullname);
        }

        $results = [];
        foreach ($planned as $row) {
            if (!in_array($row['action'], [self::ACTION_CREATE, self::ACTION_ENROL, self::ACTION_NOTHING], true)) {
                continue;
            }
            $password = null;
            if ($row['action'] === self::ACTION_CREATE) {
                $password = self::new_password();
                $user = (object) [
                    'username' => $row['username'],
                    'auth' => 'manual',
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id,
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'email' => $row['email'],
                    'idnumber' => $row['idnumber'],
                    'password' => $password,
                    'lang' => $CFG->lang,
                    'timezone' => '99',
                ];
                $userid = user_create_user($user, true, true);
                set_user_preference('auth_forcepasswordchange', 1, $userid);
                $created = true;
            } else {
                $userid = $row['userid'];
                $created = false;
                if (!empty($row['resetpassword'])) {
                    $password = self::reset_password($userid);
                }
            }
            $enrolled = [];
            foreach ($instances as $courseid => $instance) {
                if (!is_enrolled(\context_course::instance($courseid), $userid, '', true)) {
                    $manual->enrol_user($instance, $userid, $studentrole);
                }
                $enrolled[] = $coursenames[$courseid];
            }
            $user = \core_user::get_user($userid);
            $results[] = [
                'userid' => (int) $userid,
                'username' => $user->username,
                'password' => $password,
                'fullname' => fullname($user),
                'firstname' => $user->firstname,
                'email' => $user->email,
                'idnumber' => $user->idnumber,
                'created' => $created,
                'courses' => $enrolled,
            ];
        }
        return $results;
    }

    /**
     * A new password that satisfies the site's password policy, avoiding look-alike characters.
     *
     * @return string
     */
    public static function new_password(): string {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '#%*+-=?@'];
        $all = implode('', $sets);
        do {
            $chars = [];
            foreach ($sets as $set) {
                $chars[] = $set[random_int(0, strlen($set) - 1)];
            }
            while (count($chars) < 10) {
                $chars[] = $all[random_int(0, strlen($all) - 1)];
            }
            shuffle($chars);
            $password = implode('', $chars);
            $errmsg = '';
        } while (!check_password_policy($password, $errmsg));
        return $password;
    }

    /**
     * Gives an existing manual account a new generated password it must change at next login.
     *
     * @param int $userid
     * @return string The new password (for one-time display).
     */
    public static function reset_password(int $userid): string {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        if ($user->auth !== 'manual' || is_siteadmin($user) || isguestuser($user)) {
            throw new \moodle_exception('error_cannotreset', 'local_studentportal');
        }
        $password = self::new_password();
        update_internal_user_password($user, $password);
        set_user_preference('auth_forcepasswordchange', 1, $userid);
        \core\session\manager::kill_user_sessions($userid);
        return $password;
    }
}
