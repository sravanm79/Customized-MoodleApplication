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

$string['pluginname'] = 'LLM notebook grader';

$string['apikey'] = 'API key';
$string['apikey_desc'] = 'Optional bearer token for the LLM endpoint.';
$string['enabled'] = 'Enable automatic grading';
$string['enabled_desc'] = 'Grade .ipynb assignment submissions automatically when students submit them.';
$string['llmurl'] = 'LLM base URL';
$string['llmurl_desc'] = 'OpenAI-compatible base URL, ending in /v1 (e.g. a vLLM server).';
$string['maxchars'] = 'Maximum notebook size sent (characters)';
$string['maxchars_desc'] = 'The condensed notebook is cut at this length so it fits the model\'s context. About 16000 for an 8k-token model.';
$string['model'] = 'Model name';
$string['model_desc'] = 'Model id as listed by the endpoint\'s /v1/models.';
$string['systemprompt'] = 'System prompt';
$string['systemprompt_desc'] = 'Instructions for the grader. The reply must stay in the JSON shape described at the end.';
$string['timeout'] = 'Request timeout (seconds)';
$string['timeout_desc'] = 'How long to wait for the LLM to answer one notebook.';

$string['details'] = 'Details';
$string['disabled'] = 'Auto Grade is turned off in the site settings.';
$string['error_pointgrade'] = 'Auto Grade needs a point grade (Grade type: Point) with a maximum above 0.';
$string['feedbackfooter'] = 'Graded automatically: {$a->score}/{$a->maxscore} marks = {$a->grade}/{$a->maxgrade}.';
$string['gotograding'] = 'Open grading table';
$string['invalidresponse'] = 'The LLM reply was not in the expected format: {$a}';
$string['llmgrading'] = 'Auto Grade';
$string['llmhttperror'] = 'The LLM server returned an error: {$a}';
$string['llmunreachable'] = 'Could not reach the LLM server: {$a}';
$string['marks'] = 'marks';
$string['metadata'] = '{$a->model} · {$a->prompttokens} + {$a->completiontokens} tokens · {$a->latency}s';
$string['nojobs'] = 'No notebooks have been sent to the LLM for this assignment yet. Submissions are graded automatically when students click "Submit"; use the button above to grade submissions that already exist.';
$string['permanentfailure'] = '{$a}';
$string['queueall'] = 'Grade submitted notebooks not yet graded';
$string['queued'] = '{$a} notebook(s) queued. Results appear within a minute or two.';
$string['regrade'] = 'Grade again';
$string['reportintro'] = 'Notebooks are graded automatically when students submit, and the grade and feedback are sent to the student straight away. You can still change a grade in the grading table; Auto Grade never overwrites a grade you changed.';
$string['showfeedback'] = 'Feedback';
$string['status_applied'] = 'Sent to student';
$string['status_evaluated'] = 'Graded, not sent (held under the old review rule)';
$string['status_failed'] = 'Failed';
$string['status_queued'] = 'Waiting';
$string['status_skipped_human_graded'] = 'Already graded by teacher';
$string['status_stale'] = 'Outdated';
$string['status_superseded'] = 'Replaced';

$string['privacy:metadata:local_llmgrader_job'] = 'LLM evaluations of submitted notebooks.';
$string['privacy:metadata:local_llmgrader_job:userid'] = 'The student whose notebook was evaluated.';
$string['privacy:metadata:local_llmgrader_job:score'] = 'The score suggested by the LLM.';
$string['privacy:metadata:local_llmgrader_job:feedback'] = 'The feedback written by the LLM.';
$string['privacy:metadata:llm'] = 'The notebook content is sent to the configured LLM server for grading. No name or email is sent.';
$string['privacy:metadata:llm:notebook'] = 'The submitted notebook (code, text and outputs).';
