<?php

require_once dirname(__FILE__) . '/lib.php';

class cps_semester extends cps_dao {

    public static function in_session() {
        $now = time();

        $filters = array(
            'classes_start >= ' . $now,
            'grades_due <= ' . $now
        );

        return self::get_select($filters, true);
    }
}

class cps_course extends cps_dao {

    public static function by_department($dept) {
        return cps_course::get_all(array('department' => $dept), true);
    }
}

class cps_section extends cps_dao {
    var $semester;
    var $course;

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
}

abstract class user_handler extends cps_dao {
    var $section;
    var $user;

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
}

class cps_teacher extends user_handler {
    // Teacher related methods go here
}

class cps_student extends cps_dao {
    // Student related methods go here
}

class cps_user extends cps_dao {

    public static function tablename() {
        return self::get_name();
    }

}
