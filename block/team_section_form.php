<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

abstract class team_section_form extends cps_form {

    public function extract_data() {
        return array(
            $this->_customdata['course'],
            $this->_customdata['semester'],
            $this->_customdata['requests']
        );
    }

    public static function next_from($next, $data, $courses) {
        return parent::next_from('team_section', $next, $data, $courses);
    }

    public static function create($courses, $state = null, $extra = null) {
        return parent::create('team_section', $courses, $state, $extra);
    }
}

class team_section_form_select extends team_section_form {
    var $current = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return $courses;
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $m->addElement('header', 'selected_course',
            $this->display_course($course, $semester));

        $is_a_master = false;
        $not_a_master = false;

        $m->addElement('static', 'your_sections', self::_s('team_section'), '');

        foreach ($requests as $request) {
            if ($request->is_owner()) {
                $is_a_master = true;
                $courseid = $request->courseid;
                $other_course = $request->other_course();
            } else {
                $not_a_master = true;
                $courseid = $request->requested_course;
                $other_course = $request->course();
            }

            if ($course->id == $courseid) {
                foreach ($course->sections as $section) {
                    // TODO: check for section that are selected
                    $label = 'Section ' . $section->sec_number;
                    $m->addElement('static', 'section_'.$section->courseid, '', $label);
                }

                $m->addElement('static', 'other_course_'.$other_course->id,
                    $this->display_course($other_course, $semester), '');

                // TODO display the sections they've selected
            }
        }

        $m->addElement('static', 'breather', '', '');

        $m->addElement('static', 'continue_build', '', self::_s('team_continue_build'));

        $m->addElement('hidden', 'id', $course->id);

        $this->is_a_master = $is_a_master;
        $this->not_a_master = $not_a_master;

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        // Teacher can potentially be both...
        // But if the Teacher is NOT a master, then we can skip the shells
        if ($this->not_a_master and !$this->is_a_master) {
            // TODO: or update
            $this->next = self::DECIDE;
        }

        return true;
    }
}

class team_section_form_shells extends team_section_form {
    var $current = self::SHELLS;
    var $prev = self::SELECT;
    var $next = self::DECIDE;

    public static function build($courses) {
        return team_section_form_select::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $m->addElement('header', 'selected_course',
            $this->display_course($course, $semester));

        $section_count = count($course->sections);

        $other_courses = array();

        foreach ($requests as $request) {
            if (!$request->is_owner()) {
                continue;
            }

            $id = $request->requested_course;
            if (!isset($other_courses[$id])) {
                $other_courses[$id] = $request->other_course();
            }

            $teacher = $request->other_teacher();

            $taught_courses = cps_course::merge_sections($teacher->sections());

            $section_count += count($taught_courses[$id]->sections);
        }

        $shell_range = range(1, floor($section_count / (count($other_courses) + 1)));

        $options = array_combine($shell_range, $shell_range);

        $m->addElement('select', 'shells', self::_s('split_how_many'), $options);

        $m->addElement('hidden', 'id', $course->id);
        $this->generate_states_and_buttons();
    }
}

class team_section_form_decide extends team_section_form {
    var $current = self::DECIDE;
    var $next = self::CONFIRM;
    var $prev = self::SHELLS;

    public static function build($courses) {
        $shells = required_param('shells', PARAM_INT);

        return array('shells' => $shells) + team_section_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $m->addElement('header', 'selected_course', '');

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'id', '');

        $this->generate_states_and_buttons();
    }
}
