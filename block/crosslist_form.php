<?php

require_once $CFG->libdir . '/formslib.php';

interface crosslist_consts {
    const SELECT = 'select';
    const SHELLS = 'shells';
    const DESCIDE = 'decide';
    const CONFIRM = 'confirm';
    const UPDATE = 'update';
    const FINISHED = 'finish';
}

abstract class crosslist_form extends moodleform implements crosslist_consts {
    var $state;
    var $next;
    var $prev;

    public static function _s($key, $a = null) {
        return get_string($key, 'block_cps', $a);
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
}

class crosslist_form_select extends crosslist_form {
    var $state = self::SELECT;
    var $next = self::SHELLS;

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['courses'];

        $semesters = array();

        $m->addElement('header', 'select_course', self::_s('crosslist_select'));

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

            $m->addElement('checkbox', 'selcted_' . $course->id, '', $display);
        }

        $this->generate_buttons();
    }
}
