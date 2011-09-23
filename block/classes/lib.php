<?php

require_once $CFG->dirroot . '/enrol/cps/publiclib.php';
cps::require_daos();

abstract class cps_preferences extends cps_base {
    public static function settings() {
        $settings = array('creation', 'split', 'crosslist',
            'team_request', 'material', 'unwant');

        $remaining_settings = array();

        foreach ($settings as $setting) {
            $class = 'cps_' . $setting;

            if (!$class::is_enabled()) {
                continue;
            }

            $remaining_settings[$setting] = $class::name();
        }

        return $remaining_settings;
    }

    public static function get_all(array $params = array(), $fields = '*') {
        return self::get_all_internal($params, $fields);
    }

    public static function get(array $params, $fields = '*') {
        return current(self::get_all($params, $fields));
    }

    public static function get_select($filters) {
        return self::get_select_internal($filters);
    }

    public static function delete_all(array $params = array()) {
        return self::delete_all_internal($params);
    }

    public static function is_enabled() {
        $setting = self::call('get_name');

        return get_config('block_cps', $setting);
    }

    public static function name() {
        return get_string(self::call('get_name'), 'block_cps');
    }

}

interface verifiable {
    function is_valid($sections);
}

interface application {
    function apply();
}

interface undoable {
    function unapply();
}

// Begin Concrete classes
class cps_unwant extends cps_preferences implements application, undoable {
    public static function active_sections_for($teacher, $is_primary = true) {
        $sections = $teacher->sections($is_primary);

        return self::active_sections($sections, $teacher->userid);
    }

    public static function active_sections(array $sections, $userid = null) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        $unwants = cps_unwant::get_all(array('userid' => $userid));

        foreach ($unwants as $unwant) {
            if (isset($sections[$unwant->sectionid])) {
                unset($sections[$unwant->sectionid]);
            }
        }

        return $sections;
    }

    function apply() {
        $section = cps_section::get(array('id' => $this->sectionid));
        $user = cps_user::get(array('id' => $this->userid));

        // Severage is happening in eventslib.php
        cps::unenroll_users(array($section));
    }

    function unapply() {
        $section = cps_section::get(array('id' => $this->sectionid));
        $user = cps_user::get(array('id' => $this->userid));

        cps::enroll_users(array($section));
    }
}

class cps_material extends cps_preferences implements application {
    function apply() {
        global $DB, $CFG;

        require_once $CFG->dirroot . '/course/lib.php';

        $cps_course = cps_course::get(array('id' => $this->courseid));
        $user = cps_user::get(array('id' => $this->userid));

        $pattern = get_string('material_shortname', 'block_cps');

        $a = new stdClass;
        $a->department = $cps_course->department;
        $a->course_number = $cps_course->cou_number;
        $a->fullname = fullname($user);

        $shortname = cps::format_string($pattern, $a);

        $mcourse = $DB->get_record('course', array('shortname' => $shortname));

        $enrol = enrol_get_plugin('cps');

        if (!$mcourse) {
            $cat_params = array('name' => $cps_course->department);
            $cat = $DB->get_field('course_categories', 'id', $cat_params);

            $course = new stdClass;
            $course->visible = 0;
            $course->numsections = $enrol->setting('course_numsections');
            $course->format = $enrol->setting('course_format');

            $course->fullname = $shortname;
            $course->shortname = $shortname;
            $course->summary = $shortname;
            $course->category = $cat;

            $mcourse= create_course($course);
        }

        $instance = $enrol->get_instance($mcourse->id);

        $primary = $enrol->setting('editingteacher_role');
        $enrol->enrol_user($instance, $user->id, $primary);

        $this->moodleid = $mcourse->id;

        return true;
    }
}

class cps_creation extends cps_preferences {
}

class cps_setting extends cps_preferences {
}

class cps_split extends cps_preferences {
    public static function filter_valid($courses) {
        return array_filter($courses, function ($course) {
            return count($course->sections) > 1;
        });
    }

    public static function in_course($course) {
        global $USER;

        if (empty($course->sections)) {
            $course->sections = array();

            $teacher = cps_teacher::get(array('id' => $USER->id));

            $sections = cps_unwant::active_sections_for($teacher, true);

            foreach ($sections as $section) {
                $course->sections[$section->id] = $section;
            }
        }

        $course_section_ids = implode(',', array_keys($course->sections));

        $split_filters = array(
            'userid' => $USER->id,
            'sectionid IN (' . $course_section_ids . ')'
        );

        $splits = self::get_select($split_filters);

        return $splits;
    }

    public static function exists($course) {
        return self::in_course($course) ? true : false;
    }

    public static function groups($splits) {
        if (empty($splits)) {
            return 0;
        }

        return array_reduce($splits, function ($in, $split) {
            return $split->groupingid > $in ? $split->groupingid : $in;
        });
    }
}

class cps_crosslist extends cps_preferences {
    public static function in_courses(array $courses) {
        global $USER;

        // Flatten sections
        $course_to_sectionids = function ($course) {
            return implode(',', array_keys($course->sections));
        };

        $sectionids = implode(',', array_map($course_to_sectionids, $courses));

        $crosslist_params = array(
            'userid = ' . $USER->id,
            'sectionid IN (' . $sectionids . ')'
        );

        $crosslists = self::get_select($crosslist_params);

        return $crosslists;
    }

    public static function exists($course) {
        $courses = is_array($course) ? $course : array($course);

        return self::in_courses($courses) ? true : false;
    }

    public static function groups($crosslists) {
        if (empty($crosslists)) {
            return 0;
        }

        return array_reduce($crosslists, function ($in, $crosslist) {
            return $crosslist->groupingid > $in ? $crosslist->groupingid : $in;
        });
    }
}

class cps_team_request extends cps_preferences {

    var $semester;
    var $sections;

    var $owner;
    var $course;
    var $other_user;
    var $other_course;

    public static function in_course($course, $semester, $approved = false) {
        global $USER;

        $params = array(
            'userid' => $USER->id,
            'courseid' => $course->id,
            'semesterid' => $semester->id
        );

        $requests = cps_team_request::get_all($params);

        $params = array(
            'requested' => $USER->id,
            'requested_course' => $course->id,
            'semesterid' => $semester->id
        );

        $participants = cps_team_request::get_all($params);

        $all_together = $requests + $participants;

        if ($approved) {
            $rtn = array_filter($all_together, function ($req) {
                return $req->approved();
            });

            return $rtn;
        } else {
            return $all_together;
        }
    }

    public static function exists($course, $semester) {
        return self::in_course($course, $semester) ? true : false;
    }

    public static function groups($teamteaches) {
        if (empty($teamteaches)) {
            return 0;
        }

        $courseids = array();
        foreach ($teamteaches as $teamteach) {
            $courseids[] = $teamteach->requested_course;
        }

        return count(array_unique($courseids));
    }

    public static function delete($id) {
        $params = array('id' => $id);

        return self::delete_all_internal($params, function($table) use ($params) {
            $old = cps_team_request::get($params);

            $child_params = array('requestid' => $old->id);

            cps_team_section::delete_all($child_params);
        });
    }

    public static function delete_all(array $params) {
        return self::delete_all_internal($params, function ($t) use ($params) {
            $old = cps_team_request::get_all($params);

            foreach ($old as $request) {
                $child_params = array('requestid' => $request->id);

                cps_team_section::delete_all($child_params);
            }
        });
    }

    public static function filtered_master($requests, $userid = null) {
        if (empty($userid)) {
            global $USER;

            $userid = $USER->id;
        }

        return array_filter($requests, function ($req) use ($userid) {
            return $req->is_owner($userid);
        });
    }

    public function is_owner($from_userid = null) {
        if (!$from_userid) {
            global $USER;

            $from_userid = $USER->id;
        }

        return $from_userid == $this->userid;
    }

    public function approved() {
        return $this->approval_flag == 1;
    }

    public function other_course() {
        if (empty($this->other_course)) {
            $course = cps_course::get(array('id' => $this->requested_course));

            $this->other_course = $course;
        }

        return $this->other_course;
    }

    public function other_user() {
        if (empty($this->other_user)) {
            $this->other_user = cps_user::get(array('id' => $this->requested));
        }

        return $this->other_user;
    }

    public function other_teacher() {
        $course = $this->other_course();

        $teachers = $course->teachers($this->semester());

        foreach ($teachers as $teacher) {
            if ($teacher->userid == $this->requested) {
                return $teacher;
            }
        }

        return false;
    }

    public function course() {
        if (empty($this->course)) {
            $this->course = cps_course::get(array('id' => $this->courseid));
        }

        return $this->course;
    }

    public function owner() {
        if (empty($this->owner)) {
            $this->owner = cps_user::get(array('id' => $this->userid));
        }

        return $this->owner;
    }

    public function semester() {
        if (empty($this->semester)) {
            $this->semester = cps_semester::get(array('id' => $this->semesterid));
        }

        return $this->semester;
    }

    public function label($from_userid = null) {

        if ($this->is_owner($from_userid)) {
            $course = $this->other_course();
            $user = $this->other_user();
        } else {
            $course = $this->course();
            $user = $this->owner();
        }

        $sem = $this->semester();

        $label = "$sem->year $sem->name $course->department $course->cou_number";

        return $label . ' with ' . fullname($user);
    }

    public function sections() {
        if (empty($this->sections)) {
            $params = array('requestid' => $this->id);

            $this->sections = cps_team_section::get_all($params);
        }

        return $this->sections;
    }
}

class cps_team_section extends cps_preferences {
    var $section;

    public static function in_requests(array $requests) {
        $sections = array();

        foreach ($requests as $request) {
            $params = array('requestid' => $request->id);
            $internal = cps_team_section::get_all($params);

            $sections += $internal;
        }

        return $sections;
    }

    public static function in_sections($requests, $sections) {
        $all_sections = self::in_requests($requests);

        $correct = array();

        foreach ($all_sections as $id => $sec) {
            if (isset($sections[$sec->sectionid])) {
                $correct[$id] = $sec;
            }
        }
        return $correct;
    }

    public static function exists($section) {
        return cps_team_section::get(array('sectionid' => $section->id));
    }

    public static function groups($sections) {
        if (empty($sections)) {
            return 0;
        }

        return array_reduce($sections, function ($in, $sec) {
            return $sec->groupingid > $in ? $sec->groupingid : $in;
        });
    }

    public static function merge_groups($sections) {
        $merged = array();

        if (empty($sections)) {
            return $merged;
        }

        foreach (range(1, self::groups($sections)) as $number) {
            $by_number = function ($section) use ($number) {
                return $section->groupingid == $number;
            };

            $merged[$number] = array_filter($sections, $by_number);
        }

        return $merged;
    }

    public static function merge_groups_in_requests($requests) {
        return self::merge_groups(self::in_requests($requests));
    }

    public function section() {
        if (empty($this->section)) {
            $this->section = cps_section::get(array('id' => $this->sectionid));
        }

        return $this->section;
    }
}
