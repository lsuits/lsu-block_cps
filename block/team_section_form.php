<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

abstract class team_section_form extends cps_form {
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

        $course = $this->_customdata['course'];

        $semester = $this->_customdata['semester'];

        $display = "$semester->year $semester->name $course->department $course->cou_number";

        $m->addElement('header', 'selected_course', $display);

        $m->addElement('hidden', 'id', $course->id);

        $this->generate_states_and_buttons();
    }
}
