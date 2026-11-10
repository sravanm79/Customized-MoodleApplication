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
 * Plagiarism plugin: similarity check of assignment submissions against classmates' submissions.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/lib.php');

use plagiarism_similarity\local\checker;

/**
 * Hooks called by the assignment.
 */
class plagiarism_plugin_similarity extends plagiarism_plugin {

    /** @var array Users whose badge was already shown on this page, by course module. */
    protected static $shown = [];

    /**
     * Similarity badge beside a student's submission (grading table, submission view), for teachers; once per student.
     *
     * @param array $linkarray userid, cmid, and file or content
     * @return string
     */
    public function get_links($linkarray) {
        global $DB;
        $cmid = (int) ($linkarray['cmid'] ?? 0);
        $userid = (int) ($linkarray['userid'] ?? 0);
        if (!$cmid || !$userid || isset(self::$shown[$cmid][$userid]) || !($cfg = checker::config($cmid))) {
            return '';
        }
        $context = context_module::instance($cmid);
        if (!has_capability('plagiarism/similarity:viewreport', $context)) {
            return '';
        }
        self::$shown[$cmid][$userid] = true;

        $row = $DB->get_record('plagiarism_similarity_sub', ['cmid' => $cmid, 'userid' => $userid]);
        $reporturl = new moodle_url('/plagiarism/similarity/report.php', ['cmid' => $cmid]);
        if (!$row || $row->status === 'pending') {
            $badge = html_writer::span(get_string('badge_pending', 'plagiarism_similarity'), 'badge bg-secondary');
            return html_writer::div(html_writer::link($reporturl, $badge), 'plagiarism-similarity');
        }
        if ($row->status !== 'done') {
            $badge = html_writer::span(get_string('status_' . $row->status, 'plagiarism_similarity'), 'badge bg-secondary');
            return html_writer::div(html_writer::link($reporturl, $badge), 'plagiarism-similarity');
        }
        $badge = html_writer::span(get_string('badge_score', 'plagiarism_similarity', format_float($row->score, 0)),
            'badge ' . self::badge_class((float) $row->score, (int) $cfg->threshold));
        $url = $row->matchuserid
            ? new moodle_url('/plagiarism/similarity/compare.php', ['cmid' => $cmid, 'a' => $userid, 'b' => $row->matchuserid])
            : $reporturl;
        return html_writer::div(html_writer::link($url, $badge,
            ['title' => get_string('badge_title', 'plagiarism_similarity')]), 'plagiarism-similarity');
    }

    /**
     * Tells students their submission is compared with classmates' submissions.
     *
     * @param int $cmid
     * @return string
     */
    public function print_disclosure($cmid) {
        global $OUTPUT;
        if (!checker::config((int) $cmid)) {
            return '';
        }
        return $OUTPUT->notification(get_string('disclosure', 'plagiarism_similarity'), 'info', false);
    }

    /**
     * Badge colour: red at or above the assignment's threshold, amber from half of it.
     *
     * @param float $score
     * @param int $threshold
     * @return string
     */
    public static function badge_class(float $score, int $threshold): string {
        if ($score >= $threshold) {
            return 'bg-danger';
        }
        return $score >= $threshold / 2 ? 'bg-warning text-dark' : 'bg-success';
    }
}

/**
 * "Similarity check" section in the assignment settings.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function plagiarism_similarity_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;
    if ($formwrapper->get_current()->modulename !== 'assign' || !get_config('plagiarism_similarity', 'enabled')
            || empty(get_config('core', 'enableplagiarism'))) {
        return;
    }
    $cm = $formwrapper->get_coursemodule();
    $context = $cm ? context_module::instance($cm->id) : $formwrapper->get_context();
    if (!has_capability('plagiarism/similarity:enable', $context)) {
        return;
    }
    $cfg = $cm ? $DB->get_record('plagiarism_similarity_cfg', ['cmid' => $cm->id]) : null;

    $mform->addElement('header', 'plagiarism_similarity_header', get_string('formheader', 'plagiarism_similarity'));
    $mform->addElement('selectyesno', 'similarity_enabled', get_string('formenabled', 'plagiarism_similarity'));
    $mform->addHelpButton('similarity_enabled', 'formenabled', 'plagiarism_similarity');
    $mform->setDefault('similarity_enabled', $cfg ? (int) $cfg->enabled
        : (int) get_config('plagiarism_similarity', 'defaulton'));

    $mform->addElement('text', 'similarity_threshold', get_string('formthreshold', 'plagiarism_similarity'), ['size' => 4]);
    $mform->setType('similarity_threshold', PARAM_INT);
    $mform->addHelpButton('similarity_threshold', 'formthreshold', 'plagiarism_similarity');
    $mform->setDefault('similarity_threshold', $cfg ? (int) $cfg->threshold
        : ((int) get_config('plagiarism_similarity', 'threshold') ?: 40));
    $mform->hideIf('similarity_threshold', 'similarity_enabled', 'eq', 0);

    $mform->addElement('textarea', 'similarity_ignoretext', get_string('formignore', 'plagiarism_similarity'),
        ['rows' => 5, 'cols' => 60, 'class' => 'text-monospace']);
    $mform->setType('similarity_ignoretext', PARAM_RAW);
    $mform->addHelpButton('similarity_ignoretext', 'formignore', 'plagiarism_similarity');
    $mform->setDefault('similarity_ignoretext', $cfg ? (string) $cfg->ignoretext : '');
    $mform->hideIf('similarity_ignoretext', 'similarity_enabled', 'eq', 0);

    if ($cfg && $cfg->enabled) {
        $mform->addElement('static', 'similarity_report', '', html_writer::link(
            new moodle_url('/plagiarism/similarity/report.php', ['cmid' => $cm->id]),
            get_string('reporttitle', 'plagiarism_similarity')));
    }
}

/**
 * Saves the "Similarity check" settings and checks the existing submissions.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function plagiarism_similarity_coursemodule_edit_post_actions($data, $course) {
    if (($data->modulename ?? '') !== 'assign' || !isset($data->similarity_enabled)) {
        return $data;
    }
    checker::save_config((int) $data->coursemodule, (bool) $data->similarity_enabled,
        (int) ($data->similarity_threshold ?? 40), (string) ($data->similarity_ignoretext ?? ''));
    if ($data->similarity_enabled) {
        checker::queue((int) $data->coursemodule);
    }
    return $data;
}
