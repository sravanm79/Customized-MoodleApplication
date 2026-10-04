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
 * Language strings for local_gradesheet.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['boxplot'] = 'Box plot of the class scores: minimum, quartiles, median and maximum';
$string['classhidden'] = 'Class statistics appear once at least {$a} students have a score, so that no one\'s score can be worked out from them.';
$string['classresults'] = 'Class results ({$a} students)';
$string['confirmdelete'] = 'Delete the grade sheet "{$a}"? Its gradebook column and every score in it are deleted.';
$string['deleted'] = 'Grade sheet "{$a}" deleted.';
$string['distribution'] = 'Distribution of scores';
$string['errorempty'] = 'The file has no data rows below the header row.';
$string['errorfiletype'] = 'Upload a CSV, Excel (.xlsx, .xls) or OpenDocument (.ods) file.';
$string['errorgrademax'] = 'The maximum score must be greater than 0.';
$string['errorread'] = 'The file could not be read. Save it again as CSV (UTF-8) or .xlsx and retry.';
$string['errorsamecolumn'] = 'Choose different columns for the student ID and the score.';
$string['errortoomanyrows'] = 'The file has more than {$a} rows.';
$string['gradebook'] = 'Open gradebook';
$string['grademax'] = 'Maximum score';
$string['gradesheet:manage'] = 'Upload and manage grade sheets';
$string['gradesheet:viewstats'] = 'View own grade sheet results and class statistics';
$string['gradesheets'] = 'Grade sheets';
$string['histogrambar'] = '{$a->range}: {$a->count} students';
$string['idcolumn'] = 'Student ID column';
$string['scorecolumn'] = 'Score column';
$string['scorecolumn_help'] = 'The column holding each student\'s score, out of the maximum score above.';
$string['idcolumn_help'] = 'The column identifying each student: their ID number (e.g. roll number), username or email address.';
$string['imported'] = 'Imported {$a->count} scores into "{$a->name}". They are also in the gradebook.';
$string['importgrades'] = 'Import {$a} scores';
$string['line'] = 'Row';
$string['mincohort'] = 'Minimum class size for statistics';
$string['mincohort_desc'] = 'Students see the class average, median, quartiles, distribution and their percentile only when at least this many students have a score on a sheet. With fewer, those figures could reveal other students\' scores.';
$string['missingscores'] = 'Students in the course without a score on this sheet: {$a}';
$string['normalised'] = 'Normalised';
$string['noscores'] = 'No current student has a score on this sheet.';
$string['noscoreyet'] = 'You have no score on this sheet.';
$string['nosheets'] = 'No grade sheets yet. Upload a CSV or Excel file with a student ID column and a score column.';
$string['nosheetsstudent'] = 'No grade sheets have been published in this course yet.';
$string['percentile'] = 'You scored higher than {$a}% of your classmates.';
$string['pluginname'] = 'Grade sheets';
$string['preview'] = 'Preview';
$string['previewerrors'] = '{$a} with problems (skipped)';
$string['previewerrorsheading'] = 'Rows that will be skipped';
$string['previewheading'] = 'Preview';
$string['previewmissing'] = 'Without a row: {$a}';
$string['previewmissinglist'] = 'Students without a row: {$a}';
$string['previewrows'] = '{$a} rows read';
$string['previewvalid'] = '{$a} ready to import';
$string['previewvalidlist'] = 'Show the {$a} rows to import';
$string['privacy:metadata'] = 'Grade sheets stores the list of uploaded sheets only; the scores are grades in the course gradebook.';
$string['problem'] = 'Problem';
$string['range'] = 'Score range';
$string['rawscore'] = '{$a->score} out of {$a->max}';
$string['reasonambiguous'] = 'Matches more than one student';
$string['reasonduplicate'] = 'Student already has a score in an earlier row';
$string['reasoninvalidscore'] = 'Score is not a number';
$string['reasonnoid'] = 'No student ID';
$string['reasonnoscore'] = 'No score';
$string['reasonnotparticipant'] = 'Not a student of this course';
$string['reasonoutofrange'] = 'Score is below 0 or above the maximum';
$string['score'] = 'Score';
$string['sheetfile'] = 'Grade sheet file';
$string['sheetfile_help'] = 'A CSV, Excel (.xlsx, .xls) or OpenDocument (.ods) file. The first row holds the column names; each following row has a student ID (ID number, username or email) and a score. Other columns are ignored.';
$string['sheetmeta'] = 'Uploaded {$a->date} · out of {$a->max} · {$a->n} scores';
$string['sheetname'] = 'Sheet name';
$string['sheetname_help'] = 'Shown to students and used as the gradebook column name, e.g. "MLP Live Session Summary - 02/10/2026".';
$string['showscores'] = 'Show all {$a} scores';
$string['statmax'] = 'Highest';
$string['statmean'] = 'Average';
$string['statmedian'] = 'Median';
$string['statmin'] = 'Lowest';
$string['statq1'] = 'Lower quartile';
$string['statq3'] = 'Upper quartile';
$string['statsd'] = 'Standard deviation';
$string['student'] = 'Student';
$string['studentid'] = 'Student ID';
$string['studentintro'] = 'Your result on each grade sheet, with the class distribution. Other students\' scores are never shown.';
$string['students'] = 'Students';
$string['teacherintro'] = 'Upload scores from a spreadsheet. Each sheet becomes a gradebook column; students see only their own score and anonymised class statistics. {$a} students in this course.';
$string['updatepreview'] = 'Update preview';
$string['uploadintro'] = 'Choose the file and the maximum score, then preview: every row is matched to the course\'s students before anything is imported.';
$string['uploadsheet'] = 'Upload grade sheet';
$string['yourrange'] = 'your score';
