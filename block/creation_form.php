<?php

require_once $CFG->libdir . '/formslib.php';

class creation_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $sections = $this->_customdata['sections'];

        $semesters = cps_semester::get_all();

        $courses = array();
        $course_semesters = array();
        foreach ($sections as $section) {
            $semesterid = $section->semesterid;
            if (!isset($course_semesters[$semesterid])) {
                $course_semesters[$semesterid] = array();
            }

            $courseid = $section->courseid;
            if (!isset($courses[$courseid])) {
                $courses[$courseid] = $section->course();
            }

            $course_semesters[$semesterid][$courseid] = $courses[$courseid];
        }

        unset ($courses, $sections);

        $_s = cps::gen_str('block_cps');

        $bold = function ($text) { return '<strong>'.$text.'</strong>'; };

        $spacer = function ($how_many) {
            return array(implode('', array_map(function($i) { return '&nbsp;'; },
                range(1, $how_many))));
        };

        $default_create_days = get_config('block_cps', 'create_days');
        $default_enroll_days = get_config('block_cps', 'enroll_days');

        $m->addElement('header', 'defaults', $_s('default_settings'));

        $m->addElement('static', 'def_create', $_s('default_create_days'),
            $default_create_days);

        $m->addElement('static', 'def_enroll', $_s('default_enroll_days'),
            $default_enroll_days);

        foreach ($course_semesters as $semesterid => $courses) {
            $semester = $semesters[$semesterid];
            $name = "{$semester->year} {$semester->name}";

            $m->addElement('header', 'semester_' . $semesterid, $name);

            $label = array(
                $m->createElement('static', 'label', '', $bold($_s('create_days'))),
                $m->createElement('static', 'label', '', $bold($_s('enroll_days')))
            );

            $m->addGroup($label, 'labels', '&nbsp;', $spacer(15));

            foreach ($courses as $courseid => $course) {
                $id = "{$semesterid}_{$courseid}";

                $group = array(
                    $m->createElement('text', 'create_days_'.$id, ''),
                    $m->createElement('text', 'enroll_days_'.$id, '')
                );

                $m->addGroup($group, 'create_group_'.$id, $course, array(' '));
            }
        }

        $buttons = array(
            $m->createElement('submit', 'save', get_string('savechanges')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '', $spacer(1), false);
        $m->closeHeaderBefore('buttons');
    }
}
