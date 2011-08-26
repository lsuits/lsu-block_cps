<?php

defined('MOODLE_INTERNAL') or die();

if ($ADMIN->fulltree) {
    require_once $CFG->dirroot . '/enrol/cps/publiclib.php';

    // using the public lib for string generation
    $_s = cps::gen_str('block_cps');
    $_m = cps::gen_str('moodle');

    $settings->add(new admin_setting_heading('block_cps_settings', '',
        $_s('pluginname_desc')));

    $settings->add(new admin_setting_configcheckbox('block_cps/course_severed',
        $_s('course_severed'), $_s('course_severed_desc'), 0));

    $days = array_combine(range(1, 120), range(1, 120));

    

    $settings->add(new admin_setting_configselect('block_cps/create_days',
        $_s('create_days'), $_s('create_days_desc'), 30, $days));

    $settings->add(new admin_setting_configselect('block_cps/enroll_days',
        $_s('enroll_days'), $_s('enroll_days_desc'), 14, $days));

}
