<?php

interface enrollment_factory {
    // Returns a semester_processor
    function semester_source();

    // Returns a course_processor
    function course_source();

    // Returns a teacher_processor
    function teacher_source();

    // Retunrs a student_processor
    function student_source();
}

abstract class enrollment_provider implements enrollment_factory {
    function get_name() {
        return current(explode('_enrollment_provider', get_called_class()));
    }

    function get_setting($key, $default=false) {
        $moodle_setting = $this->get_name() . '_' . $key;

        $attempt = get_config('enrol_cps', $moodle_setting);

        // Try generated setting defaults first
        $reg_settings = $this->settings();

        if (isset($reg_settings[$key])) {
            $def = empty($reg_settings[$key]) ? $default : $reg_settings[$key];
        } else {
            $def = $default;
        }

        return empty($attempt) ? $def : $attempt;
    }

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
     * Return admin_setting_* classes for the $ADMIN config tree
     */
    function adv_settings() {
        return array();
    }
}
