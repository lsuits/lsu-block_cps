<?php

interface semester_processor {
    function semesters($date_threshold);
}

interface course_processor {
    function courses(stdClass $semester);
}

interface teacher_processor {
    function teachers(stdClass $semester, stdClass $course);
}

interface student_processor {
    function students(stdClass $semester, stdClass $course);
}
