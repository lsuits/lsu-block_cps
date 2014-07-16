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
require_once $CFG->dirroot . '/blocks/cps/formslib.php';

abstract class split_form extends cps_form {

    protected function forge_split_all($course) {
        $grouping = 1;
        foreach ($course->sections as $section) {
            $_POST['shell_name_'.$grouping.'_hidden'] = $section->sec_number;
            $_POST['shell_values_'.$grouping] = $section->id;
            $grouping ++;
        }

        $_POST['shells'] = count($course->sections);
    }
}

class split_form_select extends split_form {
    var $current = self::SELECT;
    var $next = self::SHELLS;

    public static function build($semesters) {
        return array('semesters' => $semesters);
    }

    function definition() {
        $m =& $this->_form;

        $m->addElement('header', 'select', self::_s('select'));

        $semesters = $this->_customdata['semesters'];

        foreach ($semesters as $semester) {

            foreach ($semester->courses as $course) {
                $display = ' ' . $this->display_course($course, $semester);

                if (cps_split::exists($course)) {
                    $display .= ' (' . self::_s('split_option_taken') . ')';
                }

                $key = $semester->id . '_' . $course->id;
                $m->addElement('radio', 'selected', '', $display, $key);
            }
        }

        $m->addRule('selected', self::_s('err_select_one'), 'required', null, 'client');

        $this->generate_states_and_buttons();
    }

    function validation($data, $files) {
        $semesters = $this->_customdata['semesters'];

        if (empty($data['selected'])) {
            return array('selected' => self::_s('err_select_one'));
        }

        list($semid, $couid) = explode('_', $data['selected']);
        if (empty($semesters[$semid]->courses[$couid])) {
            return array('selected' => self::_s('err_select'));
        }

        $errors = array();

        $course = $semesters[$semid]->courses[$couid];

        $section_count = count($course->sections);

        if ($section_count < 2) {
            $errors['selected'] = self::_s('err_split_number');
        }

        if ($section_count == 2) {
            $this->next = self::CONFIRM;

            $this->forge_split_all($course);
        }

        $this->next = cps_split::exists($course) ? self::UPDATE : $this->next;

        return $errors;
    }
}

class split_form_shells extends split_form {
    var $current = self::SHELLS;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($semesters) {
        $selected = required_param('selected', PARAM_RAW);

        list($semid, $couid) = explode('_', $selected);

        return array('course' => $semesters[$semid]->courses[$couid]);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $semester = reset($course->sections)->semester();

        $display = $this->display_course($course, $semester);

        $m->addElement('header', 'selected_header', $display);

        $seqed = range(2, count($course->sections));
        $options = array_combine($seqed, $seqed);

        $m->addElement('select', 'shells', self::_s('split_how_many') ,$options);
        $m->addHelpButton('shells', 'split_how_many', 'block_cps');

        $m->addElement('hidden', 'selected', '');

        $this->generate_states_and_buttons();
    }

    function validation($data, $files) {
        $course = $this->_customdata['course'];

        if ($data['shells'] == count($course->sections)) {
            $this->next = self::CONFIRM;

            $this->forge_split_all($course);
        }

        return true;
    }
}

class split_form_update extends split_form implements updating_form {
    var $current = self::UPDATE;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($courses) {
        return self::prep_reshell() + split_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $current_splits = cps_split::in_course($course);

        $sections = $course->sections;

        $shells = cps_split::groups($current_splits);

        $grouping_lookup = array();

        $m->addElement('hidden', 'shells', $shells);
        $m->setType('shells', PARAM_INT);

        $m->addElement('header', 'selected_course', self::_s('split_updating'));

        $html = '<div class="previous_splits">
            <ul>';
        foreach ($current_splits as $split) {
            $section = $course->sections[$split->sectionid];

            $display = "$course->department $course->cou_number Setion $section->sec_number";
            $html .= "<li>$display is split into course $split->shell_name</li>";

            unset ($sections[$section->id]);

            $grouping_lookup[$split->groupingid][$split->shell_name][] = $split->sectionid;
        }
        $html .= '</ul>
            </div>';

        foreach ($grouping_lookup as $number => $info) {
            foreach ($info as $name => $secs) {
                $m->addElement('hidden', 'shell_name_'.$number.'_hidden', $name);
                $m->setType('shell_name_'.$number.'_hidden', PARAM_ALPHANUMEXT);

                $m->addElement('hidden', 'shell_values_'.$number, implode(',', $secs));
                $m->setType('shell_values_'.$number, PARAM_ALPHANUMEXT);
            }
        }

        $m->addElement('html', $html);

        $m->addElement('radio', 'split_option', '', self::_s('split_undo'), self::UNDO);

        if (!empty($sections) or count($course->sections) > 2) {
            $orphaned = range(2, count($course->sections));
            $options = array_combine($orphaned, $orphaned);

            $m->addElement('radio', 'split_option', '', self::_s('split_reshell'), self::RESHELL);
            $m->addElement('select', 'reshelled', self::_s('split_how_many'), $options);

            $m->addHelpButton('reshelled', 'split_how_many', 'block_cps');

            $m->disabledIf('reshelled', 'split_option', 'neq', self::RESHELL);
        }

        $m->addElement('radio', 'split_option', '', self::_s('split_rearrange'), self::REARRANGE);

        $m->setDefault('split_option', self::REARRANGE);

        $m->addElement('hidden', 'selected', '');
        $m->setType('selected', PARAM_ALPHANUMEXT);

        $this->generate_states_and_buttons();
    }

    function validation($data, $files) {
        $option = $data['split_option'];

        $course = $this->_customdata['course'];

        if ($option == self::UNDO) {
            $this->next = self::LOADING;
        } else if ($option == self::REARRANGE and count($course->sections) == 2) {
            $this->next = self::CONFIRM;
        } else {
            $this->next;
        }

        return true;
    }
}

class split_form_decide extends split_form {
    var $current = self::DECIDE;
    var $next = self::CONFIRM;
    var $prev = self::SHELLS;

    public static function build($courses) {
        return self::conform_reshell() + split_form_shells::build($courses);
    }

    function definition() {
        global $USER;

        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $this->prev = cps_split::exists($course) ? self::UPDATE : $this->prev;

        $sections = $course->sections;

        $semester = current($course->sections)->semester();

        $display = $this->display_course($course, $semester);

        $m->addElement('header', 'selected_course', $display);

        $before = array();

        foreach ($sections as $section) {
            $before[$section->id] = "Section $section->sec_number";
        }

        $shells = array();

        foreach (range(1, $this->_customdata['shells']) as $groupingid) {
            $updating = !empty($this->_customdata['shell_values_'.$groupingid]);

            if ($updating) {
                $shell_name_value = $this->_customdata['shell_name_'.$groupingid.'_hidden'];
                $shell_values = $this->_customdata['shell_values_'.$groupingid];

                $shell_ids = explode(',', $shell_values);
                $shell_sections = array_map(function($sec) use ( &$before) {
                    $section = $before[$sec];
                    unset($before[$sec]);
                    return $section;
                }, $shell_ids);

                $shell_options = array_combine($shell_ids, $shell_sections);
            } else {
                $shell_name_value = 'Course ' . $groupingid;
                $shell_values = '';

                $shell_options = array();
            }

            $shell_label =& $m->createElement('static', 'shell_' . $groupingid .
                '_label', '', $display . ' <span id="shell_name_'.$groupingid.'">'
                . $shell_name_value . '</span>');
            $shell =& $m->createElement('select', 'shell_'.$groupingid, '', $shell_options);
            $shell->setMultiple(true);

            $shell_name_params = array('style' => 'display: none;');
            $shell_name =& $m->createElement('text', 'shell_name_' . $groupingid,
                '', $shell_name_params);
            $shell_name->setValue($shell_name_value);

            $link = html_writer::link('shell_'.$groupingid, self::_s('customize_name'));

            $radio_params = array('id' => 'selected_shell_'.$groupingid);
            $radio =& $m->createElement('radio', 'selected_shell', '', '', $groupingid, $radio_params);

            $radio->setChecked($groupingid == 1);

            $for = ' for ' . fullname($USER);

            $shells[] = $shell_label->toHtml() . $for . ' (' . $link . ')<br/>' .
                $shell_name->toHtml() . '<br/>' . $radio->toHtml() . $shell->toHtml();

            $m->addElement('hidden', 'shell_values_'.$groupingid, $shell_values);
            $m->setType('shell_values_'.$groupingid, PARAM_ALPHANUMEXT);
            $m->addElement('hidden', 'shell_name_'.$groupingid.'_hidden', $shell_name_value);
            $m->setType('shell_name_'.$groupingid.'_hidden', PARAM_ALPHANUMEXT);
        }

        $previous_label =& $m->createElement('static', 'available_sections',
            '', self::_s('available_sections'));

        $previous =& $m->createElement('select', 'before', '', $before);
        $previous->setMultiple(true);

        $form_html = $this->mover_form($previous_label, $previous, $shells);

        $m->addElement('html', $form_html);

        $m->addElement('hidden', 'shells', '');
        $m->setType('shells', PARAM_INT);

        $m->addElement('hidden', 'reshelled', '');

        $m->addElement('hidden', 'selected', '');
        $m->setType('selected', PARAM_ALPHANUMEXT);

        $this->generate_states_and_buttons();
    }
}

class split_form_confirm extends split_form {
    var $current = self::CONFIRM;
    var $next = self::LOADING;
    var $prev = self::DECIDE;

    public static function build($courses) {
        $data = split_form_decide::build($courses);

        $extra = array();
        foreach (range(1, $data['shells']) as $number) {
            $namekey = 'shell_name_'.$number.'_hidden';
            $valuekey = 'shell_values_'.$number;

            $extra[$namekey] = required_param($namekey, PARAM_TEXT);
            $extra[$valuekey] = required_param($valuekey, PARAM_RAW);
        }

        return $data + $extra;
    }

    function definition() {
        global $USER;
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $sections = $course->sections;

        $semester = reset($sections)->semester();

        $display = $this->display_course($course, $semester);

        $m->addElement('header', 'selected_course', $display);

        $m->addElement('static', 'chosen', self::_s('chosen'), '');

        foreach (range(1, $this->_customdata['shells']) as $number) {
            $namekey = 'shell_name_' . $number . '_hidden';
            $valuekey = 'shell_values_' . $number;

            $name = $this->_customdata[$namekey];

            $values = $this->_customdata[$valuekey];

            $html = '<ul class="split_review_sections">';
            foreach (explode(',', $values) as $sectionid) {
                $html .= '<li>Section ' . $sections[$sectionid]->sec_number . '</li>';
            }
            $html .= '</ul>';

            $m->addElement('static', 'shell_label_' . $number, $display . ' ' .$name, $html);
            $m->setType('reshelled', PARAM_INT);

            $m->addElement('hidden', $namekey, $this->_customdata[$namekey]);
            $m->setType($namekey, PARAM_ALPHANUM);

            $m->addElement('hidden', $valuekey, $this->_customdata[$valuekey]);
            $m->setType($valuekey, PARAM_ALPHANUM);
        }

        $m->addElement('hidden', 'shells', $this->_customdata['shells']);
        $m->setType('shells', PARAM_INT);

        $m->addElement('hidden', 'reshelled', '');

        $m->addElement('hidden', 'selected', '');
        $m->setType('selected', PARAM_ALPHANUMEXT);

        $this->generate_states_and_buttons();
    }

    function validation($data, $files) {
        $course = $this->_customdata['course'];

        $section_count = count($course->sections);

        if ($section_count == 2) {
            $this->prev = self::SELECT;
        } else if ($this->_customdata['shells'] == $section_count) {
            $this->prev = self::SHELLS;
        }

        return true;
    }
}

class split_form_finish implements finalized_form {

    function process($data, $semesters) {
        list($semid, $couid) = explode('_', $data->selected);
        $course = $semesters[$semid]->courses[$couid];

        $current_splits = cps_split::in_course($course);

        if (isset($data->split_option) and $data->split_option == split_form_update::UNDO) {
            $this->undo($current_splits);
        } else {
            $this->save_or_update($data, $current_splits);
        }
    }

    function undo($splits) {
        foreach ($splits as $split) {
            $split->delete($split->id);
            $split->unapply();
        }
    }

    function save_or_update($data, $current_splits) {
        global $USER;

        foreach (range(1, $data->shells) as $grouping) {
            $shell_name = $data->{'shell_name_'.$grouping.'_hidden'};

            $shell_values = $data->{'shell_values_'.$grouping};

            foreach (explode(',', $shell_values) as $sectionid) {
                $split_params = array(
                    'userid' => $USER->id,
                    'sectionid' => $sectionid,
                );

                if (!$split = cps_split::get($split_params)) {
                    $split = new cps_split();
                    $split->fill_params($split_params);
                }

                $split->groupingid = $grouping;
                $split->shell_name = $shell_name;
                $split->save();
                $split->apply();

                unset ($current_splits[$split->id]);
            }
        }

        // Not sure that we'd ever get here... but for sanity sake's we'll
        // delete invalid splits
        $this->undo($current_splits);
    }

    function display() {
        global $OUTPUT;

        $_s = ues::gen_str('block_cps');

        echo $OUTPUT->notification($_s('split_thank_you'), 'notifysuccess');
        echo $OUTPUT->continue_button(new moodle_url('/blocks/cps/split.php'));
    }
}
