<?php

$events = array(
    'cps_primary_change',
    'cps_teacher_process',
    'cps_teacher_release',
    'cps_section_process',
    'cps_section_drop',
    'cps_semester_drop',
    'cps_course_create',
    'cps_course_severed',
    'cps_group_emptied'
);

$mapper = function ($event) {
    return array(
        'handlerfile' => '/blocks/cps/eventslib.php',
        'handlerfunction' => array('cps_event_handler', $event),
        'schedule' => 'instant'
    );
};

$handlers = array_combine($events, array_map($mapper, $events));
