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
 * English strings for mod_jupyter.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Jupyter notebook';
$string['modulename'] = 'Jupyter notebook';
$string['modulenameplural'] = 'Jupyter notebooks';
$string['modulename_help'] = 'Students write and run code in a Jupyter notebook inside Moodle, then submit it for grading.';
$string['pluginadministration'] = 'Jupyter notebook administration';

$string['jupyter:addinstance'] = 'Add a new Jupyter notebook activity';
$string['jupyter:view'] = 'View Jupyter notebook activity';
$string['jupyter:submit'] = 'Submit a Jupyter notebook';
$string['jupyter:grade'] = 'Grade Jupyter notebook submissions';

$string['hubinternalurl'] = 'JupyterHub internal URL';
$string['hubinternalurl_desc'] = 'URL the Moodle server uses to reach JupyterHub, including its base URL, e.g. http://jupyterhub:8000/jupyter inside Docker.';
$string['hubpublicurl'] = 'JupyterHub public URL';
$string['hubpublicurl_desc'] = 'URL students\' browsers use to reach JupyterHub. Use the same https origin as Moodle (e.g. https://192.168.30.239/jupyter, proxied by Apache) so the notebook iframe is not blocked as mixed content.';
$string['hubtoken'] = 'JupyterHub service token';
$string['hubtoken_desc'] = 'API token of the "moodle" service configured in jupyterhub_config.py.';

$string['notebookfile'] = 'Starter notebook';
$string['notebookfile_help'] = 'Optional .ipynb file each student starts from. If empty, students get a blank Python notebook.';

$string['backtosubmissions'] = 'Back to submissions';
$string['feedback'] = 'Feedback';
$string['grade'] = 'Grade';
$string['gradessaved'] = 'Grades saved.';
$string['huberror'] = 'JupyterHub request failed ({$a->action}, HTTP {$a->status}) {$a->message}';
$string['hubnotconfigured'] = 'JupyterHub is not configured. Ask an administrator to set it up under Site administration > Plugins > Activity modules > Jupyter notebook.';
$string['hubtimeout'] = 'Your Jupyter server took too long to start. Please reload the page.';
$string['hubunreachable'] = 'Could not connect to JupyterHub: {$a}';
$string['invalidnotebook'] = 'The notebook file is not valid JSON.';
$string['launchfailed'] = 'Could not open the notebook: {$a}';
$string['nograde'] = 'No grade';
$string['nograding'] = 'Grading disabled';
$string['nonotebook'] = 'No notebook file found for this submission.';
$string['nosubmissions'] = 'No students to show.';
$string['notebook'] = 'Notebook';
$string['notsubmitted'] = 'You have not submitted this notebook yet.';
$string['notsubmittedshort'] = 'Not submitted';
$string['openinjupyter'] = 'Open in Jupyter';
$string['resubmit'] = 'Resubmit notebook';
$string['reviewing'] = 'Submission from {$a}';
$string['savebeforesubmit'] = 'Save your notebook first (Ctrl+S). The last saved version is submitted.';
$string['savegrades'] = 'Save grades';
$string['status'] = 'Status';
$string['submissions'] = 'Submissions';
$string['submissionsaved'] = 'Your notebook has been submitted.';
$string['submit'] = 'Submit notebook';
$string['submitfailed'] = 'Submission failed: {$a}';
$string['submittedon'] = 'Submitted on {$a}';
$string['viewsubmissions'] = 'View submissions ({$a})';
$string['yourgrade'] = 'Grade: {$a}';

$string['privacy:metadata:jupyter_submissions'] = 'Notebook submissions and their grades.';
$string['privacy:metadata:jupyter_submissions:userid'] = 'The user who submitted the notebook.';
$string['privacy:metadata:jupyter_submissions:grade'] = 'The grade given to the submission.';
$string['privacy:metadata:jupyter_submissions:feedback'] = 'Feedback given on the submission.';
$string['privacy:metadata:jupyter_submissions:timemodified'] = 'When the notebook was last submitted.';
$string['privacy:metadata:jupyterhub'] = 'Notebooks are edited and run on an external JupyterHub server.';
$string['privacy:metadata:jupyterhub:userid'] = 'The Moodle user id, used to name the JupyterHub account.';
$string['privacy:metadata:jupyterhub:notebook'] = 'The notebook the user is working on.';
