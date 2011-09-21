<?php

require_once dirname(__FILE__) . '/lib.php';

class cps_semester extends cps_dao {
    var $sections;

    public static function in_session() {
        $now = time();

        $filters = array(
            'classes_start >= ' . $now,
            'grades_due <= ' . $now
        );

        return self::get_select($filters, true);
    }

    public function sections() {
        if (empty($this->sections)) {
            $sections = cps_section::get_all(array('semesterid' => $this->id));

            $this->sections = $sections;
        }

        return $this->sections;
    }

    public function __toString() {
        $session = !empty($this->session_key) ? ' Session '. $this->session_key : '';
        return sprintf('%s %s%s at %s', $this->year, $this->name, $session, $this->campus);
    }
}

class cps_course extends cps_dao {
    var $sections;
    var $teachers;
    var $student;

    public static function by_department($dept) {
        return cps_course::get_all(array('department' => $dept), true);
    }

    public static function merge_sections(array $sections) {
        $courses = array();

        foreach ($sections as $section) {
            $courseid = $section->courseid;

            if (!isset($courses[$courseid])) {
                $course = $section->course();
                $course->sections = array();
                $courses[$courseid] = $course;
            }

            $courses[$courseid]->sections[$section->id] = $section;
        }

        return $courses;
    }

    public function teachers($semester = null) {
        if (empty($this->teachers)) {
            $filters = $this->section_filters($semester);

            $filters[] = 'primary_flag = 1';

            $this->teachers = cps_teacher::get_select($filters);
        }

        return $this->teachers;
    }

    public function students($semester = null) {
        if (empty($this->students)) {
            $filters = $this->section_filters($semester);

            $this->students = cps_student::get_select($filters);
        }

        return $this->students;
    }

    public function sections($semester = null) {
        if (empty($this->sections)) {
            $by_params = array('courseid' => $this->id);

            if ($semester) {
                $by_params['semesterid'] = $semester->id;
            }

            $this->sections = cps_section::get_all($by_params);
        }

        return $this->sections;
    }

    public function __toString() {
        return sprintf('%s %s', $this->department, $this->cou_number);
    }

    private function section_filters($semester = null) {
        $sectionids = implode(',', array_keys($this->sections($semester)));

        $filters = array (
            'sectionid IN (' . $sectionids . ')',
            "(status = '".cps::PROCESSED."' OR status ='".cps::ENROLLED."')"
        );

        return $filters;
    }
}

class cps_section extends cps_dao {
    var $semester;
    var $course;
    var $moodle;

    var $primary;
    var $teachers;

    public function primary() {
        if (empty($this->primary)) {
            $teachers = $this->teachers();

            $primaries = function ($t) { return $t->primary_flag; };

            $this->primary = current(array_filter($teachers, $primaries));
        }

        return $this->primary;
    }

    public function teachers() {
        if (empty($this->teachers)) {
            $params = array(
                'sectionid = '.$this->id,
                '(status = "'.cps::ENROLLED.'" OR status = "'.cps::PROCESSED.'")'
            );
            $this->teachers = cps_teacher::get_select($params);
        }

        return $this->teachers;
    }

    public function semester() {
        if (empty($this->semester)) {
            $semester = cps_semester::get(array('id' => $this->semesterid));

            $this->semester = $semester;
        }

        return $this->semester;
    }

    public function course() {
        if (empty($this->course)) {
            $course = cps_course::get(array('id' => $this->courseid));

            $this->course = $course;
        }

        return $this->course;
    }

    public function moodle() {
        if (empty($this->moodle)) {
            global $DB;

            $course_params = array('idnumber' => $this->idnumber);
            $this->moodle = $DB->get_record('course', $course_params);
        }

        return $this->moodle;
    }

    public function is_manifested() {
        global $DB;

        // Clearly it hasn't
        if (empty($this->idnumber)) {
            return false;
        }

        $moodle = $this->moodle();

        return $moodle ? true : false;
    }

    public function __toString() {
        if ($this->course and $this->semester) {
            $course = $this->course;
            $semester = $this->semester;

            return sprintf('%s %s %s %s %s', $semester->year, $semester->name,
                $course->department, $course->cou_number, $this->sec_number);
        }

        return 'Section '. $this->sec_number;
    }

    /** Expects a Moodle course, returns an optionally full cps_section */
    public static function from_course(stdClass $course, $fill = true) {
        $sections = cps_section::get_all(array('idnumber' => $course->idnumber));

        if ($sections and $fill) {
            foreach ($sections as $section) {
                $section->course();
                $section->semester();
            }
        }

        return $sections;
    }
}

abstract class user_handler extends cps_dao {
    var $section;
    var $user;

    protected function qualified() {
        return array(
            'userid = ' . $this->userid,
            '(status = "'.cps::ENROLLED.'" OR status = "'.cps::PROCESSED.'")'
        );
    }

    public function section() {
        if (empty($this->section)) {
            $section = cps_section::get(array('id' => $this->sectionid));

            $this->section = $section;
        }

        return $this->section;
    }

    public function user() {
        if (empty($this->user)) {
            $user = cps_user::get(array('id' => $this->userid), true,
                'id, firstname, lastname, username, email, idnumber');

            $this->user = $user;
        }

        return $this->user;
    }

    public static function reset_status($section, $to = 'pending', $from = 'enrolled') {
        $class = get_called_class();

        $class::update(
            array('status' => $to),
            array('sectionid' => $section->id, 'status' => $from)
        );
    }
}

class cps_teacher extends user_handler {
    var $sections;

    public function sections($is_primary = false) {
        if (empty($this->sections)) {
            $qualified = $this->qualified();

            if ($is_primary) {
                $qualified[] = 'primary_flag = 1';
            }

            $all_teaching = cps_teacher::get_select($qualified);
            $sections = array();
            foreach ($all_teaching as $teacher) {
                $section = $teacher->section();
                $sections[$section->id] = $section;
            }

            $this->sections = $sections;
        }

        return $this->sections;
    }
}

class cps_student extends user_handler {
    var $sections;

    public function sections() {
        if (empty($this->sections)) {
            $all_students = cps_student::get_select($this->qualified());

            $sections = array();
            foreach ($all_students as $student) {
                $section = $student->section();
                $sections[$section->id] = $section;
            }

            $this->sections = $sections;
        }

        return $this->sections;
    }
}

class cps_user extends cps_dao {

    public static function tablename() {
        return self::get_name();
    }

    public static function is_teacher($userid = null) {
        if (!$userid) {
            global $USER;
            $userid = $USER->id;
        }

        $filters = array (
            'userid = ' . $userid,
            '(status = "'.cps::PROCESSED.'" OR status = "'.cps::ENROLLED.'")'
        );

        $count = cps_teacher::count_select($filters);

        return !empty($count);
    }
}
