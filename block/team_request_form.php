<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

interface team_states {
    const QUERY = 'query';
    const REQUEST = 'request';
    const REVIEW = 'review';
    const MANAGE = 'manage';
    const SECTIONS = 'sections';
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

            if (cps_team_request::exists($course, $semester)) {
                $display .= ' (' . self::_s('team_request_option') . ')';
            }

            $m->addElement('radio', 'selected', '', $display, $course->id);
        }

        $m->addRule('selected', self::_s('err_select_one'), 'required', null, 'client');

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        $course = $this->_customdata['courses'][$data['selected']];

        $semester = reset($course->sections)->semester();

        if (cps_team_request::exists($course, $semester)) {
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

        $team_teaches = cps_team_request::in_course($course, $semester);

        $is_master = false;
        $any_approved = false;

        $selected_users = array();
        $queries = array();

        $grouping_map = array();
        $groupingid = 0;
        foreach ($team_teaches as $request) {

            if ($request->is_owner()) {
                $is_master = true;

                $user = $request->other_user();

                $other_course = $request->other_course();

                if (!isset($grouping_map[$other_course->id])) {
                    $groupingid ++;
                    $grouping_map[$other_course->id] = $groupingid;
                }

                $queries[$groupingid] = array(
                    'department' => $other_course->department,
                    'cou_number' => $other_course->cou_number
                );

                $selected_users[$groupingid][] = $user->id;
            }

            if ($request->approved()) {
                $any_approved = true;
                $append = self::_s('team_approved');
            } else {
                $append = self::_s('team_not_approved');
            }

            $label = $request->label() . ' - ' . $append;

            $m->addElement('static', 'selected_'.$request->id, '', $label);
        }

        $m->addElement('static', 'breather', '', '');

        if ($is_master) {
            $m->addElement('radio', 'update_option', '',
                self::_s('team_current'), self::ADD_USER_CURRENT);
        }

        $m->addElement('radio', 'update_option', '',
            self::_s('team_add_course'), self::ADD_COURSE);

        $shells_range = range(1, 10 - $groupingid);

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
        $m->addElement('hidden', 'shells', $groupingid);

        $m->addElement('hidden', 'semesterid', $semester->id);

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
            case self::MANAGE_SECTIONS:
                $this->next = self::SECTIONS;
                break;
        }

        return true;
    }
}

class team_request_form_manage extends team_request_form {
    var $current = self::MANAGE;
    var $prev = self::UPDATE;
    var $next = self::CONFIRM;

    const NOTHING = 0;
    const APPROVE = 1;
    const REVOKE = 2;

    public static function build($courses) {
        return team_request_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $to_bold = function ($text) { return "<strong>$text</strong>"; };

        $filler = function ($how_much) {
            $spaces = range(1, $how_much);

            return implode('', array_map(function ($sp) {
                return '&nbsp;';
            }, $spaces));
        };

        $m->addElement('header', 'selected_course', $to_display($course));

        $m->addElement('static', 'team_error', '', '');

        $m->addElement('static', 'action_labels', '',
            $to_bold(self::_s('team_actions')). $filler(50) .
            $to_bold(self::_s('team_requested_courses')));

        $team_teaches = cps_team_request::in_course($course, $semester);

        foreach ($team_teaches as $request) {
            // The master of this request
            $master = $request->is_owner();

            $approval = $request->approved() ?
                self::_s('team_approved') :
                self::_s('team_not_approved');

            $label = $request->label(). ' - <strong>' . $approval . '</strong>';

            $options = array (
                $m->createELement('radio', 'approval_'.$request->id, '',
                    self::_s('team_do_nothing'), self::NOTHING)
            );

            if (!$master and !$request->approved()) {
                $options[] =
                    $m->createELement('radio', 'approval_'.$request->id, '',
                        self::_s('team_approve'), self::APPROVE);
            }

            if ($master) {
                $verbiage = self::_s('team_revoke');
            } else if ($request->approved()) {
                $verbiage = self::_s('team_cancel');
            } else {
                $verbiage = self::_s('team_deny');
            }

            $options[] =
                $m->createELement('radio', 'approval_'.$request->id, '',
                    $verbiage, self::REVOKE);

            $options[] =
                $m->createElement('static', 'request'.$request->id, '', $label);

            $m->addGroup($options, 'options_'.$request->id, '&nbsp;',
                $filler(3), true);
        }

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'semesterid', $semester->id);

        $m->addElement('hidden', 'update_option', '');

        $this->generate_states_and_buttons();
    }

    function validation($data) {

        if (isset($data['back'])) {
            return true;
        }

        $selected = 0;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $teams = cps_team_request::in_course($course, $semester);

        foreach ($teams as $id =>$team) {
            $approval = $data['options_'.$id]['approval_'.$id];

            if ($approval != self::NOTHING) {
                $selected ++;
            }
        }

        if (empty($selected)) {
            return array('team_error' => self::_s('err_manage_one'));
        }

        return true;
    }
}

class team_request_form_confirm extends team_request_form {
    var $current = self::CONFIRM;
    var $next = self::FINISHED;
    var $prev = self::MANAGE;

    public static function build($courses) {
        return team_request_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $team_teaches = cps_team_request::in_course($course, $semester);

        $m->addElement('header', 'selected_course', $to_display($course));

        $approved = array();
        $denied = array();

        foreach ($team_teaches as $id => $request) {

            $m->addElement('hidden', 'options_'.$id.'[approval_'.$id.']', '');;

            if (!isset($this->_customdata['options_'.$id])) {
                continue;
            }

            $action = $this->_customdata['options_' . $id]['approval_'.$id];

            switch ($action) {
                case team_request_form_manage::APPROVE:
                    $approved[] = $request;
                    break;
                case team_request_form_manage::REVOKE:
                    $denied[] = $request;
                case team_request_form_manage::NOTHING:
                    continue;
            }
        }

        if ($approved) {
            $m->addElement('static', 'approved', self::_s('team_to_approve'), '');

            foreach ($approved as $request) {
                $m->addElement('static', 'approve_'.$request->id, '', $request->label());
            }
        }

        if ($denied) {
            $m->addElement('static', 'not_approved', self::_s('team_to_revoke'), '');

            foreach ($denied as $request) {
                $m->addElement('static', 'deny_'.$request->id, '', $request->label());
            }
        }

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'update_option', '');
        $m->addElement('hidden', 'semesterid', $semester->id);

        $this->generate_states_and_buttons();
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
        $m->addElement('hidden', 'semesterid', $sem->id);


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
            'reshell' => $reshell
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
        $m->addElement('hidden', 'semesterid', $semester->id);


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

            $other_teachers = $other_course->teachers($semester);

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
        $m->addElement('hidden', 'semesterid', $semester->id);


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

            $userid = ($users) ? implode(',', $users) : $userids;

            $users_data[$key . '_str'] = $userid;
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

            $userids = $this->_customdata['selected_users'.$number.'_str'];

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

        $m->addElement('hidden', 'semesterid', $semester->id);

        $this->generate_states_and_buttons();
    }
}

class team_request_form_finish implements finalized_form {
    function process($data, $courses) {

        $course = $courses[$data->selected];

        $semester = cps_semester::get(array('id' => $data->semesterid));

        $teamteaches = cps_team_request::in_course($course, $semester);

        $exists = !empty($data->update_option);

        if ($exists and $data->update_option == team_request_form_update::MANAGE_REQUESTS) {
            $this->handle_approvals($data, $teamteaches);
        } else {
            $this->save_or_update($data, $teamteaches);
        }
    }

    function handle_approvals($data, $teamteaches) {
        $to_undo = array();

        foreach ($teamteaches as $id => $teamteach) {
            $action = $data->{'options_'.$id}['approval_'.$id];

            switch ($action) {
                case team_request_form_manage::APPROVE:
                    // TODO: notify master
                    $teamteach->approval_flag = 1;
                    $teamteach->save();
                    break;
                case team_request_form_manage::REVOKE:
                    // TODO: send formal letter
                    $to_undo[] = $teamteach;
                    break;
            }
        }

        $this->undo($to_undo);
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
                    'semesterid' => $data->semesterid,
                    'requested_course' => $requested->id,
                    'requested' => $userid
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