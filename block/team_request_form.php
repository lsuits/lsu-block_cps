<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

interface team_states {
    const QUERY = 'query';
    const REQUEST = 'request';
    const REVIEW = 'review';
    const MANAGE = 'manage';
}

abstract class team_request_form extends cps_form implements team_states {
    public static function next_from($next, $data, $courses) {
        return parent::next_from('team_request', $next, $data, $courses);
    }

    public static function create($courses, $state = null, $extra = null) {
        return parent::create('team_request', $courses, $state, $extra);
    }
}

class team_request_form_select extends team_request_form {
    var $current = self::SELECT;
    var $next = self::SHELLS;

    public static function build($courses) {
        return array('courses' => $courses);
    }

    function definition() {
        $m =& $this->_form;

        $courses = $this->_customdata['courses'];

        $semesters = array();

        $m->addElement('header', 'select_course', self::_s('split_select'));

        foreach ($courses as $course) {
            foreach ($course->sections as $section) {
                $id = $section->semesterid;
                if (isset($semesters[$id])) {
                    continue;
                }

                $semesters[$id] = $section->semester();
            }

            $semester = $semesters[$id];

            $display = "$semester->year $semester->name $course->department $course->cou_number";

            if (cps_team_request::exists($course)) {
                $display .= ' (' . self::_s('team_request_option') . ')';
            }

            $m->addElement('radio', 'selected', '', $display, $course->id);
        }

        $m->addRule('selected', self::_s('err_select_one'), 'required', null, 'client');

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        $course = cps_course::get(array('id' => $data['selected']));

        if (cps_team_request::exists($course)) {
            $this->next = self::UPDATE;
        }

        return true;
    }
}

class team_request_form_update extends team_request_form {
    var $current = self::UPDATE;
    var $next = self::MANAGE;
    var $prev = self::SELECT;

    const ADD_USER_CURRENT = 1;
    const ADD_COURSE = 2;
    const MANAGE_REQUESTS = 3;
    const MANAGE_SECTIONS = 4;

    public static function build($courses) {
        $reshell = optional_param('reshell', 0, PARAM_INT);

        $shells = optional_param('shells', null, PARAM_INT);

        $extra = $shells ? array('shells' => $shells - $reshell) : array();

        return $extra + team_request_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $m->addElement('header', 'selected_course', $to_display($course));

        $m->addElement('static', 'all_requests', self::_s('team_following'), '');

        $team_teaches = cps_team_request::in_course($course);

        $is_master = false;
        $any_approved = false;

        $selected_users = array();
        $queries = array();

        foreach ($team_teaches as $request) {

            if ($request->requested_course == $course->id) {
                $other_params = array('id' => $request->courseid);
                $other_course = cps_course::get($other_params);
            } else {
                $is_master = true;
                $other_params = array('id' => $request->requested_course);
                $other_course = cps_course::get($other_params);

                $user = cps_user::get(array('id' => $request->requested));

                $queries[$request->request_groupingid] = array(
                    'department' => $other_course->department,
                    'cou_number' => $other_course->cou_number
                );

                $selected_users[$request->request_groupingid][] = $user->id;
            }

            if ($request->approval_flag) {
                $any_approved = true;
                $append = self::_s('team_approved');
            } else {
                $append = self::_s('team_not_approved');
            }

            $app_user = empty($user) ? '' : fullname($user);

            $label = $to_display($other_course) . ' with '. $app_user .
                ' - ' . $append;

            $m->addElement('static', 'selected_'.$other_params['id'], '', $label);
        }

        $m->addElement('static', 'breather', '', '');

        if ($is_master) {
            $m->addElement('radio', 'update_option', '',
                self::_s('team_current'), self::ADD_USER_CURRENT);
        }

        $m->addElement('radio', 'update_option', '',
            self::_s('team_add_course'), self::ADD_COURSE);

        $shells = cps_team_request::groups($team_teaches);
        $shells_range = range(1, 10 - $shells);

        $options = array_combine($shells_range, $shells_range);

        $m->addElement('select', 'reshell', self::_s('team_reshell'), $options);

        $m->setDefault('reshell', 1);

        $m->disabledIf('reshell', 'update_option', 'neq', self::ADD_COURSE);

        $m->addElement('radio', 'update_option', '',
            self::_s('team_manage_requests'), self::MANAGE_REQUESTS);

        if ($any_approved) {
            $m->addElement('radio', 'update_option', '',
                self::_s('team_manage_sections'), self::MANAGE_SECTIONS);
        }

        $m->setDefault('update_option', self::MANAGE_REQUESTS);

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'shells', $shells);

        foreach ($queries as $number => $query) {
            $users = implode(',', $selected_users[$number]);

            $m->addElement('hidden', 'selected_users'.$number.'_str', $users);

            foreach ($query as $key => $value) {
                $m->addElement('hidden', 'query'.$number.'['.$key.']', $value);
            }
        }

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        if (isset($data['back'])) {
            return true;
        }

        switch ($data['update_option']) {
            case self::ADD_USER_CURRENT:
                $this->next = self::REQUEST;
                break;
            case self::ADD_COURSE:
                $this->next = self::QUERY;
                break;
            // TODO: handle section forms
        }

        return true;
    }
}

class team_request_form_shells extends team_request_form {
    var $current = self::SHELLS;
    var $prev = self::SELECT;
    var $next = self::QUERY;

    public static function build($courses) {
        $selected = required_param('selected', PARAM_INT);

        $semester = reset($courses[$selected]->sections)->semester();

        return array('selected_course' => $courses[$selected], 'semester' => $semester);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $sem = $this->_customdata['semester'];

        $display = "$sem->year $sem->name $course->department $course->cou_number";

        $m->addElement('header', 'selected_course', $display);

        // TODO: Throw thershold in as admin config
        $threshold = 10;
        $range = range(1, $threshold);
        $options = array_combine($range, $range);

        $m->addElement('select', 'shells', self::_s('team_how_many'), $options);

        $m->addElement('hidden', 'selected', '');

        $this->generate_states_and_buttons();
    }
}

class team_request_form_query extends team_request_form {
    var $current = self::QUERY;
    var $prev = self::SHELLS;
    var $next = self::REQUEST;

    public static function build($courses) {
        $shells = required_param('shells', PARAM_INT);

        $reshell = optional_param('reshell', 0, PARAM_INT);

        // Don't need to dup this add
        $current = required_param('current', PARAM_TEXT);

        $to_add = ($reshell and $current == self::UPDATE);

        $extra = array(
            'shells' => $to_add ? $shells + $reshell: $shells,
        );

        return $extra + team_request_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $shells = $this->_customdata['shells'];

        $update_option = optional_param('update_option', null, PARAM_INT);

        if ($update_option) {
            $m->addElement('hidden', 'update_option', $update_option);

            $this->prev = self::UPDATE;
        }

        $display = "$semester->year $semester->name $course->department $course->cou_number";

        $m->addElement('header', 'selected_course', $display);

        $m->addElement('static', 'err_label', '', '');

        $to_bold = function ($s) { return "<strong>$s</strong>"; };

        $fill = function ($n) {
            $spaces = range(1, $n);
            return array(implode('', array_map(function ($d) {
                return '&nbsp;'; }, $spaces)));
        };

        $dept = self::_s('department');
        $cou = self::_s('cou_number');

        $labels = array(
            $m->createELement('static', 'dept_label', '', $to_bold($dept)),
            $m->createELement('static', 'cou_label', '', $to_bold($cou))
        );

        $m->addGroup($labels, 'query_labels', '&nbsp;', $fill(23), false);

        foreach (range(1, $shells) as $number) {
            $texts = array(
                $m->createELement('text', 'department', ''),
                $m->createELement('text', 'cou_number', '')
            );

            $display = self::_s('team_query_for', $semester);

            $m->addGroup($texts, 'query' . $number, $display, $fill(1), true);

            $m->addElement('hidden', 'selected_users'.$number.'_str', '');
        }

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'shells', '');

        $m->addElement('hidden', 'reshell', 0);

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        global $USER;

        if (isset($data['back'])) {
            return true;
        }

        $one_or_other = function ($one, $other) {
            return ($one and !$other) or (!$one and $other);
        };

        $errors = array();

        $semester = $this->_customdata['semester'];

        $valid = false;
        foreach (range(1, $data['shells']) as $number) {

            $query = $data['query' . $number];

            if (empty($query['department']) and empty($query['cou_number'])) {
                continue;
            }

            if ($one_or_other($query['department'], $query['cou_number'])) {
                $errors['err_label'] = self::_s('err_team_query');
                return $errors;
            }

            // Do any sections exists?
            $course = cps_course::get($query);
            $a = (object) $query;

            if (empty($course)) {
                $errors['err_label'] = self::_s('err_team_query_course', $a);
                return $errors;
            }

            $sections = $course->sections($semester);

            if (empty($sections)) {
                $a->year = $semester->year;
                $a->name = $semester->name;

                $errors['err_label'] = self::_s('err_team_query_sections', $a);
                return $errors;
            }

            $valid = true;
        }

        if (!$valid) {
            $errors['err_label'] = self::_s('err_team_query');
        }

        return $errors;
    }
}

class team_request_form_request extends team_request_form {
    var $current = self::REQUEST;
    var $prev = self::QUERY;
    var $next = self::REVIEW;

    public static function build($courses) {
        $data = team_request_form_query::build($courses);

        $queries = array();
        $current_selected = array();

        foreach (range(1, $data['shells']) as $number) {
            $key = 'query' . $number;

            $query = required_param($key, PARAM_ALPHANUM);

            $queries[$key] = $query;

            $users = optional_param('selected_users'.$number.'_str', null,
                PARAM_TEXT);

            if ($users) {
                $queries['selected_users'.$number] = explode(',', $users);
            }
        }

        return $queries + team_request_form_query::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $selected_course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $update_option = optional_param('update_option', null, PARAM_INT);

        if ($update_option) {
            $m->addElement('hidden', 'update_option', $update_option);
            $adding_user = team_request_form_update::ADD_USER_CURRENT;

            $this->prev = $update_option == $adding_user ? self::UPDATE :
                $this->prev;
        }

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $m->addElement('header', 'selected_course', $to_display($selected_course));

        foreach (range(1, $this->_customdata['shells']) as $number) {
            $users = array();

            $key = 'query' . $number;

            $query = $this->_customdata[$key];

            $m->addElement('hidden', 'query'.$number.'[department]', '');
            $m->addElement('hidden', 'query'.$number.'[cou_number]', '');

            if (empty($query['department'])) {
                $m->addElement('hidden', 'selected_users'.$number, '');
                continue;
            }

            $other_course = cps_course::get(array(
                'department' => $query['department'],
                'cou_number' => $query['cou_number']
            ));

            $other_sections = $other_course->sections($semester);

            $teacher_filters = array(
                'sectionid IN (' . implode(',', array_keys($other_sections)) .')',
                "(status = '" . cps::PROCESSED ."' OR status = '". cps::ENROLLED. "')",
                'primary_flag = 1'
            );

            $other_teachers = cps_teacher::get_select($teacher_filters);

            foreach ($other_teachers as $teacher) {
                $user = $teacher->user();

                $section_info = $other_sections[$teacher->sectionid];

                $display = fullname($user) . " ($section_info,...)";

                $users[$teacher->userid] = $display;
            }

            $m->addElement('static', 'query'.$number.'_course', $to_display($other_course));

            $select =& $m->addElement('select', 'selected_users' . $number,
                self::_s('team_teachers'), $users);

            $select->setMultiple(true);

            $m->addElement('hidden', 'selected_users'.$number.'_str', '');
        }

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'reshell', 0);

        $this->generate_states_and_buttons();
    }

    function validation($data) {

        if (isset($data['back'])) {
            return true;
        }

        $errors = array();

        $shells = $data['shells'];

        foreach (range(1, $shells) as $number) {
            $key = 'selected_users'.$number;

            if (!isset($data[$key])) {
                $errors[$key] = self::_s('err_select_teacher');
            }
        }

        return $errors;
    }
}

class team_request_form_review extends team_request_form {
    var $current = self::REVIEW;
    var $prev = self::REQUEST;
    var $next = self::FINISHED;

    public static function build($courses) {
        $data = team_request_form_request::build($courses);

        $users_data = array();

        foreach (range(1, $data['shells']) as $number) {
            $key = 'selected_users'. $number;

            $users = optional_param($key, null, PARAM_INT);
            $userids = optional_param($key . '_str', null, PARAM_TEXT);

            $userid = ($users) ? $users : explode(',', $userids);

            $users_data['users' . $number] = $userid;
        }

        return $users_data + $data;
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $m->addElement('header', 'review', self::_s('review_selection'));

        $m->addElement('static', 'selected_course', $to_display($course), self::_s('team_with'));

        foreach (range(1, $this->_customdata['shells']) as $number) {
            $query = (object) $this->_customdata['query'. $number];

            $m->addElement('hidden', 'query'.$number.'[department]', '');
            $m->addElement('hidden', 'query'.$number.'[cou_number]', '');

            if (empty($query->department)) {
                $m->addElement('hidden', 'selected_users'.$number.'_str', '');
                continue;
            }

            $userids = implode(',', $this->_customdata['users'.$number]);

            $users = cps_user::get_select('id IN ('. $userids .')');

            foreach ($users as $user) {
                $str = $to_display($query) . ' with ' . fullname($user);

                $m->addElement('static', 'selected_user_'.$user->id, '', $str);
            }

            $m->addElement('hidden', 'selected_users'.$number.'_str', $userids);
        }

        $m->addElement('static', 'breather', '', '');
        $m->addElement('static', 'please_note', self::_s('team_note'), self::_s('team_going_email'));

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'shells', '');
        $m->addElement('hidden', 'reshell', 0);

        $update_option = optional_param('update_option', null, PARAM_INT);
        if ($update_option) {
            $m->addElement('hidden', 'update_option', $update_option);
        }

        $this->generate_states_and_buttons();
    }
}

class team_request_form_finish implements finalized_form {
    function process($data, $courses) {
        // TODO: retrieval
        $this->save_or_update($data, array());
    }

    function undo($teamteaches) {
        foreach ($teamteaches as $teamteach) {
            cps_team_request::delete($teamteach->id);
        }
    }

    function save_or_update($data, $current_teamteaches) {
        global $USER;

        foreach (range(1, $data->shells) as $number) {
            $requested = cps_course::get($data->{'query'.$number});

            $selected = explode(',', $data->{'selected_users'.$number.'_str'});

            foreach ($selected as $userid) {
                $params = array (
                    'userid' => $USER->id,
                    'courseid' => $data->selected,
                    'requested_course' => $requested->id,
                    'requested' => $userid,
                    'request_groupingid' => $number
                );

                if (!$request = cps_team_request::get($params)) {
                    $request = new cps_team_request();
                    $request->fill_params($params);
                }

                $request->save();

                unset ($current_teamteaches[$request->id]);
            }
        }

        $this->undo($current_teamteaches);
    }

    function display() {
        global $OUTPUT;

        $_s = cps::gen_str('block_cps');

        echo $OUTPUT->header();
        echo $OUTPUT->heading($_s('team_request_finish'));

        echo $OUTPUT->box_start();

        echo $OUTPUT->notification($_s('team_request_thank_you'), 'notifysuccess');
        echo $OUTPUT->continue_button(new moodle_url('/blocks/cps/team_request.php'));

        echo $OUTPUT->box_end();

        echo $OUTPUT->footer();

    }
}
