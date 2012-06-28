<?php

require_once '../../config.php';
require_once 'classes/lib.php';
require_once 'setting_form.php';

require_login();

$id = optional_param('id', $USER->id, PARAM_INT);

if (!cps_setting::is_enabled()) {
    print_error('not_enabled', 'block_cps', '', cps_setting::name());
}

if (!cps_setting::is_valid(ues_user::sections(true))) {
    print_error('not_teacher', 'block_cps');
}

$user = $DB->get_record('user', array('id' => $id), '*', MUST_EXIST);

if ($user->id != $USER->id and !is_siteadmin($USER->id)) {
    print_error('not_teacher', 'block_cps');
}

$_s = ues::gen_str('block_cps');

$blockname = $_s('pluginname');
$heading = cps_setting::name();

$context = get_context_instance(CONTEXT_SYSTEM);

$base_url = new moodle_url('/blocks/cps/setting.php');

$PAGE->set_context($context);
$PAGE->set_heading($blockname . ': ' . $heading);
$PAGE->set_title($heading);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_url($base_url);

// Admin came here the first time
if (is_siteadmin($USER->id) and $USER->id === $id) {
    $form = new setting_search_form();
} else {
    $form = new setting_form(null, array('user' => $user));
}

$setting_params = ues::where('userid')->equal($id)->name->starts_with('user_');

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {
    if (isset($data->search)) {
        $filters = ues::where();

        if (!empty($data->username)) {
            $filters->username->like($data->username);
        }

        if (!empty($data->idnumber)) {
            $filters->idnumber->like($data->idnumber);
        }

        if ($filters->is_empty()) {
            $note = $OUTPUT->notification($_s('no_filters'));
        } else {
            $users = ues_user::get_all($filters);
            if (empty($users)) {
                $result = $OUTPUT->notification($_s('no_results'));
            } else {
                $table = new html_table();
                $table->head = array(
                    get_string('firstname'), get_string('lastname'),
                    get_string('username'), get_string('idnumber'),
                    get_string('action')
                );

                $edit_str = get_string('edit');
                foreach ($users as $user) {
                    $url = new moodle_url($base_url, array('id' => $user->id));

                    $line = array(
                        $user->firstname,
                        $user->lastname,
                        $user->username,
                        $user->idnumber,
                        html_writer::link($url, $edit_str)
                    );

                    $table->data[] = new html_table_row($line);
                }

                $result = html_writer::tag(
                    'div', html_writer::table($table),
                    array('class' => 'centered results')
                );
            }
        }
    }

    if (isset($data->save)) {
        $current_settings = cps_setting::get_to_name($setting_params);
        foreach (get_object_vars($data) as $name => $value) {
            if (empty($value) or !preg_match('/^user_/', $name)) {
                continue;
            }

            if (isset($current_settings[$name])) {
                $setting = $current_settings[$name];
            } else {
                $setting = new cps_setting();
                $setting->userid = $user->id;
                $setting->name = $name;
            }

            $setting->value = $value;
            $setting->save();

            unset($current_settings[$name]);
        }

        foreach ($current_settings as $setting) {
            cps_setting::delete($setting->id);
        }

        events_trigger('user_updated', $user);

        $note = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    }
}

$settings = cps_setting::get_to_name($setting_params);
$to_value = function($setting) { return $setting->value; };
$form->set_data(array_map($to_value, $settings));

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help($heading, 'setting', 'block_cps');

if (!empty($note)) {
    echo $note;
}

$form->display();

if (!empty($result)) {
    echo $result;
}

echo $OUTPUT->footer();
