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
 * English strings for local_llmgrader.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'API key';
$string['apikey_desc'] = 'Optional bearer token for the LLM endpoint.';
$string['approve'] = 'Approve';
$string['approveall'] = 'Approve all drafts ({$a})';
$string['approved'] = 'Approved: the grade and feedback were released to the student.';
$string['approvedall'] = '{$a->approved} drafts approved and released. {$a->stale} skipped because the submission changed.';
$string['approverelease'] = 'Approve and release to student';
$string['assignsettings'] = 'LLM settings';
$string['assignsettings_intro'] = 'What the LLM is given for this assignment, when it runs, and whether its results wait for your approval.';
$string['assigntollm'] = 'Assign to LLM';
$string['comment'] = 'Comment';
$string['criterion'] = 'Criterion';
$string['details'] = 'Details';
$string['disabled'] = 'LLM grading is turned off in the site settings.';
$string['enabled'] = 'Enable LLM grading';
$string['enabled_desc'] = 'Send assignment submissions (online text, code files, Jupyter notebooks) to the LLM, as set per assignment: on submission, when submissions close, or when a teacher clicks "Assign to LLM".';
$string['error_pointgrade'] = 'LLM grading needs a point grade (Grade type: Point) with a maximum above 0.';
$string['feedbackfooter'] = 'Suggested by the LLM grader: {$a->score}/{$a->maxscore} marks = {$a->grade}/{$a->maxgrade}.';
$string['feedbackforstudent'] = 'Feedback for the student';
$string['filenotread'] = 'not read: only text, code and notebook files are sent to the grader';
$string['gotograding'] = 'Open grading table';
$string['gradeoutof'] = 'Grade (out of {$a})';
$string['gradeoutofrange'] = 'Enter a grade between 0 and {$a}.';
$string['guidelines'] = 'Marking guidelines';
$string['guidelines_help'] = 'Anything else the grader should know: what to be strict or lenient about, common mistakes, partial credit rules.';
$string['invalidresponse'] = 'The LLM reply was not in the expected format: {$a}';
$string['llmgrading'] = 'Auto Grade';
$string['llmhttperror'] = 'The LLM server returned an error: {$a}';
$string['llmsuggestion'] = 'LLM suggestion';
$string['llmunreachable'] = 'Could not reach the LLM server: {$a}';
$string['llmurl'] = 'LLM base URL';
$string['llmurl_desc'] = 'OpenAI-compatible base URL, ending in /v1 (e.g. a vLLM server).';
$string['marks'] = 'marks';
$string['maxchars'] = 'Maximum submission size sent (characters)';
$string['maxchars_desc'] = 'The submission text (notebooks condensed to instructions, code and outputs) is cut at this length so it fits the model\'s context. About 16000 for an 8k-token model.';
$string['metadata'] = '{$a->model} · {$a->prompttokens} + {$a->completiontokens} tokens · {$a->latency}s';
$string['model'] = 'Model name';
$string['model_desc'] = 'Model id as listed by the endpoint\'s /v1/models.';
$string['nojobs'] = 'No submissions have been sent to the LLM for this assignment yet. Click "Assign to LLM" to send every submitted, not yet evaluated submission.';
$string['notadraft'] = 'This result is no longer a draft.';
$string['onlinetext'] = 'Online text';
$string['permanentfailure'] = '{$a}';
$string['pluginname'] = 'LLM grader';
$string['privacy:metadata:llm'] = 'The submission content is sent to the configured LLM server for grading, with the assignment, rubric and reference solution. No name or email is sent.';
$string['privacy:metadata:llm:submission'] = 'The submitted work (online text, code files, notebook code and outputs).';
$string['privacy:metadata:local_llmgrader_job'] = 'LLM evaluations of submissions.';
$string['privacy:metadata:local_llmgrader_job:feedback'] = 'The feedback written by the LLM (and edited by the teacher).';
$string['privacy:metadata:local_llmgrader_job:reviewerid'] = 'The teacher who approved or rejected the suggestion.';
$string['privacy:metadata:local_llmgrader_job:score'] = 'The score suggested by the LLM.';
$string['privacy:metadata:local_llmgrader_job:userid'] = 'The student whose submission was evaluated.';
$string['provider'] = 'LLM provider';
$string['provider_desc'] = 'How requests are sent. Add a provider by putting a class implementing \\local_llmgrader\\provider\\provider in classes/provider.';
$string['provider_mock'] = 'Mock (offline test replies, no LLM)';
$string['provider_openai_compatible'] = 'OpenAI-compatible API (vLLM, OpenAI, Ollama, LiteLLM, ...)';
$string['queued'] = '{$a} submission(s) sent to the LLM. Results appear within a minute or two.';
$string['reference'] = 'Reference solution';
$string['reference_help'] = 'A model answer (text or code). Students\' answers are compared with it, but different correct approaches still earn full marks.';
$string['regrade'] = 'Evaluate again';
$string['reject'] = 'Reject';
$string['rejected'] = 'Draft rejected. Nothing was sent to the student.';
$string['reportintro_auto'] = 'Submissions are graded by the LLM and the grade and feedback are released to students straight away (teacher review is off in the LLM settings). You can still change a grade in the grading table; the LLM never overwrites a grade you gave.';
$string['reportintro_review'] = 'The LLM\'s grades and feedback are drafts: students see nothing until you approve them. Approve a draft as it is, open it to edit the grade and feedback, or reject it.';
$string['requirereview'] = 'Teacher review';
$string['requirereview_help'] = 'On: the LLM\'s grade and feedback are saved as drafts; students see nothing until you approve them on the Auto Grade page. Off: they are released to students straight away.';
$string['requirereview_label'] = 'Keep results as drafts until a teacher approves them';
$string['requirereviewdefault'] = 'Teacher review by default';
$string['requirereviewdefault_desc'] = 'For assignments without their own LLM settings: keep the LLM\'s grade and feedback as a draft until a teacher approves it.';
$string['reviewdraft'] = 'Review LLM draft';
$string['reviewdraftfor'] = 'LLM draft for {$a->name}: {$a->assignment}';
$string['reviewedby'] = 'Reviewed by {$a->name}, {$a->time}';
$string['reviewedit'] = 'Review & edit';
$string['rubric'] = 'Rubric and criteria';
$string['rubric_help'] = 'One criterion per line with its marks, e.g. "- Correct output for task 1 [4 marks]". Without a rubric, the marks stated in the assignment or the work are used.';
$string['scaledto'] = 'Grade: {$a}';
$string['settingssaved'] = 'LLM settings saved.';
$string['settingssummary'] = 'Runs: {$a->trigger} · Teacher review: {$a->review} · Rubric: {$a->rubric} · Reference solution: {$a->reference}';
$string['showfeedback'] = 'Feedback';
$string['stale_changed'] = 'The submission changed or was reverted to draft after it was evaluated.';
$string['stale_content'] = 'The student changed the submitted work after it was evaluated.';
$string['stale_teachergraded'] = 'A teacher has already graded this attempt.';
$string['status_applied'] = 'Released';
$string['status_draft'] = 'Draft: awaiting review';
$string['status_evaluated'] = 'Graded, not sent (held under the old review rule)';
$string['status_failed'] = 'Failed';
$string['status_queued'] = 'Waiting';
$string['status_rejected'] = 'Rejected';
$string['status_skipped_human_graded'] = 'Already graded by teacher';
$string['status_stale'] = 'Outdated';
$string['status_superseded'] = 'Replaced';
$string['stream'] = 'Stream responses';
$string['stream_desc'] = 'Receive the reply as it is generated (server-sent events). Useful when a proxy closes connections that stay silent for long; the result is the same.';
$string['submission'] = 'Submission';
$string['suggestedgrade'] = 'Suggested grade';
$string['systemprompt'] = 'System prompt';
$string['systemprompt_desc'] = 'Instructions for the grader. The reply must stay in the JSON shape described at the end. The assignment, rubric, reference solution, guidelines and submission are added per submission.';
$string['task_queueclosed'] = 'Queue LLM grading for assignments that have closed';
$string['timeout'] = 'Request timeout (seconds)';
$string['timeout_desc'] = 'How long to wait for the LLM to answer one submission.';
$string['trigger_close'] = 'when submissions close (cut-off date, else due date)';
$string['trigger_manual'] = 'only when a teacher clicks "Assign to LLM"';
$string['trigger_submit'] = 'when a student submits';
$string['triggermode'] = 'Run';
$string['triggermode_help'] = 'When submissions are sent to the LLM. "Assign to LLM" on the Auto Grade page works in every mode.';
$string['viewsubmission'] = 'View the submission ({$a}) in the grader';
