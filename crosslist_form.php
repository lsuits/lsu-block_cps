<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

abstract class crosslist_form extends cps_form {
    public static function next_from($next, $data, $courses) {
        return parent::next_from('crosslist', $next, $data, $courses);
    }

    public static function create($courses, $state = null, $extra = null) {
        return parent::create('crosslist', $courses, $state, $extra);
    }
}

class crosslist_form_select extends crosslist_form {
    var $current = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return array('courses' => $courses);
    }

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['courses'];

        $semesters = array();

        $m->addElement('header', 'select_course', self::_s('crosslist_select'));

        $m->addElement('static', 'selected_label', '', '');
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

            if (cps_crosslist::exists($course)) {
                $display .= ' (' . self::_s('crosslist_option_taken') . ')';
            }

            $m->addElement('checkbox', 'selected_' . $course->id, '', $display);
        }

        $this->generate_buttons();

        // Used later in validation
        $this->semesters = $semesters;
    }

    function validation($data) {
        $courses = $this->_customdata['courses'];

        $semesters = $this->semesters;

        $errors = array();

        // Must select two...
        // Must select from same semester
        $selected = 0;
        $selected_semester = null;
        $updating = false;
        foreach ($data as $key => $value) {
            $is_a_match = preg_match('/^selected_(\d+)/', $key, $matches);

            if ($is_a_match) {
                $selected ++;

                $course = $courses[$matches[1]];

                $updating = ($updating or cps_crosslist::exists($course));

                $current_semester = reset($course->sections)->semesterid;

                if (empty($selected_semester)) {
                    $selected_semester = $current_semester;
                }

                if ($selected_semester != $current_semester) {
                    $errors[$key] = self::_s('err_same_semester', $semesters[$selected_semester]);
                }
            }
        }

        if ($selected < 2) {
            $errors['selected_label'] = self::_s('err_not_enough');
        }

        if (empty($errors) and $updating) {
            $this->next = self::UPDATE;
        }

        return $errors;
    }
}

class crosslist_form_update extends crosslist_form implements updating_form {
    var $current = self::UPDATE;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($courses) {
        return self::prep_reshell() + crosslist_form_shells::build($courses);
    }

    public function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['selected_courses'];

        $semester = $this->_customdata['semester'];

        $current_crosslists = cps_crosslist::in_courses($courses);

        $shells = cps_crosslist::groups($current_crosslists);

        $cr_lookup = array();
        foreach ($current_crosslists as $crosslist) {
            $cr_lookup[$crosslist->sectionid] = $crosslist->id;
        }

        $grouping_lookup = array();

        $m->addElement('header', 'selected_courses', self::_s('crosslist_updating'));

        $orphaned_sections = 0;
        foreach ($courses as $key => $course) {

            $display = $this->display_course($course, $semester);

            $m->addElement('static', 'course_'.$course->id, $display, '');

            $html = '<ul>';
            foreach ($course->sections as $section) {
                $html .= '<li> Section ' . $section->sec_number . ' ';

                if (isset($cr_lookup[$section->id])) {
                    $crosslist = $current_crosslists[$cr_lookup[$section->id]];

                    $grouping_lookup[$crosslist->groupingid][$crosslist->shell_name][] = $section->id;

                    $html .= self::_s('crosslisted', $crosslist);
                } else {
                    $orphaned_sections ++;
                    $html .= self::_s('crosslist_no_option');
                }

                $html .= '</li>';
            }
            $html .= '</ul>';

            $m->addElement('static', 'sections_'.$course->id, '', $html);

            $m->addElement('hidden', $key, 1);
        }

        foreach ($grouping_lookup as $number => $info) {
            foreach ($info as $name => $secs) {
                $m->addElement('hidden', 'shell_name_'.$number.'_hidden', $name);
                $m->addElement('hidden', 'shell_values_'.$number, implode(',', $secs));
            }
        }

        $m->addElement('radio', 'crosslist_option', '', self::_s('split_undo'), self::UNDO);

        $orphaned = floor($orphaned_sections / 2) + $shells;

        if ($orphaned > 1) {
            $orphaned_range = range(1, $orphaned);
            $options = array_combine($orphaned_range, $orphaned_range);

            $m->addElement('radio', 'crosslist_option', '', self::_s('split_reshell'), self::RESHELL);
            $m->addElement('select', 'reshelled', self::_s('split_how_many'), $options);

            $m->disabledIf('reshelled', 'crosslist_option', 'neq', self::RESHELL);
        }

        $m->addElement('radio', 'crosslist_option', '', self::_s('split_rearrange'), self::REARRANGE);

        $m->setDefault('crosslist_option', self::REARRANGE);

        $m->addElement('hidden', 'shells', $shells);

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        $option = $data['crosslist_option'];

        $this->next = $option == self::UNDO ? self::LOADING : $this->next;

        return true;
    }
}

class crosslist_form_shells extends crosslist_form {
    var $current = self::SHELLS;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($courses) {

        $semester = null;

        $selected_courses = array();
        foreach ($courses as $course) {
            $selected = optional_param('selected_' . $course->id, null, PARAM_INT);

            if (!$semester) {
                $semester = reset($course->sections)->semester();
            }

            if ($selected) {
                $selected_courses['selected_' . $course->id] = $course;
            }
        }

        return array('selected_courses' => $selected_courses, 'semester' => $semester);
    }

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['selected_courses'];

        $semester = $this->_customdata['semester'];

        $m->addElement('header', 'selected_courses', self::_s('crosslist_you_have'));

        $total = $last = 0;

        foreach ($courses as $selected => $course) {
            $display = "$semester->year $semester->name $course->department $course->cou_number";

            $m->addElement('static', 'course_' . $course->id,
                $this->display_course($course, $semester));

            $m->addElement('hidden', $selected, 1);

            $last = count($course->sections);
            $total += $last;
        }


        $number = floor($total / 2);

        $range = range(1, $number);
        $options = array_combine($range, $range);

        $m->addElement('select', 'shells', self::_s('split_how_many'), $options);

        $this->generate_states_and_buttons();
    }
}

class crosslist_form_decide extends crosslist_form {
    var $current = self::DECIDE;
    var $next = self::CONFIRM;
    var $prev = self::SHELLS;

    public static function build($courses) {
        return self::conform_reshell() + crosslist_form_shells::build($courses);
    }

    function definition() {
        global $USER;

        $m =& $this->_form;

        $courses = $this->_customdata['selected_courses'];

        $this->prev = cps_crosslist::exists($courses) ? self::UPDATE : $this->prev;

        $semester = $this->_customdata['semester'];

        $to_coursenames = function($course) {
            return "$course->department $course->cou_number";
        };

        $course_names = implode (' / ', array_map($to_coursenames, $courses));

        $display = "$semester->year $semester->name $course_names";

        $m->addElement('header', 'selected_courses', $display);

        $before = array();
        foreach ($courses as $selected => $course) {
            foreach ($course->sections as $section) {
                $before[$section->id] = $to_coursenames($course) . " $section->sec_number";
            }

            $m->addElement('hidden', $selected, 1);
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
                $shell_name_value = $course_names;
                $shell_values = '';

                $shell_options = array();
            }

            $shell_label =& $m->createElement('static', 'shell_' . $groupingid .
                '_label', '', $semester->year . ' ' . $semester->name .
                ' <span id="shell_name_'.$groupingid.'">'
                . $shell_name_value . '</span>');
            $shell =& $m->createElement('select', 'shell_'.$groupingid, '', $shell_options);
            $shell->setMultiple(true);

            $shell_name_params = array('style' => 'display: none;');
            $shell_name =& $m->createElement('text', 'shell_name_' . $groupingid,
                '', $shell_name_params);
            $shell_name->setValue($shell_name_value);

            $link = html_writer::link('shell_'.$groupingid, $this->_s('customize_name'));

            $radio_params = array('id' => 'selected_shell_'.$groupingid);
            $radio =& $m->createElement('radio', 'selected_shell', '', '', $groupingid, $radio_params);

            $radio->setChecked($groupingid == 1);

            $for = ' for ' . fullname($USER);

            $shells[] = $shell_label->toHtml() . $for . ' (' . $link . ')<br/>' .
                $shell_name->toHtml() . '<br/>' . $radio->toHtml() . $shell->toHtml();

            $m->addElement('hidden', 'shell_values_'.$groupingid, $shell_values);
            $m->addElement('hidden', 'shell_name_'.$groupingid.'_hidden', $shell_name_value);
        }

        $previous_label =& $m->createElement('static', 'available_sections',
            '', $this->_s('available_sections'));

        $previous =& $m->createElement('select', 'before', '', $before);
        $previous->setMultiple(true);

        $form_html = $this->mover_form($previous_label, $previous, $shells);

        $m->addElement('html', $form_html);

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'reshelled', '');

        $this->generate_states_and_buttons();
    }

    function validation($data) {

        if (isset($data['back'])) {
            return true;
        }

        $shells = range(1, $data['shells']);

        $reduce_values = function ($in, $number) use ($data) {
            $values = explode(',', $data['shell_values_'.$number]);

            return $in and !empty($values) and count($values) >= 2;
        };

        $is_valid = array_reduce($shells, $reduce_values, true);

        $errors = array();
        if (!$is_valid) {
            $errors['shifters'] = self::_s('err_one_shell');
        }

        return $errors;
    }
}

class crosslist_form_confirm extends crosslist_form {
    var $current = self::CONFIRM;
    var $prev = self::DECIDE;
    var $next = self::LOADING;

    public static function build($courses) {
        $data = crosslist_form_decide::build($courses);

        $extra = array();
        foreach (range(1, $data['shells']) as $number) {
            $namekey = 'shell_name_'.$number.'_hidden';
            $valuekey = 'shell_values_'.$number;

            $extra[$namekey] = required_param($namekey, PARAM_TEXT);
            $extra[$valuekey] = required_param($valuekey, PARAM_RAW);
        }

        return $extra + $data;
    }

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['selected_courses'];

        $semester = $this->_customdata['semester'];

        $sections = array_reduce($courses, function ($in, $course) {
            return $in + $course->sections;
        }, array());

        $to_coursenames = function ($course) {
            return "$course->department $course->cou_number";
        };

        $course_names = implode(' / ', array_map($to_coursenames, $courses));

        $display = "$semester->year $semester->name";

        $m->addElement('header', 'selected_courses', "$display $course_names");

        $m->addElement('static', 'chosen', self::_s('chosen'), '');

        foreach (range(1, $this->_customdata['shells']) as $number) {
            $namekey = 'shell_name_'.$number.'_hidden';
            $valuekey = 'shell_values_'.$number;

            $name = $this->_customdata[$namekey];

            $values = $this->_customdata[$valuekey];

            if (empty($values)) {
                continue;
            }

            $html = '<ul class="split_review_sections">';
            foreach (explode(',', $values) as $sectionid) {
                $section = $sections[$sectionid];
                $key = 'selected_'.$section->courseid;

                $course_name = $to_coursenames($courses[$key]);

                $html .= '<li>' . $course_name . ' ' . $section->sec_number . '</li>';
            }
            $html .= '</ul>';

            $m->addElement('static', 'shell_label_'.$number, $name, $html);

            $m->addElement('hidden', $namekey, $name);
            $m->addElement('hidden', $valuekey, $values);
        }

        $m->addElement('hidden', 'shells', $this->_customdata['shells']);

        foreach ($courses as $key => $course) {
            $m->addElement('hidden', $key, $course->id);
        }

        $this->generate_states_and_buttons();
    }
}

class crosslist_form_finish implements finalized_form {
    function process($data, $courses) {
        $extra = crosslist_form_shells::build($courses);

        $current_crosslists = cps_crosslist::in_courses($extra['selected_courses']);

        if (isset($data->crosslist_option) and $data->crosslist_option == crosslist_form_update::UNDO) {
            $this->undo($current_crosslists);
        } else {
            $this->save_or_update($data, $current_crosslists);
        }
    }

    function undo($crosslists) {
        foreach ($crosslists as $crosslist) {
            $crosslist->delete($crosslist->id);
            $crosslist->unapply();
        }
    }

    function save_or_update($data, $current_crosslists) {
        global $USER;

        foreach (range(1, $data->shells) as $grouping) {
            $shell_name = $data->{'shell_name_'.$grouping.'_hidden'};

            $shell_values = $data->{'shell_values_'.$grouping};

            foreach (explode(',', $shell_values) as $sectionid) {
                $params = array(
                    'userid' => $USER->id,
                    'sectionid' => $sectionid
                );

                if (!$crosslist = cps_crosslist::get($params)) {
                    $crosslist = new cps_crosslist();
                    $crosslist->fill_params($params);
                }

                $crosslist->groupingid = $grouping;
                $crosslist->shell_name = $shell_name;
                $crosslist->save();

                $crosslist->apply();
                unset ($current_crosslists[$crosslist->id]);
            }
        }

        $this->undo($current_crosslists);
    }

    function display() {
        global $OUTPUT;

        $_s = ues::gen_str('block_cps');

        echo $OUTPUT->notification($_s('crosslist_thank_you'), 'notifysuccess');
        echo $OUTPUT->continue_button(new moodle_url('/blocks/cps/crosslist.php'));
    }
}
