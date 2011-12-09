<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'unwant_form.php';

require_login();

if (!cps_unwant::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_unwant::name());
}

if (!ues_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = ues_teacher::get(array('userid' => $USER->id));

$sections = $teacher->sections(true);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$PAGE->requires->js('/lib/jquery.js');
$PAGE->requires->js('/blocks/cps/js/unwanted.js');

$_s = ues::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_unwant::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': '. $heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url('/blocks/cps/unwant.php');

$form = new unwant_form(null, array('sections' => $sections));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {

    $unwants = cps_unwant::get_all(array('userid' => $USER->id));

    // Perform Selected
    $fields = get_object_vars($data);
    foreach ($fields as $name => $value) {
        if (preg_match('/^section_(\d+)/', $name, $matches)) {
            $sectionid = $matches[1];

            $params = array('userid' => $USER->id, 'sectionid' => $sectionid);
            $unwant = cps_unwant::get($params);

            if (!$unwant) {
                $unwant = new cps_unwant();
                $unwant->fill_params($params);
            }

            $unwant->save();
            $unwant->apply();

            unset($unwants[$unwant->id]);
        }
    }

    // Erase deselected
    foreach ($unwants as $unwant) {
        cps_unwant::delete($unwant->id);
        $unwant->unapply();
    }

    $success = true;
}

$unwants = cps_unwant::get_all(array('userid' => $USER->id));
$form_data = array();

foreach ($unwants as $unwant) {
    $form_data['section_' . $unwant->sectionid] = 1;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (isset($success) and $success) {
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
}

$form->set_data($form_data);
$form->display();

echo $OUTPUT->footer();
