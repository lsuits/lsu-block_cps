<?php

require_once $CFG->libdir . '/formslib.php';

abstract class split_form extends moodleform {
    var $state;
    var $next;
    var $prev;

    const SELECT = 'select';
    const SHELLS = 'shells';
    const DECIDE = 'decide';
    const CONFIRM = 'confirm';
    const FINISHED = 'finish';
    const UPDATE = 'update';

    public static function current() {
        return optional_param('current', self::SELECT, PARAM_ALPHA);
    }

    public static function next() {
        return optional_param('next', self::SELECT, PARAM_ALPHA);
    }

    public static function next_from($data, $courses) {
        $form = self::create($courses, $data->next);

        $data->current = $form->state;
        $data->prev = $form->prev;
        $data->next = $form->next;

        $form->set_data($data);

        return $form;
    }

    public static function create($courses, $state = null) {
        $state = $state ? $state : self::current();

        $class = 'split_form_' . $state;

        $data = $class::build($courses);

        return new $class(null, $data);
    }

    public function _s($key, $a = null) {
        return get_string($key, 'block_cps', $a);
    }

    protected function generate_states(&$m) {
        $m->addElement('hidden', 'current', $this->state);

        if (!empty($this->next)) {
            $m->addElement('hidden', 'next', $this->next);
        }

        if (!empty($this->prev)) {
            $m->addElement('hidden', 'prev', $this->prev);
        }
    }

    protected function generate_buttons(&$m) {
        $buttons = array();

        if (!empty($this->prev)) {
            $buttons[] = $m->createElement('submit', 'back', $this->_s('back'));
        }

        $buttons[] = $m->createElement('cancel');

        $buttons[] = $m->createElement('submit', 'save', $this->_s('next'));

        return $buttons;
    }

    protected function format_course($semester, $course) {
        $n = "$semester->year $semester->name $course->department $course->cou_number";

        return $n;
    }
}

class split_form_select extends split_form {
    var $state = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return array('courses' => $courses);
    }

    function definition() {
        $m =& $this->_form;

        $m->addElement('header', 'select', $this->_s('split_select'));

        $semesters = cps_semester::get_all();

        $courses = $this->_customdata['courses'];

        foreach ($courses as $course) {
            $semester = $semesters[current($course->sections)->semesterid];

            $display = ' ' . $this->format_course($semester, $course);

            $m->addElement('radio', 'selected', '', $display, $course->id);
        }

        $m->addRule('selected', $this->_s('err_select_one'), 'required', null, 'client');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }

    function valiadation($data) {
        $courses = $this->_customdata['courses'];

        $errors = array();
        if (empty($courses[$data['selected']])) {
            $errors['selected'] = $this->_s('err_select');
        }

        $course = $courses[$data['selected']];

        $section_count = count($course->sections);

        if ($section_count < 2) {
            $errors['selected'] = $this->_s('err_split_number');
        }

        return $errors;
    }
}

class split_form_shells extends split_form {
    var $state = self::SHELLS;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($courses) {
        $selected = required_param('selected', PARAM_INT);

        return array('course' => $courses[$selected]);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $semester = current($course->sections)->semester();

        $display = $this->format_course($semester, $course);

        $m->addElement('header', 'selected_header', $display);

        $seqed = range(1, count($course->sections) - 1);
        $options = array_combine($seqed, $seqed);

        $m->addElement('select', 'shells', $this->_s('split_how_many') ,$options);

        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }

    function valiadation($data) {
    }
}

class split_form_decide extends split_form {
    var $state = self::DECIDE;
    var $next = self::CONFIRM;
    var $prev = self::SHELLS;

    public static function build($courses) {
        $shells = required_param('shells', PARAM_INT);

        return split_form_shells::build($courses) + array('shells' => $shells);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $semester = current($course->sections)->semester();

        $display = $this->format_course($semester, $course);

        $m->addElement('header', 'selected_course', $display);

        // TODO: add html
        $m->addElement('html', '
            Fill this in
        ');

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}
