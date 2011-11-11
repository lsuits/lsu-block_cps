<?php

require_once '../../config.php';
require_once 'publiclib.php';

cps::require_daos();

require_login();

if (!is_siteadmin($USER->id)) {
    redirect('/my');
}

$errorids = optional_param('ids', null, PARAM_INT);

$_s = cps::gen_str();

$blockname = $_s('pluginname');

$action = $_s('reprocess_failures');

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_title($blockname. ': '. $action);
$PAGE->set_heading($blockname. ': '. $action);
$PAGE->set_url('/enrol/cps/cleanup.php');
$PAGE->set_pagetype('admin-settings-cps-semester-cleanup');
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add($blockname, new moodle_url('/admin/settings.php',
    array('section' => 'enrolsettingscps')
));

$PAGE->navbar->add($action);

echo $OUTPUT->header();
echo $OUTPUT->heading($action);

$errors = cps_error::get_all();

$table = new html_table();

$table->head = array(
    get_string('name'), $_s('error_params'), $_s('error_when')
);

$table->data = array();

foreach ($errors as $error) {
    $params = unserialize($error->params);

    $line = array(
        $error->name,
        html_writer::tag('pre', print_r($params, true)),
        date('Y-m-d h:i:s a', $error->timestamp)
    );

    $table->data[] = new html_table_row($line);
}

echo html_writer::table($table);

echo $OUTPUT->footer();
