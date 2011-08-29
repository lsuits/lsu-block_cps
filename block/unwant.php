<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'unwant_form.php';

require_login();

if (!cps_unwant::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_unwant::name());
}

if (!cps_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = cps_teacher::get(array('userid' => $USER->id));

$sections = $teacher->sections(true);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$_s = cps::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_unwant::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/unwant.php');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form = new unwant_form(null, array('sections' => $sections));
$form->display();

echo $OUTPUT->footer();
