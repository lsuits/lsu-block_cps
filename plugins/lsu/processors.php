<?php

require_once dirname(__FILE__) . '/lib.php';

class lsu_semesters extends lsu_source implements semester_processor {
    var $serviceId = 'MOODLE_SEMESTERS';

    function parse_term($term) {
        $year = (int)substr($term, 0, 4);

        $semester_code = substr($term, -2);

        switch ($semester_code) {
            case self::FALL: return array($year - 1, 'Fall');
            case self::SPRING: return array($year, 'Spring');
            case self::SUMMER: return array($year, 'Summer');
            case self::WINTER_INT: return array($year - 1, 'WinterInt');
            case self::SPRING_INT: return array($year, 'SpringInt');
            case self::SUMMER_INT: return array($year, 'SummerInt');
        }
    }

    function semesters($date_threshold) {
        $xml_semesters = $this->invoke(array($date_threshold));

        $lookup = array();
        $semesters = array();

        foreach($xml_semesters->ROW as $xml_semester) {
            $code = $xml_semester->CODE_VALUE;

            $term = (string) $xml_semester->TERM_CODE;

            $session = (string) $xml_semester->SESSION;

            $date = $this->parse_date($xml_semester->CALENDAR_DATE);

            switch ($code) {
                case 'CLSB':
                case 'PSTGRD':
                    $campus = 'LSU';
                    $starting = ($code == 'CLSB');
                    break;
                case 'LAWB':
                case 'LFGDF':
                    $campus = 'LAW';
                    $starting = ($code == 'LAWB');
                    break;
                default: continue;
            }

            if (!isset($lookup[$campus])) {
                $lookup[$campus] = array();
            }

            if ($starting) {
                list($year, $name) = $this->parse_term($term);

                $semester = new stdClass;
                $semester->year = $year;
                $semester->name = $name;
                $semester->campus = $campus;
                $semester->session_key = $session;
                $semester->class_start = $date;

                $semesters[] = $semester;
            } else if (isset($lookup[$campus][$term]) and
                $lookup[$campus][$term]->session_key == $session) {

                $semester =& $lookup[$campus][$term];
                $semester->grades_due = $date;

            } else {
                continue;
            }

            $lookup[$campus][$term] = $semester;
        }

        return $semesters;
    }
}

class lsu_courses extends lsu_source implements course_processor {
    var $serviceId = 'MOODLE_COURSES';

    function courses(stdClass $semester) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $courses = array();

        $campus = ($semester->campus == 'LSU') ? '01' : '08';

        $xml_courses = $this->invoke(array($campus, $semester_term, $semester->session_key));

        foreach ($xml_courses->ROW as $xml_course) {
            $department = (string) $xml_course->DEPT_CODE;
            $course_number = (string) $xml_course->COURSE_NBR;

            $is_unique = function ($course) use ($department, $course_number) {
                return ($course->department != $department and
                    $course->cou_number != $course_number);
            };

            if (!$course or !$is_unique($course)) {
                $course = new stdClass;
                $course->department = $department;
                $course->cou_number = $course_number;
                $course->fullname = (string) $xml_course->COURSE_TITLE;

                $course->course_type = (string) $xml_course->CLASS_TYPE;
                $course->course_grade_type = (string) $xml_course->GRADE_SYSTEM_CODE;
                $course->course_first_year = (int) $xml_course->COURSE_NBR < 5200 ? 1 : 0;

                $course->sections = array();

                $courses[] = $course;
            }

            $section = new stdClass;
            $section->sec_number= (string) $xml_course->SECTION_NBR;

            $course->sections[] = $section;
        }

        return $courses;
    }
}

class lsu_teachers extends lsu_source implements teacher_processor {
    var $serviceId = 'MOODLE_INSTRUCTORS';

    function teachers(stdClass $semester, stdClass $course, stdClass $section) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $params = array($course->cou_number, $section->sec_number,
            $course->department, '01', $semester_term, $semester->session_key);

        $xml_teachers = $this->invoke($params);

        $teacher_mapper = function ($xml_teacher) {
            list($lastname, $first) = $this->parse_name($xml_teacher->INDIV_NAME);

            $primary_flag = trim($xml_teacher->PRIMARY_INSTRUCTOR);

            $teacher = new stdClass;

            $teacher->username = (string) $xml_teacher->PRIMARY_ACCESS_ID;
            $teacher->idnumber = (string) $xml_teacher->LSU_ID;
            $teacher->firstname = $first;
            $teacher->lastname = $lastname;

            $teacher->primary_flag = empty($primary_flag) ? 0 : 1;

            return $teacher;
        };

        return empty($xml_teachers->ROW) ?
            array() :
            array_map($teacher_mapper, $xml_teachers->ROW);
    }
}

class lsu_students extends lsu_source implements student_processor {
    var $serviceId = 'MOODLE_STUDENTS_1';

    function students(stdClass $semester, stdClass $course, stdClass $section) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $params = array('01', $semester_term, $course->department,
            $course->cou_number, $section->sec_number, $semester->session_key);

        $xml_students = $this->invoke($params);

        $student_mapper = function ($xml_student) {
            list($lastname, $firstname) = $this->parse_name($xml_student->INDIV_NAME);

            $student = new stdClass;

            $student->username = (string) $xml_student->PRIMARY_ACCESS_ID;
            $student->idnumber = (string) $xml_student->LSU_ID;

            $student->student_credit_hours = (string) $xml_student->CREDIT_HRS;
            $student->user_ferpa = (string) $xml_student->WITHHOLD_DIR_FLG;

            return $student;
        };

        return empty($xml_students->ROW) ?
            array() :
            array_map($student_mapper, $xml_students->ROW);
    }
}

class lsu_student_data extends lsu_source {
    var $serviceId = 'MOODLE_STUDENTS_2';

    function student_data(stdClass $semester) {
        $semester_term = $this->encode_semester($semester->year, $semester->name);

        $student_data = array();
        foreach (array('1590', '1595') as $instituition) {
            $xml_data = $this->invoke(array($semester_term, $instituition));

            foreach ($xml_data->ROW as $xml_student_data) {
                $stud_data = new stdClass;

                $reg = trim($xml_student_data->REGISTRATION_DATE);

                $stud_data->user_year = (string) $xml_student_data->YEAR_CLASS;
                $stud_data->user_college = (string) $xml_student_data->COLLEGE_CODE;
                $stud_data->user_major = (string) $xml_student_data->CURRIC_CODE;
                $stud_data->user_reg_status = empty($reg) ? NULL : $this->parse_date($reg);
                $stud_data->user_keypadid = (string) $xml_student_data->KEYPADID;
                $stud_data->idnumber = (string) $xml_student_data->LSU_ID;

                $student_data[] = $stud_data;
            }
        }

        return $student_data;
    }
}
