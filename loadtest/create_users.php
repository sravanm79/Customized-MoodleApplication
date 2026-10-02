<?php
// Creates/enrols N load-test students (loadtest0001..N) in a course, or deletes them with --delete.
// Run inside moodle_app:  php create_users.php [N] [courseid] [--delete]
define('CLI_SCRIPT', true);
require('/bitnami/moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$n = (int)($argv[1] ?? 1000);
$courseid = (int)($argv[2] ?? 2);
$delete = in_array('--delete', $argv);
\core\session\manager::set_user(get_admin());

if ($delete) {
    $users = $DB->get_records_select('user', "username LIKE 'loadtest%' AND deleted = 0");
    foreach ($users as $u) { delete_user($u); }
    echo "Deleted " . count($users) . " users\n";
    exit;
}

$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$created = 0;
for ($i = 1; $i <= $n; $i++) {
    $username = sprintf('loadtest%04d', $i);
    if (!$uid = $DB->get_field('user', 'id', ['username' => $username, 'deleted' => 0])) {
        $uid = user_create_user((object)[
            'username' => $username, 'password' => 'LoadTest#2026', 'auth' => 'manual',
            'firstname' => 'Load', 'lastname' => sprintf('Student%04d', $i),
            'email' => "$username@example.invalid", 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        ], true, false);
        $created++;
    }
    $enrol->enrol_user($instance, $uid, $studentrole);
    if ($i % 100 == 0) echo "$i\n";
}
echo "Created $created users, enrolled $n in course $courseid\n";
