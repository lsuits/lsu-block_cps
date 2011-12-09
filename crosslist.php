<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'crosslist_form.php';

require_login();

if (!cps_crosslist::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_crosslist::name());
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

$_s = ues::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_crosslist::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/crosslist.php');
$PAGE->set_pagetype('cps-crosslist');

$PAGE->requires->js('/lib/jquery.js');
$PAGE->requires->js('/blocks/cps/js/selection.js');
$PAGE->requires->js('/blocks/cps/js/crosslist.js');

$form = crosslist_form::create($courses);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));

} else if ($data = $form->get_data()) {

    if (isset($data->back)) {
        $form->next = $form->prev;

    } else if ($form->next == crosslist_form::FINISHED) {
        $form = new crosslist_form_finish();

        try {
            $form->process($data, $courses);

            $form->display();
        } catch (Exception $e) {
            echo $OUTPUT->notification($_s('application_errors', $e->getMessage()));
            echo $OUTPUT->continue_button('/my');
        }
        die();
    }

    $form = crosslist_form::next_from($form->next, $data, $courses);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
