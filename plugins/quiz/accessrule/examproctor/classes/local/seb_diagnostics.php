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

namespace quizaccess_examproctor\local;

/**
 * Server-side checks for a quiz that combines Safe Exam Browser with webcam proctoring.
 *
 * Shared by the student pre-check page (check.php) and the admin CLI (cli/seb_check.php). Every check
 * returns ['id', 'status' (ok|warn|error|info), 'message'], so both front ends only format the list.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seb_diagnostics {

    /** @var string[] Human names of the quizaccess_seb "Require the use of Safe Exam Browser" modes. */
    const MODES = [0 => 'no', 1 => 'manual', 2 => 'template', 3 => 'upload', 4 => 'clientconfig'];

    /** @var \stdClass Quiz record. */
    protected $quiz;

    /** @var int Course module id. */
    protected $cmid;

    /**
     * @param \stdClass $quiz quiz record
     * @param int $cmid course module id
     */
    public function __construct(\stdClass $quiz, int $cmid) {
        $this->quiz = $quiz;
        $this->cmid = $cmid;
    }

    /**
     * The quizaccess_seb mode for this quiz (0 = SEB not required).
     *
     * @return int
     */
    public function seb_mode(): int {
        if (!class_exists('\quizaccess_seb\seb_quiz_settings')) {
            return 0;
        }
        $settings = \quizaccess_seb\seb_quiz_settings::get_by_quiz_id($this->quiz->id);
        return $settings ? (int) $settings->get('requiresafeexambrowser') : 0;
    }

    /**
     * Does a webcam rule (quizaccess_proctoring) require the camera on this quiz?
     *
     * @return bool
     */
    public function camera_proctoring_enabled(): bool {
        global $DB;
        $manager = $DB->get_manager();
        if (!$manager->table_exists('quizaccess_proctoring')) {
            return false;
        }
        return $DB->record_exists('quizaccess_proctoring', ['quizid' => $this->quiz->id, 'proctoringrequired' => 1]);
    }

    /**
     * Read the generated SEB configuration (the .seb file students download) as an array of the keys that matter.
     *
     * Returns null when SEB is off or the mode does not produce a Moodle-side config (client configuration).
     *
     * @return array|null
     */
    public function seb_config(): ?array {
        if (!in_array($this->seb_mode(), [1, 2, 3])) {
            return null;
        }
        try {
            $xml = \quizaccess_seb\seb_quiz_settings::get_config_by_quiz_id($this->quiz->id);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
        if (empty($xml)) {
            return null;
        }
        $plist = new \quizaccess_seb\property_list($xml);
        $value = function(string $key) use ($plist) {
            try {
                return $plist->get_element_value($key);
            } catch (\Throwable $e) {
                return null;
            }
        };
        $rules = [];
        foreach ((array) $value('URLFilterRules') as $rule) {
            $rules[] = $rule;
        }
        return [
            'starturl' => (string) $value('startURL'),
            'camera' => $value('browserMediaCaptureCamera'),
            'microphone' => $value('browserMediaCaptureMicrophone'),
            'urlfilter' => $value('URLFilterEnable'),
            'urlfilterrules' => $rules,
            'allowquit' => $value('allowQuit'),
            'sendbrowserexamkey' => $value('sendBrowserExamKey'),
            'configkey' => \quizaccess_seb\seb_quiz_settings::get_config_key_by_quiz_id($this->quiz->id),
        ];
    }

    /**
     * Origin (scheme://host[:port]) of a URL.
     *
     * @param string $url
     * @return string
     */
    public static function origin(string $url): string {
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }
        $origin = strtolower(($parts['scheme'] ?? 'http') . '://' . $parts['host']);
        return isset($parts['port']) ? $origin . ':' . $parts['port'] : $origin;
    }

    /**
     * Is wwwroot fixed in config.php, or derived from the request Host header?
     *
     * A per-request wwwroot is the classic cause of "config key mismatch": quizaccess_seb caches one .seb file
     * and one config key per quiz, built for whichever host name generated it first.
     *
     * @return bool
     */
    public static function wwwroot_is_dynamic(): bool {
        global $CFG;
        $file = $CFG->dirroot . '/config.php';
        if (!is_readable($file)) {
            return false;
        }
        $source = file_get_contents($file);
        return (bool) preg_match('/\$CFG->wwwroot\s*=\s*[^;]*\$_SERVER/', $source);
    }

    /**
     * Run all server-side checks.
     *
     * @param string|null $clientorigin origin the student is actually using (null in CLI)
     * @return array[] list of ['id', 'status', 'message']
     */
    public function run(?string $clientorigin = null): array {
        global $CFG;
        $checks = [];
        $add = function(string $id, string $status, string $stringkey, $a = null) use (&$checks) {
            $checks[] = ['id' => $id, 'status' => $status,
                'message' => get_string('diag_' . $stringkey, 'quizaccess_examproctor', $a)];
        };

        $mode = $this->seb_mode();
        $camera = $this->camera_proctoring_enabled();
        $add('sebmode', 'info', 'sebmode', get_string('sebmode_' . self::MODES[$mode] , 'quizaccess_examproctor'));
        $add('camerarequired', 'info', $camera ? 'camerarequired' : 'cameranotrequired');

        // Webcam access needs a secure context: HTTPS, or the machine's own localhost.
        $wwwroot = self::origin($CFG->wwwroot);
        $securehost = in_array(parse_url($wwwroot, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1']);
        if (strpos($wwwroot, 'https://') === 0) {
            $add('https', 'ok', 'httpsok');
        } else if ($securehost) {
            $add('https', $camera ? 'warn' : 'info', 'httpslocal', $wwwroot);
        } else {
            $add('https', $camera ? 'error' : 'warn', 'httpsmissing', $wwwroot);
        }

        if ($mode === 0) {
            return $checks;
        }

        if (self::wwwroot_is_dynamic()) {
            $add('wwwroot', 'warn', 'wwwrootdynamic');
        } else {
            $add('wwwroot', 'ok', 'wwwrootfixed', $wwwroot);
        }

        $config = $this->seb_config();
        if ($config && !empty($config['error'])) {
            $add('config', 'error', 'configerror', $config['error']);
            return $checks;
        }
        if ($config) {
            // The .seb file and its key are cached per quiz: they must have been built for the host students use.
            $configorigin = self::origin($config['starturl']);
            $expected = $clientorigin ?: $wwwroot;
            if ($configorigin !== $expected) {
                $add('starturl', 'error', 'starturlmismatch', (object) ['config' => $configorigin, 'actual' => $expected]);
            } else {
                $add('starturl', 'ok', 'starturlok', $configorigin);
            }

            if ($camera) {
                $add('sebcamera', $config['camera'] ? 'ok' : 'error', $config['camera'] ? 'sebcameraok' : 'sebcameraoff');
            } else if ($config['camera']) {
                $add('sebcamera', 'info', 'sebcameraunused');
            }

            if ($config['urlfilter']) {
                $allowed = false;
                foreach ($config['urlfilterrules'] as $rule) {
                    $rule = (array) $rule;
                    if (!empty($rule['active']) && !empty($rule['action'])
                            && strpos($wwwroot, preg_replace('#^\w+://#', '', rtrim($rule['expression'], '/*'))) !== false) {
                        $allowed = true;
                    }
                }
                $add('urlfilter', $allowed ? 'ok' : 'warn', $allowed ? 'urlfilterok' : 'urlfiltermissing', $wwwroot);
            }

            if (!$config['allowquit']) {
                $add('allowquit', 'info', 'allowquitoff');
            }
        }

        if (!empty($this->quiz->examproctor_requirefullscreen) || !empty($this->quiz->examproctor_detectblur)) {
            $add('examproctor', 'info', 'examproctorseb');
        }
        if (!get_config('quizaccess_seb', 'autoreconfigureseb')) {
            $add('autoreconfigure', 'warn', 'autoreconfigureoff');
        }
        return $checks;
    }

    /**
     * Summarise a list of checks to one status.
     *
     * @param array[] $checks
     * @return string error|warn|ok
     */
    public static function overall(array $checks): string {
        $statuses = array_column($checks, 'status');
        if (in_array('error', $statuses)) {
            return 'error';
        }
        return in_array('warn', $statuses) ? 'warn' : 'ok';
    }
}
