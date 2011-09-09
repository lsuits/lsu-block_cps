<?php

require_once $CFG->dirroot . '/blocks/cps/formslib.php';

interface team_states {
    const QUERY = 'query';
    const REQUEST = 'request';
    const REVIEW = 'review';
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
    var $next = self::QUERY;

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

            $m->addElement('radio', 'selected', '', $display, $course->id);
        }

        $m->addRule('selected', self::_s('err_select_one'), 'required', null, 'client');

        $this->generate_states_and_buttons();
    }
}

class team_request_form_query extends team_request_form {
    var $current = self::QUERY;
    var $prev = self::SELECT;
    var $next = self::REQUEST;

    public static function build($courses) {
        $selected = required_param('selected', PARAM_INT);

        $semester = reset($courses[$selected]->sections)->semester();

        return array('selected_course' => $courses[$selected], 'semester' => $semester);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $display = "$semester->year $semester->name $course->department $course->cou_number";

        $m->addElement('header', 'selected_course', $display);

        $m->addElement('static', 'err_label', '', '');

        $texts = array(
            $m->createELement('text', 'department', ''),
            $m->createELement('text', 'cou_number', '')
        );

        $display = self::_s('team_query_for', $semester);

        $to_bold = function ($s) { return "<strong>$s</strong>"; };

        $dept = self::_s('department');
        $cou = self::_s('cou_number');

        $labels = array(
            $m->createELement('static', 'dept_label', '', $to_bold($dept)),
            $m->createELement('static', 'cou_label', '', $to_bold($cou))
        );


        $fill = function ($n) {
            $spaces = range(1, $n);
            return array(implode('', array_map(function ($d) {
                return '&nbsp;'; }, $spaces)));
        };

        $m->addGroup($labels, 'query_labels', '&nbsp;', $fill(23), false);

        $m->addGroup($texts, 'query', $display, $fill(1), true);

        $m->addElement('hidden', 'selected', '');

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        global $USER;

        $query = $data['query'];

        $errors = array();

        if (empty($query['department']) or empty($query['cou_number'])) {
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

        $sections = $course->sections($this->_customdata['semester']);

        if (empty($sections)) {
            $a->year = $semester->year;
            $a->name = $semester->name;

            $errors['err_label'] = self::_s('err_team_query_sections', $a);
        }

        return $errors;
    }
}

class team_request_form_request extends team_request_form {
    var $current = self::REQUEST;
    var $prev = self::QUERY;
    var $next = self::REVIEW;

    public static function build($courses) {
        $query = required_param('query', PARAM_ALPHANUM);

        return $query + team_request_form_query::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $selected_course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $other_course = cps_course::get(array(
            'department' => $this->_customdata['department'],
            'cou_number' => $this->_customdata['cou_number']
        ));

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $other_sections = $other_course->sections($semester);

        $teacher_filters = array(
            'sectionid IN (' . implode(',', array_keys($other_sections)) .')',
            "(status = '" . cps::PROCESSED ."' OR status = '". cps::ENROLLED. "')",
            'primary_flag = 1'
        );

        $other_teachers = cps_teacher::get_select($teacher_filters);

        $users = array();

        foreach ($other_teachers as $teacher) {
            $user = $teacher->user();

            $section_info = $other_sections[$teacher->sectionid];

            $display = fullname($user) . " ($section_info,...)";

            $users[$teacher->userid] = $display;
        }

        $m->addElement('header', 'selected_course', $to_display($selected_course));

        $m->addElement('static', 'query_course', $to_display($other_course));

        $select =& $m->addElement('select', 'selected_users', self::_s('team_teachers'), $users);
        $select->setMultiple(true);

        $m->addElement('hidden', 'query[department]', '');
        $m->addElement('hidden', 'query[cou_number]', '');
        $m->addElement('hidden', 'selected', '');

        $this->generate_states_and_buttons();
    }

    function validation($data) {
        if (isset($data['save']) and empty($data['selected_users'])) {
            return array('selected_users' => self::_s('err_select_teacher'));
        }

        return true;
    }
}

class team_request_form_review extends team_request_form {
    var $current = self::REVIEW;
    var $prev = self::REQUEST;
    var $next = self::FINISHED;

    public static function build($courses) {
        $users = optional_param('selected_users', null, PARAM_INT);
        $userids = optional_param('selected_users_str', null, PARAM_TEXT);

        $userid = ($users) ? $users : explode(',', $userids);

        return array('users' => $userid) + team_request_form_request::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $query = new stdClass;
        $query->department = $this->_customdata['department'];
        $query->cou_number = $this->_customdata['cou_number'];

        $userids = implode(',', $this->_customdata['users']);

        $users = cps_user::get_select('id IN ('. $userids .')');

        $to_display = function ($course) use ($semester) {
            return "$semester->year $semester->name $course->department $course->cou_number";
        };

        $m->addElement('header', 'review', self::_s('review_selection'));

        $m->addElement('static', 'selected_course', $to_display($course), self::_s('team_with'));

        foreach ($users as $user) {
            $str = $to_display($query) . ' with ' . fullname($user);

            $m->addElement('static', 'selected_user_'.$user->id, '', $str);
        }

        $m->addElement('static', 'breather', '', '');
        $m->addElement('static', 'please_note', self::_s('team_note'), self::_s('team_going_email'));

        $m->addElement('hidden', 'query[department]', '');
        $m->addElement('hidden', 'query[cou_number]', '');
        $m->addElement('hidden', 'selected', '');

        $m->addElement('hidden', 'selected_users_str', $userids);

        $this->generate_states_and_buttons();
    }
}

class team_request_form_finish implements finalized_form {
    function process($data, $courses) {

    }

    function undo($teamteaches) {
        foreach ($teamteaches as $teamteach) {
            cps_team_request::delete($teamteach->id);
        }
    }

    function save_or_update($data, $current_teamteaches) {
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
