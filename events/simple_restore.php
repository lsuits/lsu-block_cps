<?php

abstract class cps_simple_restore_handler {
    public static function simple_restore_complete($params) {
        extract($params);
        extract($course_settings);

        global $DB, $CFG;

        require_once $CFG->dirroot . '/enrol/ues/publiclib.php';
        ues::require_daos();

        $course = $DB->get_record('course', array('id' => $id));

        // Maintain the correct config
        $course->fullname = $fullname;
        $course->shortname = $shortname;
        $course->idnumber = $idnumber;
        $course->format = $format;
        $course->summary = $summary;
        $course->visible = $visible;

        $success = $DB->update_record('course', $course);

        // Rebuild enrollment
        ues::enroll_users(ues_section::from_course($course));

        return $success;
    }
}
