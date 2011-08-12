<?php

interface enrollment_factory {
    // Returns a SemesterProcessor
    function semester_source();

    // Returns a CourseProcessor
    function course_source();

    // Returns a TeacherProcessor
    function teacher_source();

    // Retunrs a StudentProcessor
    function student_source();
}

abstract class enrollment_provider implements enrollment_factory {
    // Manditory override
    var $name_key;

    // Override for special behavior hooks
    function preprocess() {
        return true;
    }

    function postprocess() {
        return true;
    }

    /**
     * Return key / value pair of potential $CFG->$name_key_$key values
     * The values become default values. Entries are assumed to be textboxes
     */
    function settings() {
        return array();
    }

    /**
     * $settings is the $ADMIN tree, so users can override for
     * special admin config elements
     */
    function adv_settings(&$settings) {
    }
}
