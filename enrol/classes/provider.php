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

    // Returns teacher enrollment information for a given department
    function teacher_department_source();

    // Returns student enrollment information for a given department
    function student_department_source();
}

abstract class enrollment_provider implements enrollment_factory {

    function get_setting($key, $default=false) {

        $attempt = get_config('enrol_cps', $this->setting_key($key));

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

    function supports_reverse_lookups() {
        $source = $this->teacher_info_source();
        return !empty($source);
    }

    // Optionally return a source for reverse lookups
    function teacher_info_source() {
        return null;
    }

    function setting_key($key) {
        return $this->get_name() . '_' . $key;
    }

    public static function get_name() {
        return current(explode('_enrollment_provider', get_called_class()));
    }

    /**
     * Return key / value pair of potential $CFG->$name_key_$key values
     * The values become default values. Entries are assumed to be textboxes
     */
    public static function settings() {
        return array();
    }

    /**
     * Return admin_setting_* classes for the $ADMIN config tree
     */
    public static function adv_settings() {
        return array();
    }
}
