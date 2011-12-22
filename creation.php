<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'creation_form.php';

require_login();

if (!cps_creation::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_creation::name());
}

if (!ues_user::is_teacher()) {
    print_error('not_teacher', 'block_cps');
}

$teacher = ues_teacher::get(array('userid' => $USER->id));

$sections = $teacher->sections(true);

if (empty($sections)) {
    print_error('no_section', 'block_cps');
}

$_s = ues::gen_str('block_cps');

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
    $creations = cps_creation::get_all(array('userid' => $USER->id));

    foreach ($form->create_days as $semesterid => $courses) {
        foreach ($courses as $courseid => $create_days) {
            if (empty($create_days)) {
                continue;
            }

            $enroll_days = $form->enroll_days[$semesterid][$courseid];

            $params = array(
                'userid' => $USER->id,
                'semesterid' => $semesterid,
                'courseid' => $courseid
            );

            $creation = cps_creation::get($params);
            if (!$creation) {
                $creation = new cps_creation();
                $creation->fill_params($params);
            }

            $creation->create_days = $create_days;
            $creation->enroll_days = $enroll_days;

            $creation->save();
            $creation->apply();

            unset($creations[$creation->id]);
        }
    }

    foreach ($creations as $creation) {
        cps_creation::delete($creation->id);
        $creation->apply();
    }

    $success = true;
}

$creations = cps_creation::get_all(array('userid' => $USER->id));

$form_data = array();
foreach ($creations as $creation) {
    $semesterid = $creation->semesterid;
    $courseid = $creation->courseid;

    $id = "_{$semesterid}_{$courseid}";

    $form_data["create_group{$id}"] = array(
        "create_days{$id}" => $creation->create_days,
        "enroll_days{$id}" => $creation->enroll_days
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (isset($success) and $success) {
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
}

$form->set_data($form_data);
$form->display();

echo $OUTPUT->footer();
