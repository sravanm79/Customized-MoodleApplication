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
 * Strings for plagiarism_similarity.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Similarity check';
$string['similarity:enable'] = 'Turn on the similarity check for an assignment';
$string['similarity:viewreport'] = 'View similarity scores and compare submissions';
$string['task_check'] = 'Compare assignment submissions for similarity';

// Site settings.
$string['settingsintro'] = 'Compares each assignment submission with the other students\' submissions to the same assignment (online text, code and text files, Jupyter notebooks, Word and OpenDocument files). Nothing leaves this server and the internet is not searched. PDF files are not compared.';
$string['settingenabled'] = 'Enable';
$string['settingenabled_desc'] = 'Offer the similarity check in assignment settings.';
$string['settingdefaulton'] = 'On for new assignments';
$string['settingdefaulton_desc'] = 'New assignments have the check turned on unless the teacher turns it off.';
$string['thresholderror'] = 'Enter a percentage between 1 and 100.';
$string['plagiarismoff'] = 'Plagiarism plugins are switched off for the site. Turn on <a href="{$a}">Enable plagiarism plugins</a> as well.';

// Assignment settings.
$string['formheader'] = 'Similarity check';
$string['formenabled'] = 'Compare submissions';
$string['formenabled_help'] = 'Each submission is compared with the other students\' submissions to this assignment. Teachers see a similarity percentage beside every submission and can compare two submissions side by side. Students are told their work is compared.';
$string['formthreshold'] = 'High similarity from (%)';
$string['formthreshold_help'] = 'Scores at or above this percentage are shown in red, from half of it in amber. The score is the share of a submission\'s text that also appears in one classmate\'s submission.';
$string['formignore'] = 'Ignore this text (starter code, question)';
$string['formignore_help'] = 'Text or code every student is given, such as starter code or the question itself. Passages from it are not counted. The assignment description is ignored automatically, and so is anything more than half the class has in common.';

// Badges and disclosure.
$string['disclosure'] = 'Your submission will be compared with your classmates\' submissions to this assignment for similarity.';
$string['badge_pending'] = 'Similarity: checking…';
$string['badge_score'] = 'Similarity {$a}%';
$string['badge_title'] = 'Compare with the most similar submission';
$string['status_pending'] = 'Checking…';
$string['status_done'] = 'Checked';
$string['status_tooshort'] = 'Too short to compare';
$string['status_nocontent'] = 'Nothing comparable';

// Report.
$string['reporttitle'] = 'Similarity report';
$string['reportintro'] = 'Similarity is the share of each submission that also appears in one classmate\'s submission (highest match shown). Scores from {$a}% are high. Starter code, the assignment description and passages most of the class shares are not counted. A high score is a reason to look, not proof of copying.';
$string['checknow'] = 'Check now';
$string['checked'] = '{$a} submissions compared.';
$string['lastchecked'] = 'Last checked {$a}. Checks also run about a minute after each new submission.';
$string['neverchecked'] = 'Not checked yet. Checks run about a minute after each new submission.';
$string['notenabled'] = 'The similarity check is not turned on for this assignment (assignment settings > Similarity check).';
$string['nosubmissions'] = 'No submissions have been compared yet.';
$string['similarity'] = 'Similarity';
$string['closestmatch'] = 'Most similar to';
$string['words'] = 'Words';
$string['notcompared'] = 'Files not compared';
$string['compare'] = 'Compare';
$string['comparetitle'] = 'Compare submissions';
$string['compareintro'] = 'Highlighted passages appear in both submissions (at least five words in a row). Starter code and passages most of the class shares are not highlighted.';
$string['backtoreport'] = 'Back to the similarity report';

// Privacy.
$string['privacy:metadata:sub'] = 'The similarity result of each student\'s latest assignment submission.';
$string['privacy:metadata:sub:userid'] = 'The student.';
$string['privacy:metadata:sub:fingerprint'] = 'Hashes of short passages of the submission, used for comparison (the text cannot be rebuilt from them).';
$string['privacy:metadata:sub:score'] = 'The highest similarity with another student\'s submission.';
$string['privacy:metadata:sub:matchuserid'] = 'The student whose submission was most similar.';
$string['privacy:metadata:sub:timemodified'] = 'When the submission was last compared.';
