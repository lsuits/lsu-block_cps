<?php

require_once $CFG->libdir . '/formslib.php';

class unwant_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $semesters = cps_semester::get_all();

        $sections = $this->_customdata['sections'];

        $courses = array();
        foreach ($sections as $section) {
            if (!isset($courses[$section->courseid])) {
                $courses[$section->courseid] = array();
            }

            $courses[$section->courseid][$section->id] = $section;
        }

        unset($sections);

        foreach ($courses as $courseid => $c_sections) {
            $course = cps_course::get(array('id' => $courseid));

            $m->addElement('header', 'course_'.$courseid, $course);

            $actions = array('all', 'none');

            $map = function ($action) use ($courseid) {
                $url = new moodle_url('/blocks/cps/unwant.php', array(
                    'select' => $action,
                    'what' => $courseid
                ));

                return html_writer::link($url, get_string($action));
            };

            $clean_links = implode(' / ', array_map($map, $actions));

            $m->addElement('static', 'all_none_'.$courseid, '', $clean_links);

            foreach ($c_sections as $section) {
                $semester = $semesters[$section->semesterid];
                $id = 'course'.$courseid.'_section'.$section->id;

                $name = $semester->year . ' ' . $semester->name . ' ' . $section;

                $m->addElement('checkbox', $section->id, '', $name, array(
                    'id' => $id
                ));
            }
        }
    }
}
