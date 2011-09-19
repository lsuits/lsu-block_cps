<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

abstract class team_section_form extends cps_form {

    public function extract_data() {
        return array(
            $this->_customdata['course'],
            $this->_customdata['semester'],
            $this->_customdata['requests']
        );
    }

    public static function next_from($next, $data, $courses) {
        return parent::next_from('team_section', $next, $data, $courses);
    }

    public static function create($courses, $state = null, $extra = null) {
        return parent::create('team_section', $courses, $state, $extra);
    }
}

class team_section_form_select extends team_section_form {
    var $current = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return $courses;
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $m->addElement('header', 'selected_course',
            $this->display_course($course, $semester));

        $is_a_master = false;
        $not_a_master = false;

        $m->addElement('static', 'your_sections', self::_s('team_section'), '');

        foreach ($requests as $request) {
            if ($request->is_owner()) {
                $is_a_master = true;
                $courseid = $request->courseid;
                $other_course = $request->other_course();
            } else {
                $not_a_master = true;
                $courseid = $request->requested_course;
                $other_course = $request->course();
            }

            if ($course->id == $courseid) {
                foreach ($course->sections as $section) {
                    // TODO: check for section that are selected
                    $label = 'Section ' . $section->sec_number;
                    $m->addElement('static', 'section_'.$section->courseid, '', $label);
                }

                $m->addElement('static', 'other_course_'.$other_course->id,
                    $this->display_course($other_course, $semester), '');

                // TODO display the sections they've selected
            }
        }

        $m->addElement('static', 'breather', '', '');

        $m->addElement('static', 'continue_build', '', self::_s('team_continue_build'));

        $m->addElement('hidden', 'id', $course->id);

        $this->is_a_master = $is_a_master;
        $this->not_a_master = $not_a_master;

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        // Teacher can potentially be both...
        // But if the Teacher is NOT a master, then we can skip the shells
        if ($this->not_a_master and !$this->is_a_master) {
            // TODO: or update
            $this->next = self::DECIDE;
        }

        return true;
    }
}

class team_section_form_shells extends team_section_form {
    var $current = self::SHELLS;
    var $prev = self::SELECT;
    var $next = self::DECIDE;

    public static function build($courses) {
        return team_section_form_select::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $m->addElement('header', 'selected_course',
            $this->display_course($course, $semester));

        $section_count = count($course->sections);

        $other_courses = array();

        foreach ($requests as $request) {
            if (!$request->is_owner()) {
                continue;
            }

            $id = $request->requested_course;
            if (!isset($other_courses[$id])) {
                $other_courses[$id] = $request->other_course();
            }

            $teacher = $request->other_teacher();

            $taught_courses = cps_course::merge_sections($teacher->sections());

            $section_count += count($taught_courses[$id]->sections);
        }

        $shell_range = range(1, floor($section_count / (count($other_courses) + 1)));

        $options = array_combine($shell_range, $shell_range);

        $m->addElement('select', 'shells', self::_s('split_how_many'), $options);

        $m->addElement('hidden', 'id', $course->id);
        $this->generate_states_and_buttons();
    }
}

class team_section_form_decide extends team_section_form {
    var $current = self::DECIDE;
    var $next = self::CONFIRM;
    var $prev = self::SHELLS;

    public static function build($courses) {
        $shells = required_param('shells', PARAM_INT);

        return array('shells' => $shells) + team_section_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $display = "$semester->year $semester->name";

        $all_courses = array($course->id => $course);

        foreach ($requests as $request) {
            $other_course = $request->is_owner() ?
                $request->other_course() : $request->course();

            if (isset($all_courses[$other_course->id])) {
                continue;
            }

            $all_courses[$other_course->id] = $other_course;
        }

        $to_coursename = function ($course) {
            return "$course->department $course->cou_number";
        };

        $all_names = implode(' / ', array_map($to_coursename, $all_courses));

        $m->addElement('header', 'selected_course', "$display $all_names");

        // Instructor can only deal with sections they own
        $before = array();

        foreach ($course->sections as $section) {
            $before[$section->id] = "$course->department $course->cou_number $section->sec_number";
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
                $shell_name_value = 'Team ' . $groupingid . ' ' . $all_names;
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

            $shells[] = $shell_label->toHtml() . ' (' . $link . ')<br/>' .
                $shell_name->toHtml() . '<br/>' . $radio->toHtml() . $shell->toHtml();

            $m->addElement('hidden', 'shell_values_'.$groupingid, $shell_values);
            $m->addElement('hidden', 'shell_name_'.$groupingid.'_hidden', $shell_name_value);
        }

        $previous_label =& $m->createElement('static', 'available_sections',
            '', self::_s('available_sections'));

        $previous =& $m->createElement('select', 'before', '', $before);
        $previous->setMultiple(true);

        $m->addElement('html', '<div id="split_error"></div>');

        $previous_html =& $m->createElement('html', '
            <div class="split_available_sections">
                '.$previous_label->toHtml().'<br/>
                '.$previous->toHtml().'
            </div>
        ');

        $move_left =& $m->createElement('button', 'move_left', self::_s('move_left'));
        $move_right =& $m->createElement('button', 'move_right', self::_s('move_right'));

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
        $m->addElement('hidden', 'id', '');

        $this->generate_states_and_buttons();
    }
}

class team_section_form_confirm extends team_section_form {
    var $current = self::CONFIRM;
    var $prev = self::DECIDE;
    var $next = self::FINISHED;

    public static function build($courses) {
        $data = team_section_form_decide::build($courses);

        $extra = array();

        foreach (range(1, $data['shells']) as $number) {
            $value_key = 'shell_values_'.$number;
            $name_key = 'shell_name_'.$number.'_hidden';

            $extra += array(
                $name_key => required_param($name_key, PARAM_TEXT),
                $value_key => required_param($value_key, PARAM_RAW)
            );
        }

        return $extra + $data;
    }

    function definition() {
        $m =& $this->_form;

        list($course, $semester, $requests) = $this->extract_data();

        $display = "$semester->year $semester->name";

        $to_coursename = function ($course) {
            return "$course->department $course->cou_number";
        };

        $courses = array($course->id => $to_coursename($course));

        foreach ($requests as $request) {
            $other_course = $request->is_owner() ?
                $request->other_course() : $request->course();

            if (isset($courses[$other_course->id])) {
                continue;
            }

            $courses[$other_course->id] = $to_coursename($other_course);
        }

        $all_courses = implode(' / ', $courses);

        $all_users = array();

        $m->addElement('header', 'selected_course', "$display $all_courses");

        foreach (range(1, $this->_customdata['shells']) as $number) {
            $value_key = 'shell_values_'.$number;
            $name_key = 'shell_name_'.$number.'_hidden';

            $sectionids = $this->_customdata[$value_key];
            $shell_name = $this->_customdata[$name_key];

            $sections = cps_section::get_select('id IN ('.$sectionids.')');

            $m->addElement('static', 'shell_'.$number, $shell_name, '');

            foreach ($sections as $section) {
                $teacher = $section->primary();

                if (!isset($all_users[$teacher->userid])) {
                    $all_user[$teacher->userid] = $teacher->user();
                }

                $parts = array(
                    $courses[$section->courseid],
                    $section->sec_number,
                    'for',
                    fullname($all_user[$teacher->userid])
                );

                $label = implode(' ', $parts);

                $m->addElement('static', 'section_'.$section->id, '', $label);
            }

            $m->addElement('hidden', $value_key, '');
            $m->addElement('hidden', $name_key, '');
        }

        $m->addElement('hidden', 'id', '');
        $m->addElement('hidden', 'shells', '');

        $this->generate_states_and_buttons();
    }
}

class team_section_form_finish implements finalized_form {
    var $id;

    function process($data, $initial_data) {
        $this->id = $data->id;

        // TODO: get old team taught sections

        $this->save_or_update($data, $initial_data['requests'], array());
    }

    function undo($current_sections) {
        foreach ($current_sections as $section) {
            $section->delete($section->id);
        }
    }

    function save_or_update($data, $current_requests, $current_sections) {

        foreach (range(1, $data->shells) as $number) {
            $sectionids = $data->{'shell_values_'.$number};
            $shell_name = $data->{'shell_name_'.$number.'_hidden'};

            foreach (explode(',', $sectionids) as $sectionid) {
                $section = cps_section::get(array('id' => $sectionid));

                $requested = $section->primary();

                $associates = function ($req) use ($requested, $section) {
                    $comp = array(0 => $req->semesterid);

                    if ($req->is_owner()) {
                        $comp += array(1 => $req->userid, 2 => $req->courseid);
                    } else {
                        $comp += array(1 => $req->requested,
                            2 => $req->requested_course);
                    }

                    list($semid, $userid, $courseid) = $comp;

                    return $requested->userid == $userid and
                        $section->semesterid == $semid and
                        $section->courseid == $courseid;
                };

                $associate = current(array_filter($current_requests, $associates));

                $params = array(
                    'sectionid' => $sectionid,
                    'shell_name' => $shell_name,
                    'groupingid' => $number,
                    'requestid' => $associate->id
                );

                if (!$req_sec = cps_team_section::get($params)) {
                    $req_sec = new cps_team_section();
                    $req_sec->fill_params($params);
                }

                $req_sec->save();

                unset($current_sections[$req_sec->id]);
            }
        }

        $this->undo($current_sections);
    }

    function display() {
        global $OUTPUT;

        $_s = cps::gen_str('block_cps');

        $heading = $_s('team_section_finished');

        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);

        echo $OUTPUT->box_start();
        echo $OUTPUT->notification($_s('team_section_processed'), 'notifysuccess');

        $params = array('id' => $this->id);
        $url = new moodle_url('/blocks/cps/team_section.php', $params);

        echo $OUTPUT->continue_button($url);
        echo $OUTPUT->box_end();

        echo $OUTPUT->footer();
    }
}
