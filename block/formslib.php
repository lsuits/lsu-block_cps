<?php

require_once $CFG->libdir . '/formslib.php';

interface generic_states {
    const SELECT = 'select';
    const SHELLS = 'shells';
    const DECIDE = 'decide';
    const CONFIRM = 'confirm';
    const FINISHED = 'finish';
    const UPDATE = 'update';
}

interface finalized_form {
    function process($data, $courses);

    function display();
}

abstract class cps_form extends moodleform implements generic_states {
    var $current;
    var $next;
    var $prev;

    public static function _s($key, $a = null) {
        return get_string($key, 'block_cps', $a);
    }

    public static function first() {
        return optional_param('current', self::SELECT, PARAM_ALPHA);
    }

    public static function next_from($prefix, $next, $data, $courses) {
        $form = self::create($prefix, $courses, $next, $data);

        self::navs($prefix, $form->current);

        $data->current = $form->current;
        $data->prev = $form->prev;
        $data->next = $form->next;

        $form->set_data($data);

        return $form;
    }

    public static function create($prefix, $courses, $state = null, $extra= null) {
        $state = $state ? $state : self::first();

        $class = $prefix . '_form_' . $state;

        $data = $class::build($courses);

        if ($extra) {
            $data += get_object_vars($extra);
        }

        return new $class(null, $data);
    }

    public static function navs($prefix, $state) {
        global $PAGE;
        $PAGE->navbar->add(self::_s($prefix . '_' . $state));
    }

    protected function generate_states() {
        $m =& $this->_form;

        $m->addElement('hidden', 'current', $this->current);

        if (!empty($this->next)) {
            $m->addElement('hidden', 'next', $this->next);
        }

        if (!empty($this->prev)) {
            $m->addElement('hidden', 'prev', $this->prev);
        }
    }

    protected function generate_buttons() {
        $m =& $this->_form;

        $buttons = array();

        if (!empty($this->prev)) {
            $buttons[] = $m->createElement('submit', 'back', self::_s('back'));
        }

        $buttons[] = $m->createElement('cancel');

        if (!empty($this->next)) {
            $buttons[] = $m->createElement('submit', 'save', self::_s('next'));
        }

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }

    protected function generate_states_and_buttons() {
        $this->generate_states();

        $this->generate_buttons();
    }
}
