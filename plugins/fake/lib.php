<?php

function cleanup_fake_data($output = true) {
    global $DB;

    if ($output) {
        mtrace("Cleaning up fake data:");
    }

    $tables = array('students', 'teachers', 'sections', 'courses', 'semesters');

    foreach ($tables as $table) {
        if ($output) {
            mtrace("\tCleaning up {$table}...");
        }

        $sql = 'TRUNCATE {enrol_cps_' . $table .'}';

        $DB->execute($sql);
    }

    if ($output) {
        mtrace("\tCleaning up users...");
    }

    $users = $DB->get_records_select('user', "idnumber LIKE '123%'");

    foreach ($users as $user) {
        delete_user($user);
    }
}
