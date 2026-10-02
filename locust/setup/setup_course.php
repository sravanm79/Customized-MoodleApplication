<?php
// Creates the isolated load-test course, activities and synthetic students.
// Run inside moodle_app:  php setup_course.php create <nusers>   |   php setup_course.php delete
define('CLI_SCRIPT', true);
require('/bitnami/moodle/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

const SHORTNAME = 'LOADTEST-2026';
const PREFIX = 'lt26_';
const PASSWORD = 'LoadTest#2026';
const MCQ_QBES = [4, 5];   // Existing multichoice questions (PYTHON TEST).
const CODE_QBE = 25;       // Existing CodeRunner "Square function" (CODING TEST).

\core\session\manager::set_user(get_admin());
$mode = $argv[1] ?? '';

if ($mode === 'delete') {
    if ($course = $DB->get_record('course', ['shortname' => SHORTNAME])) {
        delete_course($course, false);
        echo "deleted course\n";
    }
    $users = $DB->get_records_select('user', 'username LIKE ? AND deleted = 0', [PREFIX . '%']);
    foreach ($users as $u) {
        delete_user($u);
    }
    echo 'deleted ' . count($users) . " users\n";
    exit(0);
}
if ($mode !== 'create') {
    exit("usage: create <nusers> | delete\n");
}
$nusers = (int) ($argv[2] ?? 1000);
$gen = new testing_data_generator();

$course = $DB->get_record('course', ['shortname' => SHORTNAME]);
if (!$course) {
    $course = $gen->create_course(['shortname' => SHORTNAME, 'fullname' => 'Load test course 2026', 'numsections' => 1]);
}

function lt_module($gen, $type, $course, $name, $extra = []) {
    global $DB;
    if ($m = $DB->get_record($type, ['course' => $course->id, 'name' => $name])) {
        return get_coursemodule_from_instance($type, $m->id);
    }
    $inst = $gen->create_module($type, ['course' => $course->id, 'name' => $name, 'section' => 1] + $extra);
    return get_coursemodule_from_id($type, $inst->cmid);
}

$page = lt_module($gen, 'page', $course, 'LT Reading page', [
    'content' => str_repeat('<p>Python lists, tuples and dictionaries are the core containers. ' .
        'This paragraph simulates a normal reading page.</p>', 40)]);
$forum = lt_module($gen, 'forum', $course, 'LT Discussion forum');
$mcq = lt_module($gen, 'quiz', $course, 'LT MCQ quiz', ['attempts' => 0, 'preferredbehaviour' => 'deferredfeedback',
    'questionsperpage' => 0, 'grade' => 10, 'sumgrades' => 0]);
$code = lt_module($gen, 'quiz', $course, 'LT Coding quiz', ['attempts' => 0, 'preferredbehaviour' => 'deferredfeedback',
    'questionsperpage' => 0, 'grade' => 10, 'sumgrades' => 0]);
// mod_jupyter has no test generator: create it the way the course editing form does.
if ($j = $DB->get_record('jupyter', ['course' => $course->id, 'name' => 'LT Jupyter notebook'])) {
    $jup = get_coursemodule_from_instance('jupyter', $j->id);
} else {
    require_once($CFG->dirroot . '/course/modlib.php');
    $mi = create_module((object) ['modulename' => 'jupyter', 'course' => $course->id, 'section' => 1, 'visible' => 1,
        'name' => 'LT Jupyter notebook', 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        'grade' => 0, 'notebookfile' => file_get_unused_draft_itemid()]);
    $jup = get_coursemodule_from_id('jupyter', $mi->coursemodule);
}

// Put the existing questions (latest version) into the quizzes.
foreach ([[$mcq, MCQ_QBES], [$code, [CODE_QBE]]] as [$cm, $qbes]) {
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
    if ($DB->count_records('quiz_slots', ['quizid' => $quiz->id])) {
        continue;
    }
    foreach ($qbes as $qbe) {
        $qid = $DB->get_field_sql('SELECT questionid FROM {question_versions} WHERE questionbankentryid = ?
            ORDER BY version DESC', [$qbe], IGNORE_MULTIPLE);
        quiz_add_quiz_question($qid, $quiz, 0, 1);
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
}

// One discussion to read.
if (!$DB->record_exists('forum_discussions', ['forum' => $forum->instance])) {
    $gen->get_plugin_generator('mod_forum')->create_discussion(['course' => $course->id, 'forum' => $forum->instance,
        'userid' => get_admin()->id, 'name' => 'Welcome thread', 'message' => 'Say hello here.']);
}

// Synthetic students.
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

$out = ['courseid' => $course->id, 'page' => $page->id, 'forum' => $forum->id, 'mcq' => $mcq->id, 'code' => $code->id,
    'jupyter' => $jup->id, 'discussion' => (int) $DB->get_field('forum_discussions', 'id', ['forum' => $forum->instance]),
    'users' => $nusers, 'prefix' => PREFIX, 'password' => PASSWORD];
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
fwrite(STDERR, "created $created new users\n");
