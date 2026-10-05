<?php
// Video track setup in an existing course (default: PYTHON LESSON, id 2) instead of the isolated load-test course:
// adds a "VIDEO LECTURE" section with the uploaded lecture as an embedded File resource (what a teacher gets from
// Add an activity or resource → File), and creates + enrols the synthetic students lt26_0001… there.
// Run inside moodle_app as daemon (never root: files written to moodledata must stay daemon's):
//   php setup_video.php create <video.mp4> <nusers> [courseid]   prints the JSON for setup/video.json
//   php setup_video.php delete [courseid]                        removes the synthetic students (the video stays)
//   php setup_video.php delete-video [courseid]                  also removes the video section
define('CLI_SCRIPT', true);
require('/bitnami/moodle/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');

const PREFIX = 'lt26_';
const PASSWORD = 'LoadTest#2026';
const SECTIONNAME = 'VIDEO LECTURE';
const VIDEONAME = 'Lecture: If, Elif, Else in Python (uploaded video)';

\core\session\manager::set_user(get_admin());
$mode = $argv[1] ?? '';
$courseid = (int) ($mode === 'create' ? ($argv[4] ?? 2) : ($argv[2] ?? 2));
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

function lt_video_section($course) {
    global $DB;
    return $DB->get_record('course_sections', ['course' => $course->id, 'name' => SECTIONNAME]);
}

if ($mode === 'delete' || $mode === 'delete-video') {
    $users = $DB->get_records_select('user', 'username LIKE ? AND deleted = 0', [PREFIX . '%']);
    foreach ($users as $u) {
        delete_user($u);
    }
    echo 'deleted ' . count($users) . " users\n";
    if ($mode === 'delete-video' && ($section = lt_video_section($course))) {
        course_delete_section($course, $section, true);
        echo "deleted section '" . SECTIONNAME . "'\n";
    }
    exit(0);
}
if ($mode !== 'create' || empty($argv[2]) || !is_readable($argv[2])) {
    exit("usage: create <video.mp4> <nusers> [courseid] | delete [courseid] | delete-video [courseid]\n");
}
$videopath = $argv[2];
$nusers = (int) ($argv[3] ?? 1000);
$gen = new testing_data_generator();

// Section at the end of the course.
if (!$section = lt_video_section($course)) {
    $section = course_create_section($course);
    course_update_section($course, $section, ['name' => SECTIONNAME,
        'summary' => '<p>Recorded lecture. Press play; you can seek and change speed in the player.</p>',
        'summaryformat' => FORMAT_HTML]);
    $section = lt_video_section($course);
}

// File resource, embedded so the page shows Moodle's video player (as when a teacher uploads an .mp4).
$resource = $DB->get_record('resource', ['course' => $course->id, 'name' => VIDEONAME]);
if ($resource) {
    $cm = get_coursemodule_from_instance('resource', $resource->id);
} else {
    $draftid = file_get_unused_draft_itemid();
    get_file_storage()->create_file_from_pathname([
        'contextid' => context_user::instance(get_admin()->id)->id, 'component' => 'user', 'filearea' => 'draft',
        'itemid' => $draftid, 'filepath' => '/', 'filename' => basename($videopath),
    ], $videopath);
    $mi = create_module((object) [
        'modulename' => 'resource', 'course' => $course->id, 'section' => $section->section, 'visible' => 1,
        'name' => VIDEONAME, 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'files' => $draftid, 'display' => RESOURCELIB_DISPLAY_EMBED, 'printintro' => 0,
        'showsize' => 1, 'showtype' => 1,
    ]);
    $cm = get_coursemodule_from_id('resource', $mi->coursemodule);
    $resource = $DB->get_record('resource', ['id' => $cm->instance]);
}
$context = context_module::instance($cm->id);
$files = get_file_storage()->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
$file = reset($files);
$url = moodle_url::make_pluginfile_url($context->id, 'mod_resource', 'content', $resource->revision,
    $file->get_filepath(), $file->get_filename())->out_as_local_url(false);

// Synthetic students, enrolled in this course as students.
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$hash = hash_internal_user_password(PASSWORD);
$created = 0;
for ($i = 1; $i <= $nusers; $i++) {
    $username = sprintf('%s%04d', PREFIX, $i);
    $uid = $DB->get_field('user', 'id', ['username' => $username, 'deleted' => 0]);
    if (!$uid) {
        $uid = $DB->insert_record('user', (object) [
            'username' => $username, 'password' => $hash, 'auth' => 'manual', 'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id, 'firstname' => 'Load', 'lastname' => sprintf('Tester %04d', $i),
            'email' => $username . '@loadtest.invalid', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $created++;
    }
    $gen->enrol_user($uid, $course->id, $studentrole);
}

echo json_encode(['courseid' => $course->id, 'video' => $cm->id, 'video_url' => $url, 'video_bytes' => $file->get_filesize(),
    'users' => $nusers, 'prefix' => PREFIX, 'password' => PASSWORD], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
fwrite(STDERR, "created $created new users\n");
