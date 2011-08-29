<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'creation_form.php';

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
$heading = cps_creation::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/creation.php');

$form = new creation_form(null, array('sections' => $sections));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {
    // Hande data here
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
