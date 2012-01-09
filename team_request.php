<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'team_request_form.php';

require_login();

if (!cps_team_request::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_team_request::name());
}

if (!ues_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = ues_teacher::get(array('userid' => $USER->id));

$non_primaries = (bool) get_config('block_cps', 'team_request_nonprimary');

$sections = cps_unwant::active_sections_for($teacher, !$non_primaries);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$courses = ues_course::merge_sections($sections);

$_s = ues::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_team_request::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/team_request.php');
$PAGE->set_pagetype('cps-teamteach');

$form = team_request_form::create($courses);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));

} else if ($data = $form->get_data()) {

    if (isset($data->back)) {
        $form->next = $form->prev;

    } else if ($form->next == team_request_form::FINISHED) {
        $form = new team_request_form_finish();

        $form->process($data, $courses);

        $form->display();

        die();
    } else if ($form->next == team_request_form::SECTIONS) {
        redirect(new moodle_url('/blocks/cps/team_section.php', array(
            'id' => $data->selected
        )));
    }

    $form = team_request_form::next_from($form->next, $data, $courses);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
