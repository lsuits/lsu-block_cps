<?php

abstract class cps_simple_restore_handler {
    public static function simple_restore_complete($params) {
        extract($params);
        extract($course_settings);

        // This is an import, ignore
        if ($restore_to == 1) {
            return true;
        }

        global $DB, $CFG;

        $keep_enrollments = (bool) get_config('simple_restore', 'keep_roles_and_enrolments');
        $keep_groups = (bool) get_config('simple_restore', 'keep_groups_and_groupings');

        $enrol_instances = $DB->get_records('enrol', array(
            'courseid' => $id,
            'enrol' => 'ues'
        ));

        // No need to re-enroll
        if ($keep_groups and $keep_enrollments) {
            // Cleanup old instances
            $ues = enrol_get_plugin('ues');

            foreach (array_slice($enrol_instances, 1) as $instance) {
                $ues->delete_instance($instance);
            }

            return true;
        }

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

        $sections = ues_section::from_course($course);

        // Nothing to do
        if (empty($sections)) {
            return true;
        }

        // Rebuild enrollment
        ues::enroll_users(ues_section::from_course($course));

        return $success;
    }
}
