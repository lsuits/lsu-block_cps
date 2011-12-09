<?php

$events = array(
    'ues_primary_change',
    'ues_teacher_process',
    'ues_teacher_release',
    'ues_section_process',
    'ues_section_drop',
    'ues_semester_drop',
    'ues_course_create',
    'ues_course_severed',
    'ues_group_emptied'
);

$mapper = function ($event) {
    return array(
        'handlerfile' => '/blocks/cps/eventslib.php',
        'handlerfunction' => array('ues_event_handler', $event),
        'schedule' => 'instant'
    );
};

$handlers = array_combine($events, array_map($mapper, $events));
