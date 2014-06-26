<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 *
 * @package    block_cps
 * @copyright  2014 Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

abstract class cps_profile_field_helper {

    public static function process($user, $shortname) {
        $category = self::get_category();

        $params = array(
            'categoryid' => $category->id,
            'shortname' => $shortname
        );

        $field = self::default_profile_field($params);

        $params = array(
            'userid' => $user->id,
            'fieldid' => $field->id
        );

        return self::set_profile_data($user->$shortname, $params);
    }

    public static function get_category() {
        global $DB;

        $catid = get_config('block_cps', 'user_field_catid');

        // Backup
        $sql = 'SELECT id FROM {user_info_category} LIMIT 1';

        $catid = empty($catid) ? $DB->get_field_sql($sql) : $catid;

        $params = array('id' => $catid);

        if (!$category = $DB->get_record('user_info_category', $params)) {
            $category = new stdClass;
            $category->name = get_string('user_info_category', 'block_cps');
            $category->sortorder = 1;

            $category->id = $DB->insert_record('user_info_category', $category);
        }

        return $category;
    }

    public static function default_profile_field($params) {
        global $DB;

        if (!$field = $DB->get_record('user_info_field', $params)) {
            $field = new stdClass;
            $field->shortname = $params['shortname'];
            $field->name = get_string($field->shortname, 'block_cps');
            $field->description = get_string('auto_field_desc', 'block_cps');
            $field->descriptionformat = 1;
            $field->datatype = 'text';
            $field->categoryid = $params['categoryid'];
            $field->locked = 1;
            $field->visible = 1;
            $field->param1 = 30;
            $field->param2 = 2048;

            $field->id = $DB->insert_record('user_info_field', $field);
        }

        return $field;
    }

    public static function set_profile_data($info, $params) {
        global $DB;

        if (!$data = $DB->get_record('user_info_data', $params)) {
            $data = new stdClass;
            $data->userid = $params['userid'];
            $data->fieldid = $params['fieldid'];

            $data->data = '';

            $data->id = $DB->insert_record('user_info_data', $data);
        }

        $data->data = $info;

        return $DB->update_record('user_info_data', $data);
    }

    public static function clear_field_data($user, $shortname) {
        global $DB;

        $category = self::get_category();

        $params = array(
            'categoryid' => $category->id,
            'shortname' => $shortname
        );

        $field = self::default_profile_field($params);

        $params = array(
            'fieldid' => $field->id,
            'userid' => $user->id
        );

        return $DB->delete_records('user_info_data', $params);
    }
}

abstract class cps_ues_handler {

    /**
     * 
     * @global type $DB
     * @param stdClass $user previously, this has been of type ues_user
     * @see enrol_ues_plugin::create_user
     * @return boolean
     */
    public static function user_updated($user) {
        global $DB;

        $firstname = cps_setting::get(array(
            'userid' => $user->id,
            'name' => 'user_firstname'
        ));

        // No preference or firstname is the same as preference
        if (empty($firstname) or $user->firstname == $firstname->value) {
            return true;
        }

        $user->firstname = $firstname->value;
        return $DB->update_record('user', $user);
    }

    public static function ues_primary_change($data) {
        // Empty enrollment / idnumber
        ues::unenroll_users(array($data->section));

        // Safe keeping
        $data->section->idnumber = '';
        $data->section->status = ues::PROCESSED;
        $data->section->save();

        // Set to re-enroll
        ues_student::reset_status($data->section, ues::PROCESSED);
        ues_teacher::reset_status($data->section, ues::PROCESSED);

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
        $semester = $section->semester();

        $primary = $section->primary();
        // @TODO debug this: 1 why  use current ? is the choice of teacher arbitrary ?
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

        $creation_settings = cps_setting::get_all(ues::where()
            ->userid->equal($primary->userid)
            ->name->starts_with('creation_')
        );

        $semester = $section->semester();
        $session = $semester->get_session_key();

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
            $a->session = $session;
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
            $a->session = $session;
            $a->shell_name = $crosslist->shell_name;
            $a->fullname = fullname($primary->user());

            $string_key = 'crosslist_shortname';
        }

        $team_teach = cps_team_section::get(array('sectionid' => $section->id));
        if ($team_teach) {
            $a->year = $semester->year;
            $a->name = $semester->name;
            $a->session = $session;
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

        // Instructor overrides only on creation
        if (empty($course->id)) {
            foreach ($creation_settings as $setting) {
                $key = str_replace('creation_', '', $setting->name);

                $course->$key = $setting->value;
            }
        }

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

    public static function ues_lsu_student_data_updated($user) {
        if (empty($user->user_keypadid)) {
            return cps_profile_field_helper::clear_field_data($user, 'user_keypadid');
        }

        return cps_profile_field_helper::process($user, 'user_keypadid');
    }

    // Accommodate the Generic XML provider.
    public static function ues_xml_student_data_updated($user) {
        self::ues_lsu_student_data_updated($user);
    }

    public static function ues_lsu_anonymous_updated($user) {
        if (empty($user->user_anonymous_number)) {
            return cps_profile_field_helper::clear_field_data($user, 'user_anonymous_number');
        }

        return cps_profile_field_helper::process($user, 'user_anonymous_number');
    }

    // Accommodate the Generic XML provider.
    public static function ues_xml_anonymous_updated($user) {
        mtrace(sprintf("xml_anon event triggered !"));
        self::ues_lsu_anonymous_updated($user);
    }

    public static function ues_group_emptied($params) {
        return true;
    }
}
