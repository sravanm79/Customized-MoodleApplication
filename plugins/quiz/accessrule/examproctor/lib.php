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
 * Library functions for quizaccess_examproctor.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves recorded webcam video to users who can view the quiz reports.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args itemid (attempt id), filename
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file is not found
 */
function quizaccess_examproctor_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload,
        array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== \quizaccess_examproctor\local\video::FILEAREA) {
        return false;
    }
    require_login($course, false, $cm);
    require_capability('mod/quiz:viewreports', $context);

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $file = get_file_storage()->get_file($context->id, 'quizaccess_examproctor', $filearea, $itemid, '/', $filename);
    if (!$file) {
        return false;
    }
    // Byte ranges let the browser's video player seek.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
