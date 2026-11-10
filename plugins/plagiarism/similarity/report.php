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
 * Similarity report of an assignment: every student's score and closest classmate.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use plagiarism_similarity\local\checker;

$cmid = required_param('cmid', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('plagiarism/similarity:viewreport', $context);

$url = new moodle_url('/plagiarism/similarity/report.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('reporttitle', 'plagiarism_similarity'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

$cfg = checker::config($cmid);
if ($cfg && optional_param('checknow', 0, PARAM_BOOL) && confirm_sesskey()) {
    $n = checker::run($cmid);
    redirect($url, get_string('checked', 'plagiarism_similarity', $n), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', 'plagiarism_similarity') . ': ' . format_string($cm->name));

if (!$cfg) {
    echo $OUTPUT->notification(get_string('notenabled', 'plagiarism_similarity'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('reportintro', 'plagiarism_similarity', (int) $cfg->threshold), ['class' => 'text-muted']);
echo html_writer::div(
    $OUTPUT->single_button(new moodle_url($url, ['checknow' => 1]), get_string('checknow', 'plagiarism_similarity'), 'post',
        ['type' => 'primary']) .
    html_writer::span($cfg->timechecked
        ? get_string('lastchecked', 'plagiarism_similarity', userdate($cfg->timechecked))
        : get_string('neverchecked', 'plagiarism_similarity'), 'ms-3 small text-muted'),
    'd-flex align-items-center mb-3');

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$matchfields = \core_user\fields::for_name()->get_sql('m', false, 'match', '', false)->selects;
$rows = $DB->get_records_sql(
    "SELECT s.id, s.userid, s.status, s.score, s.matchuserid, s.skipped, s.tokens, u.idnumber, $userfields, $matchfields
       FROM {plagiarism_similarity_sub} s
       JOIN {user} u ON u.id = s.userid
  LEFT JOIN {user} m ON m.id = s.matchuserid
      WHERE s.cmid = :cmid
   ORDER BY s.score DESC, u.lastname, u.firstname", ['cmid' => $cmid]);

if (!$rows) {
    echo $OUTPUT->notification(get_string('nosubmissions', 'plagiarism_similarity'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('user'), get_string('idnumber'), get_string('similarity', 'plagiarism_similarity'),
    get_string('closestmatch', 'plagiarism_similarity'), get_string('words', 'plagiarism_similarity'),
    get_string('notcompared', 'plagiarism_similarity'), ''];
$table->attributes['class'] = 'generaltable table-sm';
foreach ($rows as $r) {
    $match = '';
    if ($r->matchuserid) {
        $m = (object) [];
        foreach (\core_user\fields::get_name_fields() as $field) {
            $m->$field = $r->{'match' . $field};
        }
        $match = fullname($m);
    }
    $score = $r->status === 'done'
        ? html_writer::span(format_float($r->score, 0) . '%',
            'badge ' . plagiarism_plugin_similarity::badge_class((float) $r->score, (int) $cfg->threshold))
        : html_writer::span(get_string('status_' . $r->status, 'plagiarism_similarity'), 'badge bg-secondary');
    $table->data[] = [
        fullname($r),
        s($r->idnumber),
        $score,
        $match,
        (int) $r->tokens,
        s($r->skipped),
        $r->matchuserid ? html_writer::link(new moodle_url('/plagiarism/similarity/compare.php',
            ['cmid' => $cmid, 'a' => $r->userid, 'b' => $r->matchuserid]), get_string('compare', 'plagiarism_similarity'))
            : '',
    ];
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->footer();
