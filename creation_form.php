<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 *
 * @package    block_cps
 * @copyright  2014 Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once $CFG->libdir . '/formslib.php';
require_once $CFG->libdir . '/completionlib.php';

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

        $m->addElement('header', 'create_header', $_s('creation_settings'));

        $m->addElement('checkbox', 'creation_defaults', $_s('use_defaults'));

        $options = array();
        $formats = get_plugin_list('format');
        foreach ($formats as $format => $ignore) {
            $options[$format] = get_string('pluginname', "format_$format");
        }

        $default_format = get_config('moodlecourse', 'format');

        $str = get_string('format');
        $m->addElement('select', "creation_format", $str, $options);
        $m->setDefault('creation_format', $default_format);
        $m->disabledIf('creation_format', 'creation_defaults', 'checked');

        $maxsections = get_config('moodlecourse', 'maxsections');
        $default_number = get_config('moodlecourse', 'numsections');
        $options = array_combine(range(1, $maxsections), range(1, $maxsections));
        $str = get_string('numberweeks');
        $m->addElement('select', 'creation_numsections', $str, $options);
        $m->setDefault('creation_numsections', $default_number);
        $m->disabledIf('creation_numsections', 'creation_defaults', 'checked');

        $default_visibility = get_config('moodlecourse', 'visible');
        $options = array(
            '0' => get_string('courseavailablenot'),
            '1' => get_string('courseavailable')
        );
        $str = get_string('availability');
        $m->addElement('select', 'creation_visible', $str, $options);
        $m->setDefault('creation_visible', $default_visibility);
        $m->disabledIf('creation_visible', 'creation_defaults', 'checked');

        if (completion_info::is_enabled_for_site()) {
            $options = array(
                0 => get_string('completiondisabled', 'completion'),
                1 => get_string('completionenabled', 'completion')
            );

            $m->addElement('static', '',
                '<strong>' . get_string('progress', 'completion') . '</strong>', '');
            $m->addElement('select', 'creation_enablecompletion', get_string('completion', 'completion'), $options);
            $m->setDefault('creation_enablecompletion', get_config('moodlecourse', 'enablecompletion'));
            $m->disabledIf('creation_enablecompletion', 'creation_defaults', 'checked');


            /* Removed from Moodle
                $m->addElement('checkbox', 'creation_completionstartonenrol', get_string('completionstartonenrol', 'completion'));
                $m->setDefault('creation_completionstartonenrol', get_config('moodlecourse', 'completionstartonenrol'));

                $m->disabledIf('creation_completionstartonenrol', 'creation_enablecompletion', 'eq', 0);
                $m->disabledIf('creation_completionstartonenrol', 'creation_defaults', 'checked');
            */

        }

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
            
            $create_default = get_config('block_cps', 'create_days');
            $enroll_default = get_config('block_cps', 'enroll_days');

            foreach ($courses as $courseid => $course) {
                $id = "{$semesterid}_{$courseid}";

                $group = array(
                    $m->createElement('text', 'create_days_'.$id, null),
                    $m->createElement('text', 'enroll_days_'.$id, null)
                );

                $m->addGroup($group, 'create_group_'.$id, $course, $spacer(1));
                
                $m->setType("create_group_{$id}[create_days_{$id}]", PARAM_INT);
                $m->setDefault("create_group_{$id}[create_days_{$id}]", $create_default);

                $m->setType("create_group_{$id}[enroll_days_{$id}]", PARAM_INT);
                $m->setDefault("create_group_{$id}[enroll_days_{$id}]", $enroll_default);
            }
        }

        $buttons = array(
            $m->createElement('submit', 'save', get_string('savechanges')),
            $m->createElement('cancel')
        );

        $m->addGroup($buttons, 'buttons', '', $spacer(1), false);
        $m->closeHeaderBefore('buttons');
    }

    public function validation($data, $files) {
        $create_days = $enroll_days = $settings = $errors = array();

        $fill = function (&$collection, $semesterid, $courseid, $value) {
            if (!isset($collection[$semesterid])) {
                $collection[$semesterid] = array();
            }

            $numeric   = is_numeric($value) && (int) $value > 0;
            $empty_str = trim($value) === '';

            if ($numeric || $empty_str){
                $value = $numeric ? (int) $value : $value;
                $collection[$semesterid][$courseid] = $value;
                return true;
            } else {
                return false;
            }
        };

        $_s = ues::gen_str('block_cps');

        // iterate over the form values for creation and enrollment data.
        foreach ($data as $gname => $group) {
            if ($gname === 'creation_defaults') {
                continue;
            }

            if (preg_match('/^creation_/', $gname)) {
                $settings[$gname] = $group;
                continue;
            }

            if (preg_match('/^create_group_(\d+)_(\d+)/', $gname, $matches)) {
                $semesterid = $matches[1];
                $courseid = $matches[2];

                foreach ($group as $name => $value) {
                    if (preg_match('/^create_days/', $name)) {
                        $filled = $fill($create_days, $semesterid,
                            $courseid, $value);
                        if(!$filled){
                            if(!is_numeric($value)){
                                $errors[$gname] = $_s('err_numeric');                                
                            }else{
                                $errors[$gname] = $_s('err_number');
                            }
                            break;
                        } 

                    } else {
                        $filled = $fill($enroll_days, $semesterid, $courseid, $value);

                        $valid = true;
                        if(!$filled){
                            if(!is_numeric($value)){
                                $errors[$gname] = $_s('err_numeric');                                
                            }else{
                                $errors[$gname] = $_s('err_number');
                            }
                            break;
                        }else{
                        
                            if($create_days[$semesterid][$courseid] == '' &&
                                    $enroll_days[$semesterid][$courseid] == '') {
                                $both_empty = true;
                            }else{
                                $both_empty = false;
                                if(is_numeric($create_days[$semesterid][$courseid]) &&
                                        is_numeric($enroll_days[$semesterid][$courseid])){
                                    $both_numeric = true;
                                }else{
                                    $both_numeric = false; 
                                }

                                if($filled && !$both_empty && !$both_numeric){
                                    $errors[$gname] = $_s('err_both_empty');
                                }else{
                                    $valid = 
                                        ($create_days[$semesterid][$courseid] >= $value);

                                    if ($filled and !$valid) {
                                        $errors[$gname] = $_s('err_enrol_days');
                                    }
                                }

                            }
                        }
                    }
                }
            }
        }

        $this->create_days = $create_days;
        $this->enroll_days = $enroll_days;
        $this->settings = $settings;

        return $errors;
    }
}
