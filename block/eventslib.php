<?php

require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

abstract class cps_event_handler {

    public static function cps_primary_change($params) {
        return true;
    }

    public static function cps_teacher_release($cps_teacher) {
        // TODO: clear out settings for this instructor
        return true;
    }

    public static function cps_section_process($section) {
        // Semesters are different here on campus.
        // Oddly enough, LAW courses and enrollments are tied to the
        // LSU campus, which means that we have to separate the logic here
        if ($section->course->department == 'LAW') {
            $sem_params = array (
                'name' => $section->semester->name,
                'year' => $section->semester->year,
                'session_key' => $section->semester->session_key,
                'campus' => 'LAW'
            );

            $law_semester = cps_semester::get($sem_params);

            $section->semester = $law_semester;
            $section->semesterid = $law_semester->id;
        }

        // Unwanted interjection
        $unwanted = cps_unwant::get(array('sectionid' => $section->id));
        if ($unwanted) {
            $section->status = cps::PENDING;
            return true;
        }

        $teacher_params = array(
            'sectionid' => $section->id,
            'primary_flag' => 1,
            'status' => cps::PROCESSED
        );

        // Creation and Enrollment interjection
        $primary = cps_teacher::get($teacher_params);

        // We know a teacher exists for this course, so we'll use a non-primary
        if (!$primary) {
            unset($teacher_params['primary_flag']);

            $primary = cps_teacher::get($teacher_params);
        }

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

        $classes_start = $section->semester()->classes_start;
        $diff = $classes_start - time();

        $diff_days = ($diff / 60 / 60 / 24);

        if ($diff_days > $creation->create_days) {
            $section->status = cps::PENDING;
            return true;
        }

        if ($diff_days > $creation->enroll_days) {
            cps_student::reset_status($section, cps::PENDING, cps::PROCESSED);
        }

        foreach (array('split', 'crosslist', 'team_section') as $setting) {
            $class = 'cps_'.$setting;
            $applied = $class::get(array('sectionid' => $section->id));

            if ($applied) {
                $section->idnumber = $applied->new_idnumber();
            }
        }

        return true;
    }

    public static function cps_course_create($course) {
        // Split, Crosslist, and Team teach manipulate the shortname
        // and fullname of a created course
        // We must consider these.

        $sections = cps_section::from_course($course);

        if (empty($sections)) {
            return true;
        }

        $section = reset($sections);

        $primary = $section->primary();

        if (empty($primary)) {
            return true;
        }

        $semester = $section->semester();
        $cps_course = $section->course();

        $owner_params = array(
            'userid' => $primary->userid,
            'sectionid' => $section->id
        );

        // Properly fold
        $fullname = $course->fullname;
        $shortname = $course->shortname;

        $a = new stdClass;

        $split = cps_split::get($owner_params);
        if ($split) {
            $a->year = $semester->year;
            $a->name = $semester->name;
            $a->department = $cps_course->department;
            $a->course_number = $cps_course->cou_number;
            $a->shell_name = $split->shell_name;
            $a->fullname = fullname($primary->user());

            $string_key = 'split_shortname';
        }

        $crosslist = cps_crosslist::get($owner_params);
        if ($crosslist) {
            $a->year = $semester->year;
            $a->name = $semester->name;
            $a->shell_name = $crosslist->shell_name;
            $a->fullname = fullname($primary->user());

            $string_key = 'crosslist_shortname';
        }

        $team_teach = cps_team_section::get(array('sectionid' => $section->id));
        if ($team_teach) {
            $a->year = $semester->year;
            $a->name = $semester->name;
            $a->shell_name = $team_teach->shell_name;

            $string_key = 'team_request_shortname';
        }

        if (isset($string_key)) {
            $pattern = get_config('block_cps', $string_key);

            $fullname = cps::format_string($pattern, $a);
            $shortname = cps::format_string($pattern, $a);
        }

        $course->fullname = $fullname;
        $course->shortname = $shortname;

        return true;
    }

    public static function cps_course_severed($course) {
        // This event only occurs when a Moodle course will no longer be
        // supported. Good news is that the section that caused this
        // severage will still be link to the idnumber until the end of the
        // unenrollment process

        // Should there be no grades, no activities, and no resources
        // we can safely assume that this course is no longer used
        global $DB;

        $res = $DB->get_records('resource', array('course' => $course->id));

        $grade_items_params = array(
            'courseid' => $course->id,
            'itemtype' => 'course'
        );

        $ci = $DB->get_record('grade_items', $grade_items_params);

        $grades = function($ci) use ($DB) {
            if (empty($ci)) {
                return false;
            }

            $count_params = array('itemid' => $ci->id);
            $grades = $DB->count_records('grade_grades', $count_params);

            return !empty($grades);
        };

        if (empty($res) and !$grades($ci)) {
            delete_course($course, false);
            return true;
        }

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
}
