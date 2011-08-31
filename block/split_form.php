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
        $form = self::create($courses, $data->next, $data);

        $data->current = $form->state;
        $data->prev = $form->prev;
        $data->next = $form->next;

        $form->set_data($data);

        return $form;
    }

    public static function create($courses, $state = null, $extra= null) {
        $state = $state ? $state : self::current();

        $class = 'split_form_' . $state;

        $data = $class::build($courses);

        if ($extra) {
            $data += get_object_vars($extra);
        }

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

    function validation($data) {
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

        $semester = reset($course->sections)->semester();

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
        global $USER;

        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $semester = current($course->sections)->semester();

        $display = $this->format_course($semester, $course);

        $m->addElement('header', 'selected_course', $display);

        // TODO: filter split ones
        $before = array();

        foreach ($course->sections as $section) {
            $before[$section->id] = "Section $section->sec_number";
        }

        $previous_label =& $m->createElement('static', 'available_sections',
            '', $this->_s('available_sections'));

        $previous =& $m->createElement('select', 'before', '', $before);
        $previous->setMultiple(true);

        $previous_html =& $m->createElement('html', '
            <div class="split_available_sections">
                '.$previous_label->toHtml().'<br/>
                '.$previous->toHtml().'
            </div>
        ');

        $move_left =& $m->createElement('button', 'move_left', $this->_s('move_left'));
        $move_right =& $m->createElement('button', 'move_right', $this->_s('move_right'));

        $button_html =& $m->createElement('html', '
            <div class="split_movers">
                '.$move_left->toHtml().'<br/>
                '.$move_right->toHtml().'
            </div>
        ');

        $shells = array();

        foreach (range(1, $this->_customdata['shells']) as $groupingid) {
            // TODO: fill in split ones
            $shell_label =& $m->createElement('static', 'shell_' . $groupingid .
                '_label', '', $display . ' Course ' . $groupingid);
            $shell =& $m->createElement('select', 'shell_'.$groupingid, '', array());
            $shell->setMultiple(true);

            $link = html_writer::link('shell_'.$groupingid, $this->_s('customize_name'));

            $radio =& $m->createElement('radio', 'selected_shell', '', '');

            $for = ' for ' . fullname($USER);

            $shells[] = $shell_label->toHtml() . $for . ' (' . $link . ')<br/>' .
                $radio->toHtml() . $shell->toHtml();
        }

        $shell_html =& $m->createElement('html', '
            <div class="split_bucket_sections">
                '. implode('<br/>', $shells) . '
            </div>
        ');

        $shifters = array($previous_html, $button_html, $shell_html);

        $m->addGroup($shifters, 'shifters', '', array(' '), false);

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}

class split_form_confirm extends split_form {
    var $state = self::CONFIRM;
    var $next = self::FINISHED;
    var $prev = self::DECIDE;

    public static function build($courses) {
        return split_form_decide::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $semester = reset($course->sections)->semester();

        $display = $this->format_course($semester, $course);

        $m->addElement('header', 'selected_course', $display);

        $m->addElement('static', 'chosen', $this->_s('chosen'), '');

        // TODO map to bucket names
        $sections = array_map(function ($section) {
            return "<li>Seciton $section->sec_number</li>";
        }, $course->sections);

        $m->addElement('html', '
            <ul class="split_review_sections">
                '.implode('', $sections).'
            </ul>
        ');

        // TODO: build bucket values

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);
        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}
