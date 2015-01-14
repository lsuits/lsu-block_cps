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
require_once $CFG->dirroot . '/enrol/ues/publiclib.php';
ues::require_daos();

interface verifiable {
    public static function is_valid($semesters);
}

abstract class cps_preferences extends ues_external implements verifiable {
    public static function settings() {
        $settings = array('creation', 'split', 'crosslist',
            'team_request', 'material', 'unwant', 'setting');

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

    public static function is_enabled() {
        global $USER;

        // Allow admins to login as instructors and by-pass disabled settings
        // to pre-build courses for them
        if (isset($USER->realuser) and is_siteadmin($USER->realuser)) {
            return true;
        } else {
            $setting = self::call('get_name');

            return (bool) get_config('block_cps', $setting);
        }
    }

    public static function name() {
        return get_string(self::call('get_name'), 'block_cps');
    }

    public static function is_valid($semesters) {
        return !empty($semesters);
    }
}

interface application {
    function apply();
}

interface undoable {
    function unapply();
}

interface unique extends application {
    function new_idnumber();
}

abstract class ues_section_accessor extends cps_preferences {
    var $section;

    public function section() {
        if (empty($this->section)) {
            $section = ues_section::get(array('id' => $this->sectionid));

            $this->section = $section;
        }

        return $this->section;
    }
}

abstract class ues_user_section_accessor extends ues_section_accessor {
    var $user;

    public function user() {
        if (empty($this->user)) {
            $user = ues_user::get(array('id' => $this->userid));

            $this->user = $user;
        }

        return $this->user;
    }
}

abstract class manifest_updater extends ues_user_section_accessor implements unique {
    public function save() {
        $updated = !empty($this->id);
        $updated = parent::save() && $updated;

        if ($updated) {
            $this->update_manifest();
        }

        return true;
    }

    public function update_manifest() {
        global $DB;

        // Only on update
        if (empty($this->id)) {
            return false;
        }

        $section = $this->section();
        $course = $section->moodle();
        $new_idnumber = $this->new_idnumber();

        // Nothing to do
        if (empty($course)) {
            return false;
        }

        // Allow event to rename course
        events_trigger('ues_course_create', $course);

        // Change association if there exists no other course.
        // This would prevent an unnecessary course creation
        $n = $DB->get_record('course', array('idnumber' => $new_idnumber));
        if (empty($n) and $course->idnumber != $new_idnumber) {
            $course->idnumber = $new_idnumber;
        }

        return $DB->update_record('course', $course);
    }
}

// Begin Concrete classes
class cps_unwant extends ues_user_section_accessor implements application, undoable {
    var $sectionid;
    var $userid;

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
        $section = $this->section();

        // Severage is happening in eventslib.php
        ues::unenroll_users(array($section));
    }

    function unapply() {
        $section = $this->section();

        ues::enroll_users(array($section));
    }
}

class cps_material extends cps_preferences implements application, undoable {
    var $userid;
    var $courseid;
    var $moodleid;

    private $ues_course;
    private $user;
    private $moodle;

    public function moodle() {
        global $DB;

        if (empty($this->moodle)) {
            if ($this->moodleid) {
                $params = array('id' => $this->moodleid);
            } else {
                $params = array('shortname' => $this->build_shortname());
            }

            $this->moodle = $DB->get_record('course', $params);
        }

        return $this->moodle;
    }

    public function course() {
        if (empty($this->ues_course) and $this->courseid) {
            $this->ues_course = ues_course::by_id($this->courseid);
        }

        return $this->ues_course;
    }

    public function user() {
        if (empty($this->user) and $this->userid) {
            $this->user = ues_user::by_id($this->userid);
        }

        return $this->user;
    }

    public function build_shortname() {
        $pattern = get_config('block_cps', 'material_shortname');

        $a = new stdClass;
        $a->department = $this->course()->department;
        $a->course_number = $this->course()->cou_number;
        $a->fullname = fullname($this->user());

        return ues::format_string($pattern, $a);
    }

    function unapply() {
        $mcourse = $this->moodle();

        if (empty($mcourse)) {
            return true;
        }

        $enrol = enrol_get_plugin('ues');
        $instance = $enrol->get_instance($mcourse->id);

        $enrol->unenrol_user($instance, $this->userid);
        return true;
    }

    function apply() {
        global $DB, $CFG;

        require_once $CFG->dirroot . '/course/lib.php';

        $shortname = $this->build_shortname();

        $mcourse = $DB->get_record('course', array('shortname' => $shortname));

        $enrol = enrol_get_plugin('ues');

        if (!$mcourse) {
            $category = $enrol->manifest_category($this->course());

            $course = new stdClass;
            $course->visible = 0;
            $course->numsections = get_config('moodlecourse', 'numsections');
            $course->format = get_config('moodlecourse', 'format');

            $course->fullname = $shortname;
            $course->shortname = $shortname;
            $course->summary = $shortname;
            $course->category = $category->id;

            $settings = cps_setting::get_all(ues::where()
                ->userid->equal($this->userid)
                ->name->starts_with('creation_')
            );

            foreach ($settings as $setting) {
                $key = str_replace('creation_', '', $setting->name);
                $course->$key = $setting->value;
            }

            $mcourse = create_course($course);
        }

        $instance = $enrol->get_instance($mcourse->id);

        $primary = $enrol->setting('editingteacher_role');
        $enrol->enrol_user($instance, $this->userid, $primary);

        $this->moodleid = $mcourse->id;

        return true;
    }
}

class cps_creation extends cps_preferences implements application {
    var $userid;
    var $semesterid;
    var $courseid;
    var $enroll_days;
    var $create_days;

    function apply() {
        $params = array(
            'semesterid' => $this->semesterid,
            'courseid' => $this->courseid
        );

        // All the sections for this course and semester
        $sections = ues_section::get_all($params);

        $userid = $this->userid;

        $by_teacher = function ($section) use ($userid) {
            $primary = $section->primary();

            if (empty($primary)) {
                $primary = current($section->teachers());
            }

            if (empty($primary)) return false;

            return $userid == $primary->userid;
        };

        $associated = array_filter($sections, $by_teacher);

        ues::inject_manifest($associated);
    }
}

class cps_setting extends cps_preferences {
    var $userid;
    var $name;
    var $value;

    public static function is_valid($semesters) {
        global $USER;
        return parent::is_valid($semesters) || is_siteadmin($USER->id);
    }

    public static function get_to_name($params) {
        $settings = self::get_all($params);

        $to_named_settings = array();
        foreach ($settings as $setting) {
            $to_named_settings[$setting->name] = $setting;
        }

        return $to_named_settings;
    }
}

class cps_split extends manifest_updater implements application, undoable {
    var $userid;
    var $sectionid;
    var $groupingid;

    public static function is_valid($semesters) {
        $valids = self::filter_valid($semesters);
        return !empty($valids);
    }

    public static function filter_valid_courses($courses) {
        return array_filter($courses, function ($course) {
            return count($course->sections) > 1;
        });
    }

    public static function filter_valid($semesters) {
        return array_filter($semesters, function($semester) {
            $courses = cps_split::filter_valid_courses($semester->courses);
            return count($courses) > 0;
        });
    }

    public static function in_course($course) {
        global $USER;

        if (empty($course->sections)) {
            $course->sections = array();

            $teacher = ues_teacher::get(array('id' => $USER->id));

            $sections = cps_unwant::active_sections_for($teacher, true);

            foreach ($sections as $section) {
                if ($section->courseid != $course->id) {
                    continue;
                }
                $course->sections[$section->id] = $section;
            }
        }

        $split_filters = ues::where()
            ->userid->equal($USER->id)
            ->sectionid->in(array_keys($course->sections));

        $splits = self::get_all($split_filters);

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

    function new_idnumber() {
        $section = $this->section();
        $semester = $section->semester();
        $session_key = $semester->session_key;

        $course = $section->course();

        $semstr = "$semester->year$semester->name$session_key";
        $coursestr = "$course->department$course->cou_number";

        $idnumber = "$semstr$coursestr{$this->userid}split{$this->groupingid}";

        return $idnumber;
    }

    function apply() {
        $sections = array($this->section());

        ues::inject_manifest($sections);
    }

    function unapply() {
        $sections = array($this->section());

        ues::inject_manifest($sections, function ($sec) {
            $sec->idnumber = '';
        });
    }
}

class cps_crosslist extends manifest_updater implements application, undoable {
    var $userid;
    var $sectionid;
    var $groupingid;
    var $shell_name;

    public static function is_valid($semesters) {
        // Must have two courses in the same semester
        $validation = function ($in, $semester) {
            return ($in || count($semester->courses) >= 2);
        };

        return array_reduce($semesters, $validation, false);
    }

    public static function in_courses(array $courses) {
        global $USER;

        // Flatten sections
        $course_to_sectionids = function ($in, $course) {
            return array_merge($in, array_keys($course->sections));
        };

        $sectionids = array_reduce($courses, $course_to_sectionids, array());

        $crosslist_params = ues::where()
            ->userid->equal($USER->id)
            ->sectionid->in($sectionids);

        $crosslists = self::get_all($crosslist_params);

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

    function new_idnumber() {
        $section = $this->section();
        $sem = $section->semester();
        $ses = $sem->session_key;

        $shell = str_replace(' ', '', $this->shell_name);
        $userid = $this->userid;

        $idnumber = "$sem->year$sem->name$ses{$shell}{$userid}cl{$this->groupingid}";
        return $idnumber;
    }

    function apply() {
        $section = $this->section();

        ues::inject_manifest(array($section));
    }

    function unapply() {
        $sections = array($this->section());

        ues::inject_manifest($sections, function ($section) {
            $section->idnumber = '';
        });
    }
}

// Request application involves emails
class cps_team_request extends cps_preferences implements application, undoable {
    var $semesterid;
    var $userid;
    var $courseid;
    var $requested;
    var $requested_course;
    var $approval_flag;

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
            return array_filter($all_together, function ($req) {
                return $req->approved();
            });
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

    public static function delete_all($params = array()) {
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
            $course = ues_course::get(array('id' => $this->requested_course));

            $this->other_course = $course;
        }

        return $this->other_course;
    }

    public function other_user() {
        if (empty($this->other_user)) {
            $this->other_user = ues_user::get(array('id' => $this->requested));
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
            $this->course = ues_course::get(array('id' => $this->courseid));
        }

        return $this->course;
    }

    public function owner() {
        if (empty($this->owner)) {
            $this->owner = ues_user::get(array('id' => $this->userid));
        }

        return $this->owner;
    }

    public function semester() {
        if (empty($this->semester)) {
            $this->semester = ues_semester::get(array('id' => $this->semesterid));
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
        $session = $sem->get_session_key();

        $label = "$sem->year $sem->name$session $course->department $course->cou_number";

        return $label . ' with ' . fullname($user);
    }

    public function sections() {
        if (empty($this->sections)) {
            $params = array('requestid' => $this->id);

            $this->sections = cps_team_section::get_all($params);
        }

        return $this->sections;
    }

    private function build_email_obj() {
        $requester = $this->owner();
        $requestee = $this->other_user();

        $course_name = function($course) {
            return "$course->department $course->cou_number";
        };

        $a = new stdClass;
        $a->requestee = fullname($requestee);
        $a->requester = fullname($requester);
        $a->other_course = $course_name($this->other_course());
        $a->course = $course_name($this->course());

        return $a;
    }

    function apply() {
        $_s = ues::gen_str('block_cps');

        $a = $this->build_email_obj();

        if ($this->approved()) {
            $subject_key = 'team_request_approved_subject';
            $body_key = 'team_request_approved_body';

            $to = $this->owner();
            $from = $this->other_user();
        } else {
            $subject_key = 'team_request_invite_subject';
            $body_key = 'team_request_invite_body';

            $url = new moodle_url('/blocks/cps/team_request.php');
            $a->link = $url->out(false);

            $to = $this->other_user();
            $from = $this->owner();
        }

        email_to_user($to, $from, $_s($subject_key), $_s($body_key, $a));
    }

    function unapply() {
        global $USER;

        $requester = $this->owner();
        $requestee = $this->other_user();

        $_s = ues::gen_str('block_cps');

        $a = $this->build_email_obj();

        if ($requester->id == $USER->id) {
            $subject_key = 'team_request_revoke_subject';
            $body_key = 'team_request_revoke_subject';

            $to = $requestee;
            $from = $requester;
        } else {
            $subject_key = 'team_request_reject_subject';
            $body_key = 'team_request_reject_body';

            $to = $requester;
            $from = $requestee;
        }

        // Cascading undo
        $children = $this->sections();
        foreach ($children as $child) {
            $child->delete($child->id);
            $child->unapply();
        }

        email_to_user($to, $from, $_s($subject_key), $_s($body_key, $a));
    }
}

class cps_team_section extends manifest_updater implements application, undoable {
    var $requesterid;
    var $courseid;
    var $sectionid;
    var $groupingid;
    var $shell_name;
    var $requestid;

    public static function in_requests(array $requests) {
        $sections = array();

        foreach ($requests as $request) {
            $internal = cps_team_section::get_all(ues::where()
                ->join('{enrol_ues_sections}', 'sec')->on('sectionid', 'id')
                ->sec->semesterid->equal($request->semesterid)
                ->requesterid->equal($request->userid)
                ->courseid->equal($request->courseid)
            );

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

    public function request() {
        if (empty($this->request)) {
            $this->request = cps_team_request::get(array('id' => $this->requestid));
        }

        return $this->request;
    }

    public function user() {
        if (empty($this->user)) {
            $this->user = ues_user::by_id($this->requesterid);
        }

        return $this->user;
    }

    public function course() {
        if (empty($this->course)) {
            $this->course = ues_course::by_id($this->courseid);
        }

        return $this->course;
    }

    function new_idnumber() {
        $section = $this->section();
        $sem = $section->semester();
        $ses = $sem->session_key;

        $requestid = "{$this->requesterid}_{$this->courseid}";

        $idnumber = "$sem->year$sem->name$ses{$requestid}tt{$this->groupingid}";

        return $idnumber;
    }

    function apply() {
        $section = $this->section();

        ues::inject_manifest(array($section));
    }

    function unapply() {
        ues::inject_manifest(array($this->section()), function($sec) {
            $sec->idnumber = '';
        });
    }
}
