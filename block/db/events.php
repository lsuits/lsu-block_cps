<?php

$events = array(
    'cps_primary_change',
    'cps_section_process',
    'cps_course_create',
    'cps_course_severed',
    'cps_group_emptied',
    'cps_student_enroll',
    'cps_student_unenroll'
);

$mapper = function ($event) {
    return array(
        'handlerfile' => '/blocks/cps/eventslib.php',
        'handlerfunction' => $event . '_handler',
        'schedule' => 'instant'
    );
};

$handlers = array_combine($events, array_map($mapper, $events));
