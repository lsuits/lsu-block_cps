<?php

require_once $CFG->libdir . '/formslib.php';

class setting_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $user = $this->_customdata['user'];

        $_s = ues::gen_str('block_cps');

        $m->addElement('text', 'user_firstname', $_s('user_firstname'));
        $m->setDefault('user_firstname', $user->firstname);
        $m->setType('user_firstname', PARAM_TEXT);

        $m->addElement('checkbox', 'user_grade_restore', $_s('grade_restore'));
        $m->setDefault('user_grade_restore', 1);
        $m->addHelpButton('user_grade_restore', 'grade_restore', 'block_cps');

        $m->addElement('hidden', 'id', $user->id);
        $m->setType('id', PARAM_INT);

        $buttons = array(
            $m->createElement('submit', 'save', get_string('savechanges')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }
}

class setting_search_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $m->addElement('text', 'username', get_string('username'));
        $m->setType('username',PARAM_ALPHANUMEXT);
        
        $m->addElement('text', 'idnumber', get_string('idnumber'));
        $m->setType('idnumber',PARAM_ALPHANUM);

        $buttons = array(
            $m->createElement('submit', 'search', get_string('search')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }
}
