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
 * Library functions for mod_jupyter.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declare the features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function jupyter_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * File manager options for the starter notebook.
 *
 * @return array
 */
function jupyter_notebook_file_options() {
    return ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.ipynb']];
}

/**
 * Create a new instance.
 *
 * @param stdClass $data Form data
 * @param mod_jupyter_mod_form|null $mform
 * @return int New instance id
 */
function jupyter_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('jupyter', $data);

    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->notebookfile, $context->id, 'mod_jupyter', 'notebook', 0,
        jupyter_notebook_file_options());

    jupyter_grade_item_update($data);
    return $data->id;
}

/**
 * Update an instance.
 *
 * @param stdClass $data Form data
 * @param mod_jupyter_mod_form|null $mform
 * @return bool
 */
function jupyter_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('jupyter', $data);

    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->notebookfile, $context->id, 'mod_jupyter', 'notebook', 0,
        jupyter_notebook_file_options());

    jupyter_grade_item_update($data);
    jupyter_update_grades($data);
    return true;
}

/**
 * Delete an instance and its submissions.
 *
 * @param int $id Instance id
 * @return bool
 */
function jupyter_delete_instance($id) {
    global $DB;

    if (!$jupyter = $DB->get_record('jupyter', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('jupyter_submissions', ['jupyter' => $id]);
    jupyter_grade_item_delete($jupyter);
    $DB->delete_records('jupyter', ['id' => $id]);
    return true;
}

/**
 * Create or update the gradebook item.
 *
 * @param stdClass $jupyter Instance record (with cmidnumber if available)
 * @param mixed $grades Grade object(s), 'reset' or null
 * @return int GRADE_UPDATE_xx
 */
function jupyter_grade_item_update($jupyter, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $jupyter->name];
    if (isset($jupyter->cmidnumber)) {
        $params['idnumber'] = $jupyter->cmidnumber;
    }
    if ($jupyter->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $jupyter->grade;
        $params['grademin'] = 0;
    } else if ($jupyter->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$jupyter->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/jupyter', $jupyter->course, 'mod', 'jupyter', $jupyter->id, 0, $grades, $params);
}

/**
 * Delete the gradebook item.
 *
 * @param stdClass $jupyter
 * @return int GRADE_UPDATE_xx
 */
function jupyter_grade_item_delete($jupyter) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/jupyter', $jupyter->course, 'mod', 'jupyter', $jupyter->id, 0, null, ['deleted' => 1]);
}

/**
 * Grades for one or all users, in gradebook format.
 *
 * @param stdClass $jupyter
 * @param int $userid 0 for all users
 * @return array Grade objects keyed by user id
 */
function jupyter_get_user_grades($jupyter, $userid = 0) {
    global $DB;

    $params = ['jupyter' => $jupyter->id];
    if ($userid) {
        $params['userid'] = $userid;
    }
    $grades = [];
    foreach ($DB->get_records('jupyter_submissions', $params) as $sub) {
        if ($sub->grade === null) {
            continue;
        }
        $grades[$sub->userid] = (object) [
            'userid' => $sub->userid,
            'rawgrade' => $sub->grade,
            'feedback' => $sub->feedback,
            'feedbackformat' => FORMAT_PLAIN,
            'usermodified' => $sub->grader,
            'dategraded' => $sub->timegraded,
            'datesubmitted' => $sub->timemodified,
        ];
    }
    return $grades;
}

/**
 * Push grades to the gradebook.
 *
 * @param stdClass $jupyter
 * @param int $userid 0 for all users
 * @param bool $nullifnone
 */
function jupyter_update_grades($jupyter, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($jupyter->grade == 0) {
        jupyter_grade_item_update($jupyter);
    } else if ($grades = jupyter_get_user_grades($jupyter, $userid)) {
        jupyter_grade_item_update($jupyter, $grades);
    } else if ($userid && $nullifnone) {
        jupyter_grade_item_update($jupyter, (object) ['userid' => $userid, 'rawgrade' => null]);
    } else {
        jupyter_grade_item_update($jupyter);
    }
}

/**
 * Whether a scale is used by the given instance.
 *
 * @param int $jupyterid
 * @param int $scaleid
 * @return bool
 */
function jupyter_scale_used($jupyterid, $scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('jupyter', ['id' => $jupyterid, 'grade' => -$scaleid]);
}

/**
 * Whether a scale is used by any instance.
 *
 * @param int $scaleid
 * @return bool
 */
function jupyter_scale_used_anywhere($scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('jupyter', ['grade' => -$scaleid]);
}

/**
 * Notebook file name used inside the user's Jupyter server for this activity.
 *
 * @param cm_info|stdClass $cm
 * @param string $name Activity name
 * @return string
 */
function jupyter_notebook_path($cm, $name) {
    $slug = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), '_');
    return ($slug !== '' ? $slug : 'assignment') . '_' . $cm->id . '.ipynb';
}

/**
 * The starter notebook JSON: the teacher's uploaded file or an empty notebook.
 *
 * @param context_module $context
 * @return string
 */
function jupyter_starter_notebook($context) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_jupyter', 'notebook', 0, 'filename', false);
    if ($files) {
        return reset($files)->get_content();
    }
    return json_encode([
        'cells' => [
            ['cell_type' => 'markdown', 'metadata' => new stdClass(), 'source' => ["# Assignment\n", 'Write your code below.']],
            ['cell_type' => 'code', 'execution_count' => null, 'metadata' => new stdClass(), 'outputs' => [], 'source' => []],
        ],
        'metadata' => [
            'kernelspec' => ['display_name' => 'Python 3', 'language' => 'python', 'name' => 'python3'],
            'language_info' => ['name' => 'python'],
        ],
        'nbformat' => 4,
        'nbformat_minor' => 4,
    ]);
}

/**
 * Serve the starter notebook and submitted notebooks.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file was not found
 */
function jupyter_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/jupyter:view', $context);

    $itemid = (int) array_shift($args);
    if ($filearea === 'submission') {
        $sub = $DB->get_record('jupyter_submissions', ['id' => $itemid, 'jupyter' => $cm->instance]);
        if (!$sub) {
            return false;
        }
        if ($sub->userid != $USER->id) {
            require_capability('mod/jupyter:grade', $context);
        }
    } else if ($filearea !== 'notebook') {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'mod_jupyter', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
