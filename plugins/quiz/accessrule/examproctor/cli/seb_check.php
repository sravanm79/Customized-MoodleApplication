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
 * Troubleshoot Safe Exam Browser + webcam proctoring for one quiz, or every SEB quiz.
 *
 * Run as the web server user (daemon in the Bitnami image), never as root:
 *   php mod/quiz/accessrule/examproctor/cli/seb_check.php --cmid=10 --url=https://moodle.example.edu
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_quiz\quiz_settings;
use quizaccess_examproctor\local\seb_diagnostics;

[$options, $unrecognised] = cli_get_params([
    'cmid' => 0,
    'all' => false,
    'url' => '',
    'purge' => false,
    'xml' => false,
    'help' => false,
], ['h' => 'help']);

$help = <<<EOT
Check Safe Exam Browser + webcam proctoring settings for a quiz.

Options:
  --cmid=N     Course module id of the quiz (the id= in mod/quiz/view.php?id=N).
  --all        Check every quiz that requires Safe Exam Browser.
  --url=URL    The address students use (e.g. https://moodle.example.edu). Defaults to \$CFG->wwwroot.
  --purge      Purge the quizaccess_seb caches first, so the .seb files and config keys are rebuilt for the
               current \$CFG->wwwroot. Do this after changing wwwroot or the SEB settings.
  --xml        Also print the generated .seb configuration (the file students download).
  -h, --help   Show this help.

Exit code: 0 all OK, 1 warnings, 2 errors.

EOT;

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help'] || (!$options['cmid'] && !$options['all'])) {
    echo $help;
    exit(0);
}

if ($options['purge']) {
    foreach (['config', 'configkey', 'quizsettings'] as $area) {
        cache::make('quizaccess_seb', $area)->purge();
    }
    cli_writeln('Purged quizaccess_seb caches (config, configkey, quizsettings).');
}

$cmids = [];
if ($options['all']) {
    $cmids = $DB->get_fieldset_sql("SELECT cm.id
                                      FROM {quizaccess_seb_quizsettings} s
                                      JOIN {course_modules} cm ON cm.id = s.cmid
                                     WHERE s.requiresafeexambrowser > 0
                                  ORDER BY cm.id");
    if (!$cmids) {
        cli_writeln('No quiz requires Safe Exam Browser.');
        exit(0);
    }
} else {
    $cmids = [(int) $options['cmid']];
}

$clientorigin = $options['url'] ? seb_diagnostics::origin($options['url']) : null;
if ($options['url'] && !$clientorigin) {
    cli_error('--url must be an absolute URL, e.g. https://moodle.example.edu');
}

$marks = ['ok' => '[ OK  ]', 'warn' => '[WARN ]', 'error' => '[ERROR]', 'info' => '[INFO ]'];
$worst = 'ok';

$dynamic = seb_diagnostics::wwwroot_is_dynamic();
cli_writeln('Site wwwroot: ' . $CFG->wwwroot . ($dynamic ? '  (derived from the Host header; the CLI fallback is not what students see)' : ''));
if ($dynamic && !$clientorigin) {
    cli_writeln('Hint: wwwroot is dynamic, so pass --url=<the address students use> for a meaningful check.');
}
cli_writeln('Students use: ' . ($clientorigin ?: seb_diagnostics::origin($CFG->wwwroot)));

foreach ($cmids as $cmid) {
    [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
    $quiz = quiz_settings::create($cm->instance)->get_quiz();
    $diagnostics = new seb_diagnostics($quiz, $cm->id);

    cli_writeln('');
    cli_heading(format_string($quiz->name) . " (cmid {$cm->id}, quiz {$quiz->id}, course {$course->shortname})");

    $checks = $diagnostics->run($clientorigin);
    foreach ($checks as $check) {
        cli_writeln($marks[$check['status']] . ' ' . html_to_text($check['message'], 0, false));
    }

    $config = $diagnostics->seb_config();
    if ($config && empty($config['error'])) {
        cli_writeln('');
        cli_writeln('  startURL:            ' . $config['starturl']);
        cli_writeln('  config key:          ' . $config['configkey']);
        cli_writeln('  camera / microphone: ' . var_export((bool) $config['camera'], true) . ' / '
                . var_export((bool) $config['microphone'], true));
        cli_writeln('  URL filter:          ' . ($config['urlfilter'] ? count($config['urlfilterrules']) . ' rule(s)' : 'off'));
        foreach ($config['urlfilterrules'] as $rule) {
            $rule = (array) $rule;
            cli_writeln('    ' . (!empty($rule['action']) ? 'allow ' : 'block ') . (!empty($rule['regex']) ? 'regex ' : '')
                    . $rule['expression'] . (empty($rule['active']) ? ' (inactive)' : ''));
        }
        cli_writeln('  Expected page key for view.php: hash("' . $config['starturl'] . '" . configkey) = '
                . hash('sha256', $config['starturl'] . $config['configkey']));
        if ($options['xml']) {
            cli_writeln('');
            cli_writeln(\quizaccess_seb\seb_quiz_settings::get_config_by_quiz_id($quiz->id));
        }
    }

    $overall = seb_diagnostics::overall($checks);
    if ($overall === 'error' || ($overall === 'warn' && $worst === 'ok')) {
        $worst = $overall;
    }
}

if ($dynamic) {
    // Reading the config above cached a .seb file and key built for the CLI's own wwwroot. Drop them so the next
    // web request rebuilds them for its host, instead of serving students a config for the wrong address.
    cache::make('quizaccess_seb', 'config')->purge();
    cache::make('quizaccess_seb', 'configkey')->purge();
}

cli_writeln('');
cli_writeln('Overall: ' . strtoupper($worst));
exit(['ok' => 0, 'warn' => 1, 'error' => 2][$worst]);
