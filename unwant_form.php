<?php

require_once $CFG->libdir . '/formslib.php';

class unwant_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $semesters = ues_semester::get_all();

        $sections = $this->_customdata['sections'];

        $courses = ues_course::merge_sections($sections);

        unset($sections);

        foreach ($courses as $courseid => $course) {

            $m->addElement('header', 'course_'.$courseid, $course);

            $actions = array('all', 'none');

            $map = function ($action) use ($courseid) {
                $url = new moodle_url('/blocks/cps/unwant.php', array(
                    'select' => $action,
                    'what' => $courseid
                ));

                $attrs = array('id' => $action . '_' . $courseid);

                return html_writer::link($url, get_string($action), $attrs);
            };

            $clean_links = implode(' / ', array_map($map, $actions));

            $m->addElement('static', 'all_none_'.$courseid, '', $clean_links);

            foreach ($course->sections as $section) {
                $semester = $semesters[$section->semesterid];
                $id = 'course'.$courseid.'_section'.$section->id;

                $name = $semester->year . ' ' . $semester->name . ' ' . $section;

                $m->addElement('checkbox', 'section_'.$section->id, '', $name,
                    array('id' => $id));
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
