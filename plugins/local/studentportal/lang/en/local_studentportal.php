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
 * Strings for local_studentportal.
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Student portal';
$string['privacy:metadata'] = 'The Student portal stores no personal data of its own. It creates accounts and enrolments in Moodle core, and shows students their own grades and activity.';
$string['studentportal:register'] = 'Register students and issue their login credentials';

// Register students.
$string['registerstudents'] = 'Register students';
$string['registerintro'] = 'Create accounts for students and enrol them in one or more courses. Every new student gets a username and a temporary password they must change at first login; you can then download, print or email the logins. Students who already have an account are only enrolled.';
$string['step_courses'] = '1. Courses';
$string['courses'] = 'Courses';
$string['courses_help'] = 'The students are enrolled as students in every course you choose here.';
$string['choosecourses'] = 'Choose one or more courses';
$string['hidden'] = 'hidden from students';
$string['step_students'] = '2. Students';
$string['mode'] = 'Add';
$string['mode_single'] = 'One student';
$string['mode_list'] = 'A list of students (CSV file or pasted rows)';
$string['rollnumber'] = 'Roll number';
$string['rollnumber_help'] = 'The student\'s roll / registration number. It becomes their username (in lower case) and their ID number in Moodle. Leave empty to use the part of the email address before "@" as username.';
$string['csvfile'] = 'CSV file';
$string['csvfile_help'] = 'One student per line: roll number, first name, last name, email. A header row with these column names (any order) is recognised; without one the columns must be in this order. Excel: File › Save as › CSV.';
$string['pasted'] = 'Or paste rows';
$string['pasted_help'] = 'Paste rows copied from a spreadsheet or typed by hand, one student per line: roll number, first name, last name, email (separated by commas, semicolons or tabs). Used together with the CSV file if both are given.';
$string['step_options'] = '3. Options';
$string['resetexisting'] = 'Existing accounts';
$string['resetexisting_label'] = 'Also give students who already have an account a new temporary password';
$string['resetexisting_help'] = 'Off: students who already have an account keep their password and are only enrolled. On: they also get a new temporary password to share (they must change it at next login, and are logged out now). Admin accounts and accounts that log in another way (e.g. single sign-on) are never changed.';
$string['preview'] = 'Preview';
$string['error_nolist'] = 'Upload a CSV file or paste at least one row.';

// Preview.
$string['previewtitle'] = 'Check before registering';
$string['previewcourses'] = 'Students will be enrolled in:';
$string['count_create'] = 'new accounts';
$string['count_enrol'] = 'existing accounts to enrol';
$string['count_nothing'] = 'already enrolled';
$string['count_error'] = 'rows with problems (skipped)';
$string['row'] = 'Row';
$string['result'] = 'Result';
$string['action_create'] = 'New account';
$string['action_enrol'] = 'Existing account';
$string['action_nothing'] = 'No change';
$string['action_error'] = 'Problem';
$string['plan_create'] = 'A new account "{$a}" will be created.';
$string['plan_enrolexisting'] = 'Account of {$a} found: will be enrolled.';
$string['plan_alreadyenrolled'] = '{$a} is already enrolled in every chosen course.';
$string['plan_newpassword'] = 'Gets a new temporary password.';
$string['error_email'] = 'Missing or invalid email address.';
$string['error_name'] = 'First name and last name are required.';
$string['error_duplicaterow'] = 'Same email or roll number as row {$a}.';
$string['error_username'] = 'The username "{$a}" is already taken by another account (or the roll number is empty and the email gives no usable name). Use a different roll number or check the existing account.';
$string['error_ambiguous'] = 'Several accounts have this email or roll number; register this student by hand.';
$string['error_conflict'] = 'The email belongs to one account and the roll number to another; check both accounts.';
$string['error_suspended'] = 'The matching account "{$a}" is suspended; unsuspend it first.';
$string['error_privileged'] = 'This email or roll number belongs to an administrator or the guest account.';
$string['confirmregister'] = 'Register {$a} student(s)';
$string['nothingtodo'] = 'Nothing to register';
$string['error_planexpired'] = 'That preview is no longer available. Fill in the form again.';

// Credentials.
$string['credentials'] = 'Student logins';
$string['credentialsready'] = 'Done: {$a} student(s) registered and enrolled.';
$string['credentialsonce'] = 'The temporary passwords are shown only now and stay on this page until {$a} (15 minutes). They are not stored anywhere readable: download, print or email them before you leave. If one gets lost, use "Reset password & share" on the course\'s Students and logins page.';
$string['sharecredentials'] = 'Share the logins';
$string['downloadcsv'] = 'Download CSV';
$string['printslips'] = 'Print slips';
$string['emailstudents'] = 'Email each student';
$string['copyall'] = 'Copy all';
$string['sharehelp'] = 'Students log in at {$a->loginurl}. Each device must first install the LMS certificate from {$a->certurl} (the slips and emails explain how). Emails go only to each student\'s own address.';
$string['passwordunchanged'] = 'unchanged (existing account)';
$string['status_created'] = 'New account';
$string['status_reset'] = 'New password';
$string['status_enrolled'] = 'Enrolled';
$string['donecredentials'] = 'Done: forget these passwords';
$string['credentialsforgotten'] = 'The temporary passwords were removed from this page.';
$string['error_batchexpired'] = 'These logins are no longer available (they are kept for 15 minutes). Reset the passwords from the course\'s Students and logins page to share them again.';
$string['emailsent'] = '{$a} email(s) sent.';
$string['emailfailed'] = 'Could not email: {$a}.';
$string['emailcredentialssubject'] = 'Your login for {$a->site}';
$string['emailcredentialsbody'] = 'Hello {$a->firstname},

An account has been created for you on {$a->site} for: {$a->courses}.

Address:   {$a->loginurl}
Username:  {$a->username}
Temporary password:  {$a->password}

First time on a computer or phone:
1. On the campus network, open {$a->certurl} and install the certificate (it makes the LMS address trusted; your teacher can show you how).
2. Open the address above and log in with your username and temporary password.
3. Choose your own new password when asked.

Keep your password private. If you forget it, use "Forgotten your username or password?" on the login page.';
$string['emailenrolledsubject'] = 'You have been added to {$a->courses}';
$string['emailenrolledbody'] = 'Hello {$a->firstname},

You have been enrolled in {$a->courses} on {$a->site}.

Log in at {$a->loginurl} with your usual username ({$a->username}) and password.';
$string['print'] = 'Print';
$string['slip_title'] = 'Student login';
$string['slip_address'] = 'Address';
$string['slip_password'] = 'Temporary password';
$string['slip_step1'] = 'First time on a device: on the campus network open {$a} and install the certificate.';
$string['slip_step2'] = 'Open the address above and log in with this username and temporary password.';
$string['slip_step3'] = 'Choose your own new password when asked. Keep it private.';

// Students and logins.
$string['coursestudents'] = 'Students and logins';
$string['studentsenrolled'] = 'Students enrolled';
$string['neverloggedin'] = 'Have never logged in';
$string['resetshare'] = 'Reset password & share';
$string['resetselected'] = 'Reset selected & share';
$string['resethelp'] = 'Resetting gives the student a new temporary password (they are logged out and must choose their own at next login) and shows it so you can share it again.';
$string['nostudents'] = 'No students in this course yet. Use "Register students".';
$string['error_cannotreset'] = 'This account\'s password cannot be reset here (it is an administrator, the guest, or logs in another way).';

// My performance.
$string['myperformance'] = 'My performance';
$string['metric_courses'] = 'Courses';
$string['metric_avggrade'] = 'Average grade';
$string['metric_completion'] = 'Average completion';
$string['metric_activedays'] = 'Active days (30)';
$string['metric_submissions'] = 'Assignments submitted';
$string['metric_tests'] = 'Tests taken';
$string['workcount'] = 'Submitted: {$a->submissions} · Tests taken: {$a->attempts}';
$string['activity30'] = 'Your activity, last 30 days';
$string['activitybar'] = '{$a->day}: {$a->count} actions';
$string['activityhelp'] = 'Each bar is one day: pages viewed, work submitted and tests taken in your courses.';
$string['insights'] = 'Insights';
$string['insight_strongest'] = 'Best result: {$a->name} ({$a->course}), {$a->percent}%.';
$string['insight_weakest'] = 'Needs work: {$a->name} ({$a->course}), {$a->percent}%. Read the feedback and ask your teacher.';
$string['insight_inactive'] = 'You have not opened {$a->course} for {$a->days} days.';
$string['insight_activedays'] = 'You were active on {$a} of the last 30 days.';
$string['lastvisit'] = 'Last visit: {$a}';
$string['coursegrade'] = 'Course grade';
$string['completion'] = 'Completion';
$string['viewfeedback'] = 'Feedback';
$string['nogradesyet'] = 'No grades yet in this course.';
$string['fullgradereport'] = 'Full grade report';
$string['nocourses'] = 'You are not enrolled in any course as a student yet.';
