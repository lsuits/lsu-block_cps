<?php

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

    public static function cps_section_process($params) {
        return true;
    }

    public static function cps_course_create($params) {
        return true;
    }

    public static function cps_course_severed($course) {
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
