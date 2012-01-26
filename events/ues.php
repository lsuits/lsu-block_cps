<?php

require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

abstract class cps_ues_handler {

    public static function ues_primary_change($params) {
        extract($params);

        // Empty enrollment / idnumber
        ues::unenroll_users(array($section));

        // Safe keeping
        $section->status = ues::PROCESSED;
        $section->save();

        // Set to re-enroll
        ues_student::reset_status($section, ues::PROCESSED);
        ues_teacher::reset_status($section, ues::PROCESSED);

        return true;
    }

    public static function ues_teacher_process($ues_teacher) {
        $threshold = get_config('block_cps', 'course_threshold');

        $course = $ues_teacher->section()->course();

        // Must abide by the threshold
        if ($course->cou_number >= $threshold) {
            $unwant_params = array(
                'userid' => $ues_teacher->userid,
                'sectionid' => $ues_teacher->sectionid
            );

            $unwant = cps_unwant::get($unwant_params);

            if (empty($unwant)) {
                $unwant = new cps_unwant();
                $unwant->fill_params($unwant_params);
                $unwant->save();
            }
        }

        return true;
    }

    public static function ues_teacher_release($ues_teacher) {
        // Check for promotion or demotion
        $params = array(
            'userid' => $ues_teacher->userid,
            'sectionid' => $ues_teacher->sectionid,
            'status' => ues::PROCESSED
        );

        $other_self = ues_teacher::get($params);

        if ($other_self) {
            $promotion = $other_self->primary_flag == 1;
            $demotion = $other_self->primary_flag == 0;
        } else {
            $promotion = $demotion = false;
        }

        $delete_params = array('userid' => $ues_teacher->userid);

        $all_section_settings = array('unwant', 'split', 'crosslist');

        if ($promotion) {
            // Promotion means all settings are in tact
            return true;
        } else if ($demotion) {
            // Demotion means crosslist and split behavior must be effected
            unset($all_section_settings[0]);
        }

        $by_successful_delete = function($in, $setting) use ($delete_params, $ues_teacher) {
            $class = 'cps_'.$setting;
            return $in && $class::delete_all($delete_params + array(
                'sectionid' => $ues_teacher->sectionid
            ));
        };

        $success = array_reduce($all_section_settings, $by_successful_delete, true);

        $creation_params = array(
            'courseid' => $ues_teacher->section()->courseid,
            'semesterid' => $ues_teacher->section()->semesterid
        );

        $success = (
            cps_creation::delete_all($delete_params + $creation_params) and
            cps_team_request::delete_all($delete_params + $creation_params) and
            $success
        );

        return $success;
    }

    public static function ues_section_process($section) {
        // Semesters are different here on campus.
        // Oddly enough, LAW courses and enrollments are tied to the
        // LSU campus, which means that we have to separate the logic here
        $semester = $section->semester();

        if ($section->course()->department == 'LAW') {
            $sem_params = array (
                'name' => $section->semester->name,
                'year' => $section->semester->year,
                'session_key' => $section->semester->session_key,
                'campus' => 'LAW'
            );

            $law_semester = ues_semester::get($sem_params);

            if ($law_semester) {
                $semester = $law_semester;
            }
        }

        $primary = $section->primary();

        // We know a teacher exists for this course, so we'll use a non-primary
        if (!$primary) {
            $primary = current($section->teachers());
        }

        // Unwanted interjection
        $unwanted = cps_unwant::get(array(
            'userid' => $primary->userid,
            'sectionid' => $section->id
        ));

        if ($unwanted) {
            $section->status = ues::PENDING;
            return true;
        }

        // Creation and Enrollment interjection
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

        $classes_start = $semester->classes_start;
        $diff = $classes_start - time();

        $diff_days = ($diff / 60 / 60 / 24);

        if ($diff_days > $creation->create_days) {
            $section->status = ues::PENDING;
            return true;
        }

        if ($diff_days > $creation->enroll_days) {
            ues_student::reset_status($section, ues::PENDING, ues::PROCESSED);
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

    public static function ues_section_drop($section) {
        $section_settings = array('unwant', 'split', 'crosslist', 'team_section');

        foreach ($section_settings as $settting) {
            $class = 'cps_' . $settting;

            $class::delete_all(array('sectionid' => $section->id));
        }

        return true;
    }

    public static function ues_semester_drop($semester) {
        $semester_settings = array('cps_creation', 'cps_team_request');

        foreach ($semester_settings as $class) {
            $class::delete_all(array('semesterid' => $semester->id));
        }

        return true;
    }

    public static function ues_course_create($course) {
        // Split, Crosslist, and Team teach manipulate the shortname
        // and fullname of a created course
        // We must consider these.

        $sections = ues_section::from_course($course);

        if (empty($sections)) {
            return true;
        }

        $section = reset($sections);

        $primary = $section->primary();

        if (empty($primary)) {
            return true;
        }

        $semester = $section->semester();
        $ues_course = $section->course();

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
            $a->department = $ues_course->department;
            $a->course_number = $ues_course->cou_number;
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

            $fullname = ues::format_string($pattern, $a);
            $shortname = ues::format_string($pattern, $a);
        }

        $course->fullname = $fullname;
        $course->shortname = $shortname;

        return true;
    }

    public static function ues_course_severed($course) {
        // This event only occurs when a Moodle course will no longer be
        // supported. Good news is that the section that caused this
        // severage will still be link to the idnumber until the end of the
        // unenrollment process

        // Should there be no grades, no activities, and no resources
        // we can safely assume that this course is no longer used

        $perform_delete = (bool) get_config('block_cps', 'course_severed');

        if (!$perform_delete) {
            return true;
        }

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

        $sections = ues_section::from_course($course);

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

    public static function ues_group_emptied($params) {
        return true;
    }
}
