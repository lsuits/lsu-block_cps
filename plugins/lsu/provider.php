<?php

require_once 'processors.php';

class lsu_enrollment_provider extends enrollment_provider {
    var $name_key = 'lsu';

    function __construct() {
        // Get credentials and wsdl from settings
    }

    function semester_source() {
        return new lsu_semesters();
    }

    function course_source() {
        return new lsu_courses();
    }

    function teacher_source() {
        return new lsu_teachers();
    }

    function student_source() {
        return new lsu_students();
    }

    function postprocess() {
        // Get dynamic semesters, eventually
        $semesters = array();

        $source = new lsu_student_data();
        foreach ($semesters as $semester) {
            $datas = $source->student_data($semester->year, $semester->name);

            foreach ($datas as $data) {
                // Update each student record
            }
        }
    }
}
