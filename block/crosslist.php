<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'crosslist_form.php';

require_login();

if (!cps_crosslist::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_crosslist::name());
}

if (!cps_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = cps_teacher::get(array('userid' => $USER->id));

$sections = cps_unwant::active_sections_for($teacher);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$courses = cps_course::merge_sections($sections);

$_s = cps::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_crosslist::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/split.php');
$PAGE->set_pagetype('cps-crosslist');

/*
 * I know this page will need jquery
$PAGE->requires->js('/lib/jquery.js');
$PAGE->requires->js('/blocks/cps/js/split.js');
*/

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form = new crosslist_form_select(null, array('courses' => $courses));

$form->display();

echo $OUTPUT->footer();
