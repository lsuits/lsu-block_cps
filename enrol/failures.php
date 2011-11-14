<?php

require_once '../../config.php';
require_once 'publiclib.php';

cps::require_daos();

require_login();

if (!is_siteadmin($USER->id)) {
    redirect('/my');
}

$errorids = optional_param('ids', null, PARAM_INT);
$reprocess_all = optional_param('reprocess_all', null, PARAM_TEXT);
$delete_all = optional_param('delete_all', null, PARAM_TEXT);

$_s = cps::gen_str();

$base_url = new moodle_url('/admin/settings.php', array(
    'section' => 'enrolsettingscps'
));

$blockname = $_s('pluginname');

$action = $_s('reprocess_failures');

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_title($blockname. ': '. $action);
$PAGE->set_heading($blockname. ': '. $action);
$PAGE->set_url('/enrol/cps/cleanup.php');
$PAGE->set_pagetype('admin-settings-cps-semester-cleanup');
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add($blockname, $base_url);

$PAGE->navbar->add($action);

// LSU requirement
$PAGE->requires->js(new moodle_url('/lib/jquery.js'));
$PAGE->requires->js(new moodle_url('/enrol/cps/js/failure.js'));

echo $OUTPUT->header();
echo $OUTPUT->heading($action);

if ($reprocess_all or $delete_all) {
    $posted = cps_error::get_all();
} else if ($errorids and is_array($errorids)) {
    $ids = implode(',', $errorids);
    $posted = cps_error::get_select('id IN ('.$ids.')');
} else {
    $posted = array();
}

if ($posted and $data = data_submitted()) {
    $reprocessing = ($reprocess_all or isset($data->reprocess));

    if ($reprocessing) {
        $handler = function($out) use ($posted) {
            $msg = cps::_s('reprocess_success');

            echo $out->notification($msg, 'notifysuccess');
            echo html_writer::tag('pre', cps::reprocess_errors($posted));
        };
    } else {
        $handler = function($out) use ($posted) {
            foreach ($posted as $error) {
                cps_error::delete($error->id);
            }

            $msg = cps::_s('delete_success');
            echo $out->notification($msg, 'notifysuccess');
        };
    }

    $url = new moodle_url('/enrol/cps/failures.php');

    output_box_and_die($url, $handler);
}

$errors = cps_error::get_all();

if (empty($errors)) {
    output_box_and_die($base_url, function($out) use ($_s) {
        echo $out->notification($_s('no_errors'), 'notifysuccess');
    });
}

$table = new html_table();

$table->head = array(
    get_string('name'), $_s('error_params'), $_s('error_when'),
    html_writer::checkbox('select_all', 1, false, get_string('select'))
);

$table->data = array();

foreach ($errors as $error) {
    $params = unserialize($error->params);

    $line = array(
        $error->name,
        html_writer::tag('pre', print_r($params, true)),
        date('Y-m-d h:i:s a', $error->timestamp),
        html_writer::checkbox('ids[]', $error->id, false)
    );

    $table->data[] = new html_table_row($line);
}

echo $OUTPUT->heading($_s('reprocess_count', count($errors)));

echo html_writer::start_tag('form', array('method' => 'POST'));
echo html_writer::table($table);

echo html_writer::empty_tag('input', array(
    'name' => 'reprocess_all',
    'type' => 'submit',
    'value' => $_s('reprocess_all')
));

echo html_writer::empty_tag('input', array(
    'name' => 'reprocess',
    'type' => 'submit',
    'disabled' => 'disabled',
    'value' => $_s('reprocess_selected')
));

echo html_writer::empty_tag('input', array(
    'name' => 'delete_all',
    'type' => 'submit',
    'value' => $_s('delete_all')
));

echo html_writer::empty_tag('input', array(
    'name' => 'delete',
    'type' => 'submit',
    'disabled' => 'disabled',
    'value' => $_s('delete_selected')
));

echo html_writer::end_tag('form');

echo $OUTPUT->footer();

function output_box_and_die($base_url, $middle) {
    global $OUTPUT;

    echo $OUTPUT->box_start();
    $middle($OUTPUT);
    echo $OUTPUT->box_end();
    echo $OUTPUT->continue_button($base_url);
    echo $OUTPUT->footer();
    die();
}
