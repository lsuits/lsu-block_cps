<?php

class fake_semesters implements semester_processor {
    function semesters($date_threshold) {
        $one_day = 24 * 60 * 60;
        $one_week = 7 * $one_day;
        $semester_length = 120 * $one_day;

        $now = strtotime($date_threshold);

        $first = new stdclass;
        $first->year = 2011;
        $first->name = 'Fall';
        $first->campus = 'Fake';
        $first->session_key = '';
        $first->classes_start = $now;
        $first->grades_due = $now + $semester_length;

        $second = new stdClass;
        $second->year = 2012;
        $second->name = 'Spring';
        $second->campus = 'Fake';
        $second->session_key = '';
        $second->classes_start = $first->grades_due + $one_week;

        return array($first, $second);
    }
}

class fake_courses implements course_processor {
    var $course_max;
    var $section_max;

    function __construct($course_variant, $section_variant) {
        $this->course_max = $course_variant;
        $this->section_max = $section_variant;
    }

    function courses($semester) {
        $alpha = range('a', 'z');

        $course_range = range(0, $this->course_max);

        $courses = array();

        foreach ($course_range as $i) {
            $letter = strtoupper($alpha[$i]);

            $course = new stdClass;

            $course->department = $letter . $letter . $letter;
            $course->cou_number = rand(1001, 4999);
            $course->fullname = $course->department . ' in ' . $semester->name . ' ' . $semester->year;

            $course->sections = array();

            $max_sections = rand(1, $this->section_max);
            foreach (range(1, $max_sections) as $i => $section_num) {
                $section = new stdClass;

                $section->sec_number = '00' . $section_num;

                $course->sections[] = $section;
            }

            $courses[] = $course;
        }

        return $courses;
    }
}

class fake_teachers implements teacher_processor {

    var $teacher_names = array(
        'Tommy Jones', 'Kyle Blart', 'Jefferson Thompson', 'Kreed Spokane',
        'Yielder Todd', 'Yason Jason', 'Josea Bordi', 'Adama Zapetailia',
        'Jordi Wheeler', 'Random Part', 'Lastly Border'
    );

    function __construct($teacher_variant) {
        $this->teacher_variant = $teacher_variant;
    }

    function teachers($semester, $course, $section) {
        $teachers = array();

        $num = end(str_split($section->sec_number));

        $i = rand(0, 10);

        list($firstname, $lastname) = explode(' ', $this->teacher_names[$i]);

        $primary = new stdClass;

        $primary->primary_flag = 1;
        $primary->idnumber = sprintf('1230000%d', ($i + 1));
        $primary->username = strtolower($firstname);
        $primary->firstname = $firstname;
        $primary->lastname = $lastname;

        $teachers[] = $primary;

        if ($num % 2 != 0 and $this->teacher_variant > 1) {
            list($firstname, $lastname) = explode(' ', 'Second Teacher '. $i);
            $second->primary_flag = 0;
            $second->username = strtolower($lastname . $i);
            $second->idnumber = sprintf('123000%d0', ($i + 1));
            $second->firstname = $firstname;
            $second->lastname = $lastname . ' ' . ($i + 1);

            $teachers[] = $second;
        }

        return $teachers;
    }
}

class fake_students implements student_processor {

    function __construct($student_variant) {
        $this->student_variant = $student_variant;
    }

    function students($semester, $course, $section) {
        $student_num = range(1, $this->student_variant);

        $fun = function ($id) {
            $idnumber = sprintf('12300%d001', $id);

            $student = new stdClass;
            $student->firstname = 'Enrolled';
            $student->lastname = 'Student ' . $id;
            $student->idnumber = $idnumber;
            $student->username = 'student'.$id;

            return $student;
        };

        return array_map($fun, $student_num);
    }
}

class fake_enrollment_provider extends enrollment_provider {
    function semester_source() {
        return new fake_semesters();
    }

    function course_source() {
        return new fake_courses($this->course_variant, $this->section_variant);
    }

    function teacher_source() {
        return new fake_teachers($this->teacher_variant);
    }

    function student_source() {
        return new fake_students($this->student_variant);
    }

    function __construct() {
        foreach ($this->settings() as $key => $default) {
            $this->$key = $this->get_setting($key);

            $except_key = current(explode('_', $key));

            if ($this->$key == 0) {
                throw new Exception('invalid_' . $except_key);
            }
        }
    }

    function postprocess() {
        if ($this->get_setting('cleanuprun')) {
            require_once dirname(__FILE__) . '/lib.php';

            cleanup_fake_data();
        }
    }

    public static function settings() {
        return array(
            'course_variant' => 10,
            'section_variant' => 4,
            'teacher_variant' => 2,
            'student_variant' => 20
        );
    }

    public static function adv_settings() {
        require_once dirname(__FILE__) . '/adminlib.php';

        $_s = cps_enrollment::gen_str();

        return array(
            new admin_setting_heading('enrol_cps_fake_linkables',
            $_s('fake_linkables'), ''),

            new admin_setting_link('enrol_cps/fake_cleanup',
            $_s('fake_cleanup'), $_s('fake_cleanup_desc'),
            '/enrol/cps/plugins/fake/cleanup.php'),

            new admin_setting_configcheckbox('enrol_cps/fake_cleanuprun',
            $_s('fake_cleanuprun'), $_s('fake_cleanuprun_desc'), 0)
        );
    }
}
