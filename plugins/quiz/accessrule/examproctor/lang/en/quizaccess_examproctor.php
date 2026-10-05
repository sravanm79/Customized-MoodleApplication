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
$string['rulesebkiosk'] = 'The exam runs in Safe Exam Browser, which locks your computer to the exam window; you cannot switch to other applications or leave fullscreen.';
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
$string['js_seb'] = 'Safe Exam Browser';

// Report.
$string['viewreport'] = 'View exam activity report';
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

// Device pre-check (check.php).
$string['precheck_link'] = 'Check my camera and exam browser';
$string['precheck_title'] = 'Exam device check';
$string['precheck_running'] = 'Running checks…';
$string['precheck_intro'] = 'Run this check before you start the exam. Start the camera test and make sure your face is clearly visible. Close other apps that use the camera (Zoom, Teams, Meet) first.';
$string['precheck_intro_seb'] = 'This exam uses Safe Exam Browser (SEB). Run the check once in your normal browser, then launch SEB from the quiz page and open this check again from inside SEB: SEB has its own camera permission and configuration keys.';
$string['precheck_camera'] = 'Camera';
$string['precheck_cameraoff'] = 'Camera is off. Click “Start camera”.';
$string['precheck_camstart'] = 'Start camera';
$string['precheck_camstop'] = 'Stop camera';
$string['precheck_mictest'] = 'Test microphone';
$string['precheck_mictesting'] = 'Listening… say something';
$string['precheck_results'] = 'This device';
$string['precheck_server'] = 'Exam settings';
$string['precheck_report'] = 'Diagnostic report';
$string['precheck_report_help'] = 'If a check fails, copy this report and send it to your teacher or the support desk. It contains no passwords or answers.';
$string['precheck_copy'] = 'Copy report';
$string['precheck_copied'] = 'Copied';
$string['precheck_back'] = 'Back to the quiz';
$string['precheck_summary_ready'] = 'Ready: this device passed every check.';
$string['precheck_summary_warn'] = 'Almost ready: finish the pending tests and read the warnings below.';
$string['precheck_summary_notready'] = 'Not ready: fix the items marked ERROR before starting the exam, or send the diagnostic report to support.';
$string['status_ok'] = 'OK';
$string['status_warn'] = 'Warning';
$string['status_error'] = 'Error';
$string['status_info'] = 'Info';
$string['precheck_status_ok'] = 'OK';
$string['precheck_status_warn'] = 'Warning';
$string['precheck_status_error'] = 'Error';
$string['precheck_status_info'] = 'Info';
$string['precheck_status_pending'] = 'To do';
$string['precheck_status_skipped'] = 'Skipped';
$string['precheck_c_seb_yes'] = 'Running inside Safe Exam Browser (version {$a}).';
$string['precheck_c_seb_no'] = 'Not running inside Safe Exam Browser.';
$string['precheck_c_seb_required_outside'] = 'You are in a normal browser. This exam needs Safe Exam Browser: after this check, launch SEB from the quiz page and run the check again inside SEB.';
$string['precheck_c_seb_notrequired'] = 'This exam does not use Safe Exam Browser.';
$string['precheck_c_secure_ok'] = 'Secure connection: the browser allows camera access on this site.';
$string['precheck_c_secure_no'] = 'Insecure connection (http): browsers block the camera on this address. The site must be opened over https.';
$string['precheck_c_media_ok'] = 'Camera and microphone API available.';
$string['precheck_c_media_no'] = 'This browser does not expose the camera API here (insecure address or very old browser).';
$string['precheck_c_cam_ok'] = 'Camera working: {$a}.';
$string['precheck_c_cam_dark'] = 'The camera picture is almost black ({$a}). Open the privacy shutter, uncover the lens or turn on a light.';
$string['precheck_c_cam_notallowed'] = 'Camera permission was refused. Click the camera icon in the address bar, choose Allow, and reload. On macOS also allow the browser in System Settings › Privacy & Security › Camera. ({$a})';
$string['precheck_c_cam_notallowed_seb'] = 'Safe Exam Browser blocked the camera. The exam configuration must allow camera access, and on macOS Safe Exam Browser must be allowed in System Settings › Privacy & Security › Camera. Tell your teacher. ({$a})';
$string['precheck_c_cam_notfound'] = 'No camera found. Connect a webcam and try again. ({$a})';
$string['precheck_c_cam_notreadable'] = 'The camera is in use by another application (Zoom, Teams, Meet, Camera app) or blocked by the system. Close those apps and try again. ({$a})';
$string['precheck_c_cam_other'] = 'The camera could not be started. ({$a})';
$string['precheck_c_cam_notrequired'] = 'This exam does not record the camera; the test is optional.';
$string['precheck_c_mic_ok'] = 'Microphone working: {$a}.';
$string['precheck_c_mic_silent'] = 'Microphone found ({$a}) but no sound was heard. Check it is not muted.';
$string['precheck_c_mic_failed'] = 'Microphone could not be started ({$a}). The exam does not need it unless your teacher says so.';
$string['precheck_c_keys_ok'] = 'Safe Exam Browser configuration accepted by Moodle (config key / browser exam key valid).';
$string['precheck_c_keys_config_bad'] = 'Moodle rejected the SEB config key: this SEB was started with an old or different exam configuration, or the site was opened under a different address. Quit SEB, delete any downloaded .seb files, and launch SEB again from the quiz page.';
$string['precheck_c_keys_bek_bad'] = 'Moodle rejected the SEB browser exam key: this Safe Exam Browser version or build is not on the list allowed for this exam. Tell your teacher which SEB version you have.';
$string['precheck_c_keys_noapi'] = 'This Safe Exam Browser version does not expose its keys to the page. Update Safe Exam Browser to the latest version.';
$string['precheck_c_keys_error'] = 'The SEB keys could not be checked ({$a}).';
$string['precheck_c_keys_notinseb'] = 'Safe Exam Browser keys are checked when you run this page inside SEB.';
$string['precheck_c_keys_timeout'] = 'Safe Exam Browser did not provide its keys. Update Safe Exam Browser and launch it again from the quiz page.';
$string['precheck_c_net_ok'] = 'Connection to Moodle OK ({$a} ms).';
$string['precheck_c_net_slow'] = 'Connection to Moodle is slow ({$a} ms). Move closer to the Wi-Fi access point or use a wired connection.';
$string['precheck_c_net_failed'] = 'Could not reach Moodle ({$a}). Check your network connection.';

// Server-side diagnostics (check.php and cli/seb_check.php).
$string['sebmode_no'] = 'Safe Exam Browser is not required';
$string['sebmode_manual'] = 'Safe Exam Browser: configured manually in Moodle';
$string['sebmode_template'] = 'Safe Exam Browser: configured from a template';
$string['sebmode_upload'] = 'Safe Exam Browser: uploaded configuration file';
$string['sebmode_clientconfig'] = 'Safe Exam Browser: client configuration';
$string['diag_sebmode'] = '{$a}.';
$string['diag_camerarequired'] = 'Webcam proctoring is enabled: the camera must work for the whole attempt.';
$string['diag_cameranotrequired'] = 'Webcam proctoring is not enabled for this quiz.';
$string['diag_httpsok'] = 'The site uses HTTPS, so browsers allow the camera.';
$string['diag_httpslocal'] = 'The site is served over http on this computer ({$a}). The camera works here only because it is localhost; students on other devices need the site on https.';
$string['diag_httpsmissing'] = 'The site is served over plain http ({$a}). Browsers, and Safe Exam Browser, block the camera on insecure addresses: serve the site over https.';
$string['diag_wwwrootdynamic'] = 'The site address ($CFG->wwwroot) follows whatever host name the browser used. Safe Exam Browser keys are cached per quiz for one address, so students who reach the site under another address get “config key” errors. The administrator should fix $CFG->wwwroot in config.php.';
$string['diag_wwwrootfixed'] = 'The site address is fixed: {$a}.';
$string['diag_configerror'] = 'The Safe Exam Browser configuration could not be generated: {$a}';
$string['diag_starturlmismatch'] = 'The Safe Exam Browser configuration was generated for {$a->config}, but this device uses {$a->actual}. SEB would open the wrong address and Moodle would reject its config key. The administrator must fix $CFG->wwwroot and purge the SEB caches.';
$string['diag_starturlok'] = 'The Safe Exam Browser configuration points to this site ({$a}).';
$string['diag_sebcameraok'] = 'The Safe Exam Browser configuration allows camera access.';
$string['diag_sebcameraoff'] = 'Webcam proctoring is on, but the Safe Exam Browser configuration blocks the camera. The teacher must turn on “Allow browser access to camera” in the quiz’s Safe Exam Browser settings (browserMediaCaptureCamera).';
$string['diag_sebcameraunused'] = 'The Safe Exam Browser configuration allows the camera, although no webcam proctoring is enabled.';
$string['diag_urlfilterok'] = 'The SEB URL filter allows this site ({$a}).';
$string['diag_urlfiltermissing'] = 'SEB URL filtering is on, but no active “allow” rule matches {$a}. Moodle pages and the proctoring uploads may be blocked.';
$string['diag_allowquitoff'] = 'Students cannot quit Safe Exam Browser themselves; if the configuration is wrong they need the quit password or a restart.';
$string['diag_examproctorseb'] = 'Inside Safe Exam Browser the proctored-exam fullscreen and window-focus checks are turned off, because SEB already locks the screen. Tab switching and copy/paste rules still apply.';
$string['diag_autoreconfigureoff'] = '“Auto-configure SEB” is off: students who already have an older configuration open will get config key errors after the quiz settings change.';
