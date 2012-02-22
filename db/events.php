<?php

$gen_mapper = function ($module) {
    return function ($event) use ($module) {
        return array(
            'handlerfile' => '/blocks/cps/events/'.$module.'.php',
            'handlerfunction' => array('cps_' . $module . '_handler', $event),
            'schedule' => 'instant'
        );
    };
};

$modules_events = array(
    'ues' => array(
        'ues_primary_change',
        'ues_teacher_process',
        'ues_teacher_release',
        'ues_section_process',
        'ues_section_drop',
        'ues_semester_drop',
        'ues_course_create',
        'ues_course_severed',
        'ues_group_emptied'
    ),
    'simple_restore' => array(
        'simple_restore_complete'
    ),
    'ues_meta_viewer' => array(
        'ues_user_data_ui_keys',
        'ues_user_data_ui_element'
    ),
    'ues_people' => array(
        'ues_people_outputs'
    )
);

$handlers = array();

foreach ($modules_events as $module => $events) {
    $mapper = $gen_mapper($module);

    $handlers += array_combine($events, array_map($mapper, $events));
}
