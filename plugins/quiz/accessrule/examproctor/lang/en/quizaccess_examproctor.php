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
 * Strings for quizaccess_examproctor.
 *
 * @package   quizaccess_examproctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Proctored exam (tab-switch detection)';
$string['privacy:metadata:log'] = 'Proctoring events recorded while a user attempts a proctored quiz.';
$string['privacy:metadata:log:attemptid'] = 'The quiz attempt the event belongs to.';
$string['privacy:metadata:log:userid'] = 'The user who made the attempt.';
$string['privacy:metadata:log:eventtype'] = 'The kind of event, e.g. tab switch or fullscreen exit.';
$string['privacy:metadata:log:details'] = 'Extra details, such as how long the user was away.';
$string['privacy:metadata:log:timecreated'] = 'When the event happened.';

// Settings.
$string['enabled'] = 'Proctored exam mode';
$string['enabled_help'] = 'Renders the attempt in a distraction-free exam layout and detects when the student switches tab, leaves the browser window or exits fullscreen. Every event is logged and shown in the proctoring report.';
$string['maxviolations'] = 'Auto-submit after violations';
$string['maxviolations_help'] = 'The attempt is submitted automatically when the student reaches this many violations. Set to 0 to only warn and log.';
$string['maxviolationserror'] = 'Enter a number between 0 and 100.';
$string['requirefullscreen'] = 'Require fullscreen';
$string['requirefullscreen_help'] = 'The attempt is hidden until the student enters fullscreen. Leaving fullscreen counts as a violation.';
$string['detectblur'] = 'Detect switching to other applications';
$string['detectblur_help'] = 'Also count focus moving to another window or application (Alt+Tab, clicking outside the browser), not just switching browser tabs.';
$string['blockcopypaste'] = 'Block copy, paste and right-click';
$string['blockcopypaste_help'] = 'Prevents copying, cutting, pasting and the context menu on the attempt page. Attempts are logged but do not count as violations.';

// Student-facing.
$string['studentdescription'] = 'This is a proctored exam. Your activity is monitored during the attempt: {$a}';
$string['ruletabswitch'] = 'Switching to another browser tab is recorded as a violation.';
$string['rulewindowblur'] = 'Leaving the browser window (for another application) is recorded as a violation.';
$string['rulefullscreen'] = 'The exam must be taken in fullscreen; leaving fullscreen is recorded as a violation.';
$string['rulecopypaste'] = 'Copy, paste and right-click are disabled.';
$string['rulemaxviolations'] = 'Your attempt will be submitted automatically after {$a} violations.';
$string['rulenolimit'] = 'Violations are reported to your teacher.';
$string['preflightheader'] = 'Proctored exam';
$string['preflightagree'] = 'I understand and agree to these exam rules';
$string['preflightrequired'] = 'You must agree to the exam rules to start.';

// JS strings.
$string['js_bartitle'] = 'Proctored exam';
$string['js_barviolations'] = 'Violations: {$a->count} / {$a->max}';
$string['js_barnolimit'] = 'Violations: {$a->count}';
$string['js_warningtitle'] = 'You left the exam';
$string['js_warningbody'] = 'You were away from the exam for {$a->seconds} seconds. This has been recorded and will be reported to your teacher (violation {$a->count}).';
$string['js_warninglimit'] = 'Your exam will be submitted automatically at {$a->max} violations. Remaining: {$a->remaining}.';
$string['js_warningok'] = 'Return to exam';
$string['js_fullscreentitle'] = 'Fullscreen required';
$string['js_fullscreenbody'] = 'This exam must be taken in fullscreen mode.\nLeaving fullscreen is recorded as a violation.';
$string['js_fullscreenbutton'] = 'Enter fullscreen';
$string['js_submittingtitle'] = 'Violation limit reached';
$string['js_submittingbody'] = 'You reached the maximum number of violations. Your exam is being submitted now.';
$string['js_blocked'] = 'This action is disabled during the exam.';
$string['js_ispreview'] = 'Preview';

// Report.
$string['viewreport'] = 'View proctoring report';
$string['reporttitle'] = 'Proctoring report';
$string['reportdetail'] = 'Proctoring events for {$a}';
$string['noevents'] = 'No proctoring events recorded yet.';
$string['backtoreport'] = 'Back to proctoring report';
$string['violations'] = 'Violations';
$string['lastevent'] = 'Last event';
$string['event'] = 'Event';
$string['details'] = 'Details';
$string['event_tabhidden'] = 'Switched tab / minimised';
$string['event_windowblur'] = 'Left browser window';
$string['event_fullscreenexit'] = 'Exited fullscreen';
$string['event_fullscreenenter'] = 'Entered fullscreen';
$string['event_returned'] = 'Returned to exam';
$string['event_copy'] = 'Copy blocked';
$string['event_cut'] = 'Cut blocked';
$string['event_paste'] = 'Paste blocked';
$string['event_contextmenu'] = 'Right-click blocked';
