<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'split_form.php';

require_login();

if (!cps_split::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_split::name());
}

if (!ues_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = ues_teacher::get(array('userid' => $USER->id));

$sections = cps_unwant::active_sections_for($teacher);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$courses = ues_course::merge_sections($sections);

$valid_courses = cps_split::filter_valid($courses);

if (empty($valid_courses)) {
    print_error('no_courses', 'block_cps');
}

$_s = ues::gen_str('block_cps');

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
$PAGE->requires->js('/blocks/cps/js/selection.js');
$PAGE->requires->js('/blocks/cps/js/split.js');

$form = split_form::create($valid_courses);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {

    if (isset($data->back)) {
        $form->next = $form->prev;

    } else if ($form->next == split_form::FINISHED) {
        $form = new split_form_finish();

        try {
            $form->process($data, $valid_courses);

            $form->display();
        } catch (Exception $e) {
            echo $OUTPUT->notification($_s('application_errors', $e->getMessage()));
            echo $OUTPUT->continue_button('/my');
        }

        die();
    }

    $form = split_form::next_from($form->next, $data, $valid_courses);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
