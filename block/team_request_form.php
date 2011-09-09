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

            $m->addElement('radio', 'selected', '', $display, $course->id);
        }

        $m->addRule('selected', self::_s('err_select_one'), 'required', null, 'client');

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

        $this->generate_states_and_buttons();
    }
}

class team_request_form_query extends team_request_form {
    var $current = self::QUERY;
    var $prev = self::SHELLS;
    var $next = self::REQUEST;

    public static function build($courses) {
        $shells = required_param('shells', PARAM_INT);

        return array('shells' => $shells) + team_request_form_shells::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

        $shells = $this->_customdata['shells'];

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
        }

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'shells', '');

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
        foreach (range(1, $data['shells']) as $number) {
            $key = 'query' . $number;

            $query = required_param($key, PARAM_ALPHANUM);

            $queries[$key] = $query;
        }

        return $queries + team_request_form_query::build($courses);
    }

    function definition() {
        $m =& $this->_form;

        $selected_course = $this->_customdata['selected_course'];

        $semester = $this->_customdata['semester'];

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

        }

        $m->addElement('hidden', 'selected', '');
        $m->addElement('hidden', 'shells', '');

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
