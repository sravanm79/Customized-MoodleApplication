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
 * Dev tool: compile theme_iiitdwd SCSS the way theme_config does. Not part of the deployed plugin.
 *
 * Usage: php compile-check.php <moodle dirroot> <theme copy dir>
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require($argv[1] . '/config.php');

$dir = $argv[2];
$compiler = new core_scss();
$compiler->setImportPaths([$dir . '/scss', $CFG->dirroot . '/theme/boost/scss']);
$compiler->append_raw_scss(file_get_contents($dir . '/scss/preset/default.scss') . "\n" .
    file_get_contents($dir . '/scss/styles.scss'));

try {
    $css = $compiler->to_css();
} catch (Exception $e) {
    fwrite(STDERR, 'SCSS compile failed: ' . $e->getMessage() . "\n");
    exit(1);
}
// An @import that cannot be resolved inside theme/ is skipped silently, so check each partial's output is there.
foreach (['teacher_dashboard' => '.iiitdwd-tdash', 'course' => '.iiitdwd-course-hero', 'calendar' => '.iiitdwd-cal-layout',
        'elements' => '.errorbox.alert-danger', 'shell' => '.iiitdwd-app-sidebar'] as $file => $selector) {
    if (strpos($css, $selector) === false) {
        fwrite(STDERR, "SCSS compile incomplete: styles from scss/$file.scss missing (skipped @import?)\n");
        exit(1);
    }
}
// Editor (TinyMCE content) CSS, compiled on its own like theme_config::editor_scss_to_css().
$editor = new core_scss();
$editor->set_file($dir . '/scss/editor.scss');
try {
    $editorcss = $editor->to_css();
} catch (Exception $e) {
    fwrite(STDERR, 'Editor SCSS compile failed: ' . $e->getMessage() . "\n");
    exit(1);
}
if (strpos($editorcss, '#0c182b') === false) {
    fwrite(STDERR, "Editor SCSS compile incomplete: design tokens missing (skipped @import?)\n");
    exit(1);
}
echo 'SCSS OK: ' . round(strlen($css) / 1024) . ' KB of CSS, editor ' . round(strlen($editorcss) / 1024, 1) . " KB\n";
