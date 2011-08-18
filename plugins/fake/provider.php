<?php

class fake_semesters implements semester_processor {
    function semesters($date_threshold) {
        return array();
    }
}

class fake_courses implements course_processor {
    function courses(stdclass $semester) {
        return array();
    }
}

class fake_teachers implements teacher_processor {
    function teachers(stdclass $semester, stdclass $course, stdclass $section) {
        return array();
    }
}

class fake_students implements student_processor {
    function students(stdclass $semester, stdclass $course, stdclass $section) {
        return array();
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

    public static function settings() {
        return array(
            'course_variant' => 10,
            'section_variant' => 4,
            'teacher_variant' => 2,
            'student_variant' => 20
        );
    }
}
