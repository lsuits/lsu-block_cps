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

//prevents previous semesters from being presented for user-creation
$all        = $teacher->sections(true);
$filter     = ues::where()->grades_due->greater_equal(time());
$valids     = array_keys(ues_semester::get_all($filter));
$sections   = array();
foreach($all as $sec){
    if(in_array($sec->semesterid, $valids)){
        $sections[] = $sec;
    }
}


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
$PAGE->set_title($heading);
$PAGE->set_url('/blocks/cps/creation.php');

$form = new creation_form(null, array('sections' => $sections));

$setting_params = ues::where()
    ->userid->equal($USER->id)
    ->name->starts_with('creation_');

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {
    $settings = cps_setting::get_to_name($setting_params);

    $creations = cps_creation::get_all(array('userid' => $USER->id));

    if (isset($data->creation_defaults)) {
        cps_setting::delete_all($setting_params);
    }

    foreach ($form->settings as $name => $value) {
        if (!isset($settings[$name])) {
            $setting = new cps_setting();
            $setting->name = $name;
            $setting->userid = $USER->id;
            $setting->value = null;
        } else {
            $setting = $settings[$name];
        }

        if ($setting->value == $value) {
            continue;
        }

        $setting->value = $value;
        $setting->save();
    }

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
$settings = cps_setting::get_all($setting_params);

$form_data = array();

if (empty($settings)) {
    $form_data['creation_defaults'] = 1;
}

foreach ($settings as $setting) {
    $form_data[$setting->name] = $setting->value;
}

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
echo $OUTPUT->heading_with_help($heading, 'creation', 'block_cps');

if (isset($success) and $success) {
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
}elseif($form->is_submitted() && !$form->is_validated()){
    echo $OUTPUT->notification(get_string('someerrorswerefound'));
}

$form->set_data($form_data);
$form->display();

echo $OUTPUT->footer();
