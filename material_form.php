<?php

require_once $CFG->libdir . '/formslib.php';

class material_form extends moodleform {
    function definition() {
        global $USER;

        $m =& $this->_form;

        $sections = $this->_customdata['sections'];

        $courses = ues_course::merge_sections($sections);

        $_s = ues::gen_str('block_cps');

        $m->addElement('header', 'materials', $_s('creating_materials'));

        foreach ($courses as $course) {
            $material = cps_material::get(array(
                'userid' => $USER->id, 'courseid' => $course->id
            ));

            $checkbox =& $m->addElement('checkbox', 'material_'.$course->id,
                '', $course);

            if ($material) {
                $m->setDefault('material_'.$course->id, 1);
                $checkbox->freeze();
            }
        }

        $buttons = array(
            $m->createElement('submit', 'save', get_string('savechanges')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}
