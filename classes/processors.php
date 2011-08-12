<?php

interface semester_processor {
    function semesters($date_threshold);
}

interface course_processor {
    function courses($semester_year, $semester_name, $semester_campus);
}

interface teacher_processor {
    function teachers($course_nbr, $course_dept, $section_nbr, $semester_year, $semester_name);
}

interface student_processor {
    function students($course_nbr, $course_dept, $section_nbr, $semester_year, $semester_name);
}
