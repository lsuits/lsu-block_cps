<?php

require_once $CFG->libdir . '/formslib.php';

class creation_form extends moodleform {
    function definition() {
        $m =& $this->_form;

        $sections = $this->_customdata['sections'];

        $semesters = ues_semester::get_all();

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

        $_s = ues::gen_str('block_cps');

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

        $course_sorter = function($coursea, $courseb) {
            if ($coursea->department == $courseb->department) {
                return strcmp($coursea->cou_number, $courseb->cou_number);
            } else {
                return strcmp($coursea->department, $courseb->department);
            }
        };

        foreach ($course_semesters as $semesterid => $courses) {
            uasort($courses, $course_sorter);

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

                $m->addGroup($group, 'create_group_'.$id, $course, $spacer(1));
            }
        }

        $buttons = array(
            $m->createElement('submit', 'save', get_string('savechanges')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '', $spacer(1), false);
        $m->closeHeaderBefore('buttons');
    }

    function validation($data) {
        $create_days = array();
        $enroll_days = array();

        $errors = array();

        $fill = function (&$collection, $semesterid, $courseid, $value) {
            if (!isset($collection[$semesterid])) {
                $collection[$semesterid] = array();
            }

            if (trim($value) === '' or $value > 0) {
                $collection[$semesterid][$courseid] = $value;
                return true;
            } else {
                return false;
            }
        };

        $_s = ues::gen_str('block_cps');

        foreach ($data as $gname => $group) {
            if (preg_match('/^create_group_(\d+)_(\d+)/', $gname, $matches)) {
                $semesterid = $matches[1];
                $courseid = $matches[2];

                foreach ($group as $name => $value) {
                    if (preg_match('/^create_days/', $name)) {
                        $success = $fill($create_days, $semesterid,
                            $courseid, $value);
                    } else {
                        $success = $fill($enroll_days, $semesterid,
                            $courseid, $value);


                        if (isset($create_days[$semesterid][$courseid])) {
                            $valid =
                                ($create_days[$semesterid][$courseid] >= $value);
                        } else {
                            $valid = true;
                        }

                        if ($success and empty($valid)) {
                            $errors[$gname] = $_s('err_enrol_days');
                        }
                    }

                    if (!$success) {
                        $errors[$gname] = $_s('err_number');
                    }
                }
            }
        }

        $this->create_days = $create_days;
        $this->enroll_days = $enroll_days;

        return $errors;
    }
}
