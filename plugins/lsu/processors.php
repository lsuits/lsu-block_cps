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

            $term = sprintf('%s', $xml_semester->TERM_CODE);

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
                $semester->class_start = $date;

                $semesters[] = $semester;
            } else if (isset($lookup[$campus][$term])) {
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

    function courses($semester_year, $semester_name, $semester_campus) {
        $semester_term = $this->encode_semester($semester_year, $semester_name);

        // LSU and LAW ... Might change the query
        $courses = array();
        foreach (array('01', '08') as $campus) {
            $xml_courses = $this->invoke(array($campus, $semester_term));

            foreach ($xml_courses->ROW as $xml_course) {
                $course = new stdClass;
                $course->department = (string) $xml_course->DEPT_CODE;
                $course->course_number = (string) $xml_course->COURSE_NBR;
                $course->fullname = (string) $xml_course->COURSE_TITLE;
                $course->section_number = (string) $xml_course->SECTION_NBR;

                // Course Meta
                $course->course_type = (string) $xml_course->CLASS_TYPE;
                $course->grade_type = (string) $xml_course->GRADE_SYSTEM_CODE;
                $course->first_year = (int) $xml_course->COURSE_NBR < 5200 ? 1 : 0;

                $courses[] = $course;
            }
        }

        return $courses;
    }
}

class lsu_teachers extends lsu_source implements teacher_processor {
    var $serviceId = 'MOODLE_INSTRUCTORS';

    function teachers($course_nbr, $course_dept, $section_nbr, $semester_year, $semester_name) {
        $semester_term = $this->encode_semester($semester_year, $semester_name);

        $params = array($course_nbr, $section_nbr, $course_dept, '01', $semester_term);

        $xml_teachers = $this->invoke($params);

        $teacher_mapper = function ($xml_teacher) {
            list($lastname, $first) = $this->parse_name($xml_teacher->INDIV_NAME);

            $primary_flag = trim($xml_teacher->PRIMARY_INSTRUCTOR);

            $teacher = new stdClass;

            $teacher->username = $xml_teacher->PRIMARY_ACCESS_ID;
            $teacher->idnumber = $xml_teacher->LSU_ID;
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

    function students($course_nbr, $course_dept, $section_nbr, $semester_year, $semester_name) {
        $semester_term = $this->encode_semester($semester_year, $semester_name);

        $params = array('01', $semester_term, $course_dept, $course_nbr, $section_nbr);

        $xml_students = $this->invoke($params);

        $student_mapper = function ($xml_student) {
            list($lastname, $firstname) = $this->parse_name($xml_student->INDIV_NAME);

            $student = new stdClass;

            $student->username = $xml_student->PRIMARY_ACCESS_ID;
            $student->idnumber = $xml_student->LSU_ID;

            $student->credit_hours = $xml_student->CREDIT_HRS;
            $student->ferpa = $xml_student->WITHHOLD_DIR_FLG;

            return $student;
        };

        return empty($xml_students->ROW) ?
            array() :
            array_map($student_mapper, $xml_students->ROW);
    }
}

class lsu_student_data extends lsu_source {
    var $serviceId = 'MOODLE_STUDENTS_2';

    function student_data($semester_year, $semester_name) {
        $student_data = array();
        foreach (array('1590', '1595') as $instituition) {
            $xml_data = $this->invoke(array($semester_term, $instituition));

            foreach ($xml_data->ROW as $xml_student_data) {
                $stud_data = new stdClass;

                $reg = trim($xml_student_data->REGISTRATION_DATE);

                $stud_data->year = $xml_student_data->YEAR_CLASS;
                $stud_data->college = $xml_student_data->COLLEGE_CODE;
                $stud_data->major = $xml_student_data->CURRIC_CODE;
                $stud_data->reg_status = empty($reg) ? NULL : $this->parse_date($reg);
                $stud_data->keypadid = $xml_student_data->KEYPADID;
                $stud_data->idnumber = $xml_student_data->LSU_ID;

                $student_data[] = $stud_data;
            }
        }

        return $student_data;
    }
}
