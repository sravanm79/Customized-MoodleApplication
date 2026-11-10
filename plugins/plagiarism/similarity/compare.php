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
 * Two submissions side by side, passages they share highlighted.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use plagiarism_similarity\local\checker;
use plagiarism_similarity\local\extractor;
use plagiarism_similarity\local\fingerprint;

$cmid = required_param('cmid', PARAM_INT);
$a = required_param('a', PARAM_INT);
$b = required_param('b', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('plagiarism/similarity:viewreport', $context);
$cfg = checker::config($cmid);
if (!$cfg) {
    throw new moodle_exception('notenabled', 'plagiarism_similarity');
}

$url = new moodle_url('/plagiarism/similarity/compare.php', ['cmid' => $cmid, 'a' => $a, 'b' => $b]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('comparetitle', 'plagiarism_similarity'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

$assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, intro', MUST_EXIST);
$sides = [];
foreach ([$a, $b] as $userid) {
    $submission = $DB->get_record_select('assign_submission', 'assignment = ? AND userid = ? AND latest = 1',
        [$assign->id, $userid], '*', MUST_EXIST);
    $sides[$userid] = [
        'user' => core_user::get_user($userid, '*', MUST_EXIST),
        'text' => extractor::extract($context, $submission)['text'],
        'row' => $DB->get_record('plagiarism_similarity_sub', ['cmid' => $cmid, 'userid' => $userid]),
    ];
}

// Passages the score ignores (starter code, assignment text, class-wide boilerplate) are not highlighted.
$fps = array_map(fn($r) => fingerprint::decode($r->fingerprint),
    $DB->get_records('plagiarism_similarity_sub', ['cmid' => $cmid], '', 'userid, fingerprint'));
$ignore = array_fill_keys(array_keys(checker::ignored($cfg, $assign, $fps)), true);

/**
 * Text with the given byte ranges wrapped in <mark>.
 *
 * @param string $text
 * @param array $ranges
 * @return string HTML
 */
function plagiarism_similarity_highlight(string $text, array $ranges): string {
    $html = '';
    $pos = 0;
    foreach ($ranges as [$start, $end]) {
        $html .= s(substr($text, $pos, $start - $pos)) . '<mark>' . s(substr($text, $start, $end - $start)) . '</mark>';
        $pos = $end;
    }
    return $html . s(substr($text, $pos));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('comparetitle', 'plagiarism_similarity') . ': ' . format_string($cm->name));
echo html_writer::link(new moodle_url('/plagiarism/similarity/report.php', ['cmid' => $cmid]),
    get_string('backtoreport', 'plagiarism_similarity'));
echo html_writer::tag('p', get_string('compareintro', 'plagiarism_similarity'), ['class' => 'text-muted mt-2']);

echo html_writer::start_div('row g-3 plagiarism-similarity-compare');
[$first, $second] = array_values($sides);
foreach ([[$first, $second], [$second, $first]] as [$side, $other]) {
    $row = $side['row'];
    $score = $row && $row->status === 'done' && (int) $row->matchuserid === (int) $other['user']->id
        ? ' · ' . get_string('badge_score', 'plagiarism_similarity', format_float($row->score, 0)) : '';
    echo html_writer::start_div('col-lg-6');
    echo html_writer::tag('h3', fullname($side['user']) . $score, ['class' => 'h5']);
    echo html_writer::tag('pre', plagiarism_similarity_highlight($side['text'],
        fingerprint::shared_ranges($side['text'], $other['text'], $ignore)), ['class' => 'plagiarism-similarity-text']);
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo $OUTPUT->footer();
