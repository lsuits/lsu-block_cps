<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

abstract class crosslist_form extends cps_form {
    public static function next_from($next, $data, $courses) {
        return parent::next_from('crosslist', $next, $data, $courses);
    }

    public static function create($courses, $state = null, $extra = null) {
        return parent::create('crosslist', $courses, $state, $extra);
    }
}

class crosslist_form_select extends crosslist_form {
    var $current = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return array('courses' => $courses);
    }

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['courses'];

        $semesters = array();

        $m->addElement('header', 'select_course', self::_s('crosslist_select'));

        $m->addElement('static', 'selected_label', '', '');
        foreach ($courses as $course) {
            foreach ($course->sections as $section) {
                $id = $section->semesterid;
                if (isset($semesters[$id])) {
                    continue;
                }

                $semesters[$id] = $section->semester();
            }

            $semester = $semesters[reset($course->sections)->semesterid];

            $display = "$semester->year $semester->name $course->department $course->cou_number";

            $m->addElement('checkbox', 'selected_' . $course->id, '', $display);
        }

        $this->generate_buttons();

        // Used later in validation
        $this->semesters = $semesters;
    }

    function validation($data) {
        $courses = $this->_customdata['courses'];

        $semesters = $this->semesters;

        $errors = array();

        // Must select two...
        // Must select from same semester
        $selected = 0;
        $selected_semester = null;
        foreach ($data as $key => $value) {
            $is_a_match = preg_match('/^selected_(\d+)/', $key, $matches);

            if ($is_a_match) {
                $selected ++;

                $courseid = $matches[1];

                $current_semester = reset($courses[$courseid]->sections)->semesterid;

                if (empty($selected_semester)) {
                    $selected_semester = $current_semester;
                }

                if ($selected_semester != $current_semester) {
                    $errors[$key] = self::_s('err_same_semester', $semesters[$selected_semester]);
                }
            }
        }

        if ($selected < 2) {
            $errors['selected_label'] = self::_s('err_not_enough');
        }

        return $errors;
    }
}

class crosslist_form_shells extends crosslist_form {
    var $current = self::SHELLS;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($courses) {

        $selected_courses = array();
        foreach ($courses as $course) {
            $selected = optional_param('selected_' . $course->id, null, PARAM_INT);

            if ($selected) {
                $selected_courses['selected_' . $course->id] = $course;
            }
        }

        return array('selected_courses' => $selected_courses);
    }

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['selected_courses'];

        $semester = reset(current($courses)->sections)->semester();

        $m->addElement('header', 'selected_courses', self::_s('crosslist_you_have'));

        $total = $last = 0;

        foreach ($courses as $selected => $course) {
            $display = "$semester->year $semester->name $course->department $course->cou_number";

            $m->addElement('static', 'course_' . $course->id, $display);

            $m->addElement('hidden', $selected, 1);

            $last = count($course->sections);
            $total += $last;
        }

        $number = min($total / count($courses), $total - $last);

        $range = range(1, $number);
        $options = array_combine($range, $range);

        $m->addElement('select', 'shells', self::_s('split_how_many'), $options);

        $this->generate_states_and_buttons();
    }
}
