<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'split_form.php';

require_login();

if (!cps_split::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_split::name());
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

$valid_courses = cps_split::filter_valid($courses);

if (empty($valid_courses)) {
    print_error('no_courses', 'block_cps');
}

$_s = cps::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_split::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/split.php');
$PAGE->set_pagetype('cps-split');

$PAGE->requires->js('/lib/jquery.js');

$form = split_form::create($valid_courses);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {
    if (isset($data->back)) {
        $data->next = $data->prev;

        $form = split_form::next_from($data, $valid_courses);
    } else {

        switch ($data->current) {
            case split_form::UPDATE:
            case split_form::DECIDE:
            default:
                $form = split_form::next_from($data, $valid_courses);
        }

    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
