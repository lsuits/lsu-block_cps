<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'team_section_form.php';

require_login();

if (!cps_team_request::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_team_request::name());
}

if (!ues_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = ues_teacher::get(array('userid' => $USER->id));

$sections = cps_unwant::active_sections_for($teacher);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$semesters = ues_semester::merge_sections($sections);

$key = required_param('id', PARAM_RAW);
list($semid, $couid) = explode('_', $key);

if (!isset($semesters[$semid]) or !isset($semesters[$semid]->courses[$couid])) {
    print_error('not_course', 'block_cps');
}

$semester = $semesters[$semid];
$course = $semester->courses[$couid];

$current_requests = cps_team_request::in_course($course, $semester, true);

if (empty($current_requests)) {
    print_error('not_approved', 'block_cps');
}

$initial_data = array(
    'course' => $course,
    'semester' => $semester,
    'requests' => $current_requests
);

$_s = ues::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_team_request::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_title($heading);
$PAGE->set_url('/blocks/cps/team_section.php', array('id' => $key));
$PAGE->set_pagetype('cps-teamteach');

$PAGE->requires->js('/blocks/cps/js/jquery.js');
$PAGE->requires->js('/blocks/cps/js/selection.js');
$PAGE->requires->js('/blocks/cps/js/crosslist.js');

$form = team_section_form::create($initial_data);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/blocks/cps/team_request.php'));

} else if ($data = $form->get_data()) {

    if (isset($data->back)) {
        $form->next = $form->prev;

    } else if ($form->next == team_section_form::FINISHED) {
        $form = new team_section_form_finish();

        try {
            $form->process($data, $initial_data);

            $form->display();
        } catch (Exception $e) {
            echo $OUTPUT->notification($_s('application_errors', $e->getMessage()));
            echo $OUTPUT->continue_button('/my');
        }
        die();
    }

    $form = team_section_form::next_from($form->next, $data, $initial_data);
}

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help($heading, 'team_manage_sections', 'block_cps');

$form->display();

echo $OUTPUT->footer();
