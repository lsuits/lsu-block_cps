<?php

require_once $CFG->libdir . '/formslib.php';

abstract class split_form extends moodleform {
    var $state;
    var $next;
    var $prev;

    const SELECT = 'select';
    const SHELLS = 'shells';
    const DECIDE = 'decide';
    const CONFIRM = 'confirm';
    const FINISHED = 'finish';
    const UPDATE = 'update';

    public static function current() {
        return optional_param('current', self::SELECT, PARAM_ALPHA);
    }

    public static function next() {
        return optional_param('next', self::SELECT, PARAM_ALPHA);
    }

    public static function next_from($next, $data, $courses) {
        $form = self::create($courses, $next, $data);

        $data->current = $form->state;
        $data->prev = $form->prev;
        $data->next = $form->next;

        $form->set_data($data);

        return $form;
    }

    public static function create($courses, $state = null, $extra= null) {
        $state = $state ? $state : self::current();

        $class = 'split_form_' . $state;

        $data = $class::build($courses);

        if ($extra) {
            $data += get_object_vars($extra);
        }

        return new $class(null, $data);
    }

    public function _s($key, $a = null) {
        return get_string($key, 'block_cps', $a);
    }

    protected function generate_states(&$m) {
        $m->addElement('hidden', 'current', $this->state);

        if (!empty($this->next)) {
            $m->addElement('hidden', 'next', $this->next);
        }

        if (!empty($this->prev)) {
            $m->addElement('hidden', 'prev', $this->prev);
        }
    }

    protected function generate_buttons(&$m) {
        $buttons = array();

        if (!empty($this->prev)) {
            $buttons[] = $m->createElement('submit', 'back', $this->_s('back'));
        }

        $buttons[] = $m->createElement('cancel');

        $buttons[] = $m->createElement('submit', 'save', $this->_s('next'));

        return $buttons;
    }

    protected function format_course($semester, $course) {
        $n = "$semester->year $semester->name $course->department $course->cou_number";

        return $n;
    }
}

class split_form_select extends split_form {
    var $state = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return array('courses' => $courses);
    }

    function definition() {
        $m =& $this->_form;

        $m->addElement('header', 'select', $this->_s('split_select'));

        $semesters = cps_semester::get_all();

        $courses = $this->_customdata['courses'];

        foreach ($courses as $course) {

            $semester = $semesters[reset($course->sections)->semesterid];

            $display = ' ' . $this->format_course($semester, $course);

            if (cps_split::exists($course)) {
                $display .= ' (' . $this->_s('split_option_taken') . ')';
            }

            $m->addElement('radio', 'selected', '', $display, $course->id);
        }

        $m->addRule('selected', $this->_s('err_select_one'), 'required', null, 'client');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }

    function validation($data) {
        $courses = $this->_customdata['courses'];

        $errors = array();
        if (empty($courses[$data['selected']])) {
            $errors['selected'] = $this->_s('err_select');
        }

        $course = $courses[$data['selected']];

        $section_count = count($course->sections);

        if ($section_count < 2) {
            $errors['selected'] = $this->_s('err_split_number');
        }

        $this->next = cps_split::exists($course) ? self::UPDATE : $this->next;

        return $errors;
    }
}

class split_form_shells extends split_form {
    var $state = self::SHELLS;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    public static function build($courses) {
        $selected = required_param('selected', PARAM_INT);

        return array('course' => $courses[$selected]);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $semester = reset($course->sections)->semester();

        $display = $this->format_course($semester, $course);

        $m->addElement('header', 'selected_header', $display);

        $seqed = range(1, count($course->sections) - 1);
        $options = array_combine($seqed, $seqed);

        $m->addElement('select', 'shells', $this->_s('split_how_many') ,$options);

        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}

class split_form_update extends split_form {
    var $state = self::UPDATE;
    var $next = self::DECIDE;
    var $prev = self::SELECT;

    const UNDO = 0;
    const RESHELL = 1;
    const REARRANGE = 2;

    public static function build($courses) {
        return split_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $current_splits = cps_split::in_course($course);

        $sections = $course->sections;

        $shells = cps_split::groups($current_splits);

        $grouping_lookup = array();

        $m->addElement('hidden', 'shells', $shells);

        $m->addElement('header', 'selected_course', $this->_s('split_updating'));

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
                $m->addElement('hidden', 'shell_values_'.$number, implode(',', $secs));
            }
        }

        $m->addElement('html', $html);

        $m->addElement('radio', 'split_option', '', $this->_s('split_undo'), self::UNDO);

        if (!empty($sections)) {
            $orphaned = range(2, count($sections) + $shells);
            $options = array_combine($orphaned, $orphaned);

            $m->addElement('radio', 'split_option', '', $this->_s('split_reshell'), self::RESHELL);
            $m->addElement('select', 'reshelled', $this->_s('split_how_many'), $options);

            $m->disabledIf('reshelled', 'split_option', 'neq', self::RESHELL);
        }

        $m->addElement('radio', 'split_option', '', $this->_s('split_rearrange'), self::REARRANGE);

        $m->setDefault('split_option', self::REARRANGE);

        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }

    function validation($data) {
        $option = $data['split_option'];

        $this->next = $option == self::UNDO ? self::FINISHED : $this->next;

        return true;
    }
}

class split_form_decide extends split_form {
    var $state = self::DECIDE;
    var $next = self::CONFIRM;
    var $prev = self::SHELLS;

    public static function build($courses) {
        $shells = required_param('shells', PARAM_INT);

        return array('shells' => $shells) + split_form_shells::build($courses);
    }

    function definition() {
        global $USER;

        $m =& $this->_form;

        $course = $this->_customdata['course'];

        $this->prev = cps_split::exists($course) ? self::UPDATE : $this->prev;

        $sections = $course->sections;

        $semester = current($course->sections)->semester();

        $display = $this->format_course($semester, $course);

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

                $shell_sections = array();
            }

            $shell_label =& $m->createElement('static', 'shell_' . $groupingid .
                '_label', '', $display . ' <span id="shell_name_'.$groupingid.'">'
                . $shell_name_value . '</span>');
            $shell =& $m->createElement('select', 'shell_'.$groupingid, '', $shell_sections);
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

        $m->addElement('html', '<div id="split_error"></div>');

        $previous_html =& $m->createElement('html', '
            <div class="split_available_sections">
                '.$previous_label->toHtml().'<br/>
                '.$previous->toHtml().'
            </div>
        ');

        $move_left =& $m->createElement('button', 'move_left', $this->_s('move_left'));
        $move_right =& $m->createElement('button', 'move_right', $this->_s('move_right'));

        $button_html =& $m->createElement('html', '
            <div class="split_movers">
                '.$move_left->toHtml().'<br/>
                '.$move_right->toHtml().'
            </div>
        ');

        $shell_html =& $m->createElement('html', '
            <div class="split_bucket_sections">
                '. implode('<br/>', $shells) . '
            </div>
        ');

        $shifters = array($previous_html, $button_html, $shell_html);

        $m->addGroup($shifters, 'shifters', '', array(' '), true);

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}

class split_form_confirm extends split_form {
    var $state = self::CONFIRM;
    var $next = self::FINISHED;
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

        $display = $this->format_course($semester, $course);

        $m->addElement('header', 'selected_course', $display);

        $m->addElement('static', 'chosen', $this->_s('chosen'), '');

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

            $m->addElement('hidden', $namekey, '');
            $m->addElement('hidden', $valuekey, '');
        }

        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'selected', '');

        $this->generate_states($m);

        $buttons = $this->generate_buttons($m);
        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
        $m->closeHeaderBefore('buttons');
    }
}

class split_form_finish {

    function process($data, $valid_courses) {
        $course = $valid_courses[$data->selected];

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
                    'groupingid' => $grouping
                );

                if (!$split = cps_split::get($split_params)) {
                    $split = new cps_split();
                    $split->fill_params($split_params);
                }

                $split->shell_name = $shell_name;
                $split->save();

                unset ($current_splits[$split->id]);
            }
        }

        // Not sure that we'd ever get here... but for sanity sake's we'll
        // delete invalid splits
        $this->undo($current_splits);
    }

    function display() {
        global $OUTPUT;

        $_s = cps::gen_str('block_cps');

        $heading = $_s('split_processed');

        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);

        echo $OUTPUT->box_start();

        echo $OUTPUT->notification($_s('split_thank_you'), 'notifysuccess');
        echo $OUTPUT->continue_button(new moodle_url('/blocks/cps/split.php'));

        echo $OUTPUT->box_end();

        echo $OUTPUT->footer();
    }
}
