<?php

abstract class cps_simple_restore_handler {
    public static function simple_restore_complete($params) {
        extract($params);
        $restore_to = $course_settings['restore_to'];
        $old_course = $course_settings['course'];

        // This is an import, ignore
        if ($restore_to == 1) {
            return true;
        }

        global $DB, $CFG;

        $course = $DB->get_record('course', array('id' => $old_course->id));

        $keep_enrollments = (bool) get_config('simple_restore', 'keep_roles_and_enrolments');
        $keep_groups = (bool) get_config('simple_restore', 'keep_groups_and_groupings');

        $skip = array('id', 'category', 'sortorder', 'modinfo', 'newsitems');

        // Maintain the correct config
        foreach (get_object_vars($old_course) as $key => $value) {
            if (in_array($key, $skip)) {
                continue;
            }

            $course->$key = $value;
        }

        $DB->update_record('course', $course);

        // No need to re-enroll
        if ($keep_groups and $keep_enrollments) {
            $enrol_instances = $DB->get_records('enrol', array(
                'courseid' => $old_course->id,
                'enrol' => 'ues'
            ));

            // Cleanup old instances
            $ues = enrol_get_plugin('ues');

            foreach (array_slice($enrol_instances, 1) as $instance) {
                $ues->delete_instance($instance);
            }

        } else {
            require_once $CFG->dirroot . '/enrol/ues/publiclib.php';
            ues::require_daos();

            $sections = ues_section::from_course($course);

            // Nothing to do
            if (empty($sections)) {
                return true;
            }

            // Rebuild enrollment
            ues::enroll_users(ues_section::from_course($course));
        }

        return true;
    }
}
