<?php

require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

abstract class cps_event_handler {

    public static function cps_primary_change($params) {
        return true;
    }

    public static function cps_student_process($params) {
        return true;
    }

    public static function cps_teacher_process($params) {
        return true;
    }

    public static function cps_section_process($section) {
        // Unwanted interjection
        $unwanted = cps_unwant::get(array('sectionid' => $section->id));
        if ($unwanted) {
            $section->status = cps::PENDING;
            return true;
        }

        // Creation and Enrollment interjection
        $primary = cps_teacher::get(array(
            'sectionid' => $section->id,
            'primary_flag' => 1,
            'status' => cps::PROCESSED
        ));

        $creation_params = array(
            'userid' => $primary->userid,
            'semesterid' => $section->semesterid,
            'courseid' => $section->courseid
        );

        $creation = cps_creation::get($creation_params);
        if (!$creation) {
            $creation = new cps_creation();
            $creation->create_days = get_config('block_cps', 'create_days');
            $creation->enroll_days = get_config('block_cps', 'enroll_days');
        }

        $classes_start = $section->semester->classes_start;
        $diff = $classes_start - time();

        $diff_days = ($diff / 60 / 60 / 24);

        if ($diff_days > $creation->create_days) {
            $section->status = cps::PENDING;
            return true;
        }

        if ($diff_days > $creation->enroll_days) {
            cps_student::reset_status($section, cps::PENDING, cps::PROCESSED);
        }

        return true;
    }

    public static function cps_course_create($params) {
        return true;
    }

    public static function cps_course_severed($course) {
        // This event only occurs when a Moodle course will no longer be
        // supported. Good news is that the section that caused this
        // severage will still be link to the idnumber until the end of the
        // unenrollment process

        $sections = cps_section::from_course($course);

        if (empty($sections)) {
            return true;
        }

        $section = reset($sections);

        $primary = $section->primary();

        $by_params = array (
            'userid' => $primary->userid,
            'sectionid' => $section->id
        );

        if (cps_unwant::get($by_params)) {
            delete_course($course, false);
        }

        return true;
    }

    public static function cps_group_emptied($params) {
        return true;
    }

    public static function cps_student_enroll($params) {
        return true;
    }

    public static function cps_student_unenroll($params) {
        return true;
    }

    public static function user_updated($user) {
        return true;
    }
}
