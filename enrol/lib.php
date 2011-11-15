<?php

defined('MOODLE_INTERNAL') or die();

require_once dirname(__FILE__) . '/publiclib.php';

class enrol_cps_plugin extends enrol_plugin {
    /** Typical errorlog for cron run */
    var $errors = array();

    /** Typical email log for cron runs */
    var $emaillog = array();

    var $is_silent = false;

    private $_provider;

    private $_loaded = false;

    function __construct() {
        global $CFG;

        $lib = cps::base('classes/dao');

        cps::require_daos();
        require_once $CFG->dirroot . '/group/lib.php';
    }

    function init() {
        $this->_loaded = true;

        try {
            $this->_provider = cps::create_provider();

        } catch (Exception $e) {
            $a = cps::translate_error($e);

            $this->errors[] = cps::_s('provider_cron_problem', $a);
        }
    }

    function provider() {
        if (empty($this->_provider) and !$this->_loaded) {
            $this->init();
        }

        return $this->_provider;
    }

    public function is_cron_required() {
        $automatic = $this->setting('cron_run');

        $running = (bool)$this->setting('running');

        if ($automatic) {
            $this->handle_automatic_errors();

            $current_hour = (int)date('H');

            $acceptable_hour = (int)$this->setting('cron_hour');

            $right_time = ($current_hour == $acceptable_hour);

            return (
                $right_time and
                parent::is_cron_required() and
                !$running
            );
        }

        return !$running;
    }

    private function handle_automatic_errors() {
        $errors = cps_error::get_all();

        $error_threshold = $this->setting('error_threshold');

        if (count($errors) > $error_threshold) {
            $this->errors[] = cps::_s('error_threshold_log');
            return;
        }

        cps::reprocess_errors($errors, true);
    }

    public function cron() {
        $this->setting('running', true);

        if ($this->provider()) {
            $this->log("
     ________  ____  ____              ____               __
    / ___/ _ \/ __/ / __/__  _______  / / /_ _  ___ ___  / /_
   / /__/ ___/\ \  / _// _ \/ __/ _ \/ / /  ' \/ -_) _ \/ __/
   \___/_/  /___/ /___/_//_/_/  \___/_/_/_/_/_/\__/_//_/\__/
            ");

            $start = microtime();

            $this->full_process();

            $end = microtime();

            $how_long = microtime_diff($start, $end);

            $this->log('------------------------------------------------');
            $this->log('CPS enrollment took: ' . $how_long . ' secs');
            $this->log('------------------------------------------------');
        }

        $this->email_reports();

        $this->setting('running', false);
    }

    public function email_reports() {
        global $CFG;

        $admins = get_admins();

        if ($this->setting('email_report')) {
            $email_text = implode("\n", $this->emaillog);

            foreach ($admins as $admin) {
                email_to_user($admin, $CFG->noreplyaddress,
                    'CPS Enrollment Log', $email_text);
            }
        }

        if (!empty($this->errors)) {
            $error_text = implode("\n", $this->errors);

            foreach ($admins as $admin) {
                email_to_user($admin, $CFG->noreplyaddress,
                    '[SEVERE] CPS Enrollment Errors', $error_text);
            }
        }
    }

    public function setting($key, $value = null) {
        if ($value !== null) {
            return set_config($key, $value, 'enrol_cps');
        } else {
            return get_config('enrol_cps', $key);
        }
    }

    public function full_process() {

        $this->provider()->preprocess();

        $provider_name = $this->provider()->get_name();

        $this->log('Pulling information from ' . cps::_s($provider_name . '_name'));
        $this->process_all();
        $this->log('------------------------------------------------');

        $this->log('Begin manifestation ...');
        $this->handle_enrollments();

        if (!$this->provider()->postprocess()) {
            $this->errors[] = 'Error during postprocess.';
        }
    }

    public function handle_enrollments() {
        $pending = cps_section::get_all(array('status' => cps::PENDING));

        $this->handle_pending_sections($pending);

        $processed = cps_section::get_all(array('status' => cps::PROCESSED));

        $this->handle_processed_sections($processed);
    }

    public function process_all() {
        $time = time();

        $processed_semesters = $this->get_semesters($time);

        foreach ($processed_semesters as $semester) {
            $this->process_semester($semester);
        }
    }

    public function process_semester($semester) {
        $process_courses = $this->get_courses($semester);

        if (empty($process_courses)) {
            return;
        }

        $departments = cps_course::flatten_departments($process_courses);

        foreach ($departments as $department => $courseids) {
            $filters = array(
                'semesterid = '.$semester->id,
                'courseid IN ('.implode(',', $courseids).')',
            );

            $current_sections = cps_section::get_select($filters);

            $this->process_enrollment_by_department(
                $semester, $department, $current_sections
            );
        }
    }

    public function get_semesters($time) {
        $ninety_days = 24 * 90 * 60 * 60;
        $now = cps::format_time($time - $ninety_days);

        $this->log('Pulling Semesters for ' . $now . '...');

        try {
            $semester_source = $this->provider()->semester_source();
            $semesters = $semester_source->semesters($now);

            $this->log('Processing ' . count($semesters) . " Semesters...\n");
            $this->process_semesters($semesters);

            $sems_in = function ($time) {
                return cps_semester::in_session($time);
            };

            $processed_semesters = $sems_in($time) + $sems_in($time + $ninety_days);

            return $processed_semesters;
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return array();
        }
    }

    public function get_courses($semester) {
        $this->log('Pulling Courses / Sections for ' . $semester);
        try {
            $courses = $this->provider()->course_source()->courses($semester);

            $this->log('Processing ' . count($courses) . " Courses...\n");
            $process_courses = $this->process_courses($semester, $courses);

            return $process_courses;
        } catch (Exception $e) {
            $this->errors[] = 'Unable to process courses for ' . $semester;

            // Queue up errors
            cps_error::courses($semester)->save();

            return array();
        }
    }

    public function process_enrollment_by_department($semester, $department, $current_sections) {
        try {
            $teacher_source = $this->provider()->teacher_department_source();
            $student_source = $this->provider()->student_department_source();

            $teachers = $teacher_source->teachers($semester, $department);
            $students = $student_source->students($semester, $department);

            $sectionids = cps_section::ids_by_course_department($semester, $department);

            $filter = array('sectionid IN ('.$sectionids.')');
            $current_teachers = cps_teacher::get_select($filter);
            $current_students = cps_student::get_select($filter);

            $ids_param = array('id IN ('.$sectionids.')');
            $all_sections = cps_section::get_select($ids_param);

            $this->process_teachers_by_department($semester, $department, $teachers, $current_teachers);
            $this->process_students_by_department($semester, $department, $students, $current_students);

            unset($current_teachers);
            unset($current_students);

            foreach ($current_sections as $section) {
                $course = $section->course();
                $this->post_section_process($semester, $course, $section);
                unset($all_sections[$section->id]);
            }

            // Drop remaining
            $remaining = implode(', ', array_keys($all_sections));
            if (!empty($remaining)) {
                cps_section::update_select(
                    array('status' => cps::PENDING),
                    array('id IN ('. $remaining .')')
                );
            }

        } catch (Exception $e) {
            $info = "$semester $department";
            $this->errors[] = 'Failed to process enrollment for ' . $info;

            cps_error::department($semester, $department)->save();
        }
    }

    public function process_teachers_by_department($semester, $department, $teachers, $current_teachers) {
        $this->fill_roles_by_department('teacher', $semester, $department, $teachers, $current_teachers);
    }

    public function process_students_by_department($semester, $department, $students, $current_students) {
        $this->fill_roles_by_department('student', $semester, $department, $students, $current_students);
    }

    private function fill_roles_by_department($type, $semester, $department, $pulled_users, $current_users) {
        foreach ($pulled_users as $user) {
            $course_params = array(
                'department' => $department,
                'cou_number' => $user->cou_number
            );

            $course = cps_course::get($course_params);

            if (empty($course)) {
                continue;
            }

            $section_params = array(
                'semesterid' => $semester->id,
                'courseid' => $course->id,
                'sec_number' => $user->sec_number
            );

            $section = cps_section::get($section_params);

            if (empty($section)) {
                continue;
            }

            $this->{'process_'.$type.'s'}($section, array($user), $current_users);
        }

        $this->release($type, $current_users);
    }

    public function process_semesters($semesters) {
        $processed = array();

        foreach ($semesters as $semester) {
            try {
                $params = array(
                    'year' => $semester->year,
                    'name' => $semester->name,
                    'campus' => $semester->campus,
                    'session_key' => $semester->session_key
                );

                $cps = cps_semester::upgrade_and_get($semester, $params);

                if (empty($cps->classes_start)) {
                    continue;
                }

                $cps->save();

                events_trigger('cps_semester_process', $cps);

                $processed[] = $cps;
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return $processed;
    }

    public function process_courses($semester, $courses) {
        $processed = array();

        foreach ($courses as $course) {
            try {
                $params = array(
                    'department' => $course->department,
                    'cou_number' => $course->cou_number
                );

                $cps_course = cps_course::upgrade_and_get($course, $params);

                $cps_course->save();

                events_trigger('cps_course_process', $cps_course);

                $processed_sections = array();
                foreach ($cps_course->sections as $section) {
                    $params = array(
                        'courseid' => $cps_course->id,
                        'semesterid' => $semester->id,
                        'sec_number' => $section->sec_number
                    );

                    $cps_section = cps_section::upgrade_and_get($section, $params);

                    if (empty($cps_section->id)) {
                        $cps_section->courseid = $cps_course->id;
                        $cps_section->semesterid = $semester->id;
                        $cps_section->status = cps::PENDING;

                        $cps_section->save();
                    }

                    $processed_sections[] = $cps_section;
                }

                // Mutating sections tied to course
                $cps_course->sections = $processed_sections;

                $processed[] = $cps_course;
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return $processed;
    }

    /**
     * Could be used to process a single course upon request
     */
    public function process_enrollment($semester, $course, $section) {
        $teacher_source = $this->provider()->teacher_source();

        $student_source = $this->provider()->student_source();

        try {
            $teachers = $teacher_source->teachers($semester, $course, $section);
            $students = $student_source->students($semester, $course, $section);

            $filter = array('sectionid' => $section->id);
            $current_teachers = cps_teacher::get_all($filter);
            $current_students = cps_student::get_all($filter);

            $this->process_teachers($section, $teachers, $current_teachers);
            $this->process_students($section, $students, $current_students);

            $this->release('teacher', $current_teachers);
            $this->release('student', $current_students);

            $this->post_section_process($semester, $course, $section);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();

            cps_error::section($section)->save();
        }
    }

    private function release($type, $users) {
        foreach ($users as $user) {
            $user->status = cps::PENDING;
            $user->save();

            events_trigger('cps_' . $type . '_release', $user);
        }
    }

    private function post_section_process($semester, $course, $section) {
        // Process section only if teachers can be processed
        // Take into consideration outside forces manipulating
        // processed numbers through event handlers
        $by_processed = array(
            "status IN ('". cps::PROCESSED . "', '" . cps::ENROLLED ."')",
            'sectionid = ' .$section->id
        );

        $processed_teachers = cps_teacher::count_select($by_processed);

        // A section _can_ be processed only if they have a teacher
        if (!empty($processed_teachers)) {
            // Full section
            $section->semester = $semester;
            $section->course = $course;

            $previous_status = $section->status;

            $count = function ($type) use ($section) {
                $enrollment = array(
                    'sectionid = '.$section->id,
                    "status IN ('".cps::PENDING."','".cps::PROCESSED."')"
                );

                $class = 'cps_'.$type;

                return $class::count_select($enrollment);
            };

            $will_enroll = ($count('teacher') or $count('student'));

            if ($will_enroll) {
                $section->status = cps::PROCESSED;
            }

            // Allow outside interaction
            events_trigger('cps_section_process', $section);

            if ($previous_status != $section->status) {
                $section->save();
            }
        }
    }

    public function process_teachers($section, $users, &$current_users) {
        return $this->fill_role('teacher', $section, $users, $current_users, function($user) {
            return array('primary_flag' => $user->primary_flag);
        });
    }

    public function process_students($section, $users, &$current_users) {
        return $this->fill_role('student', $section, $users, $current_users);
    }

    public function handle_pending_sections($sections) {
        global $DB;

        if ($sections) {
            $this->log('Found ' . count($sections) . ' Sections that will not be manifested.');
        }

        foreach ($sections as $section) {
            if ($section->is_manifested()) {

                $params = array('idnumber' => $section->idnumber);

                $course = $section->moodle();

                $last_section = cps_section::count($params) == 1;

                $cps_course = $section->course();

                $group = $this->manifest_group($course, $cps_course, $section);

                foreach (array('student', 'teacher') as $type) {
                    $class = 'cps_' . $type;

                    $params = array(
                        'sectionid' => $section->id, 'status' => 'enrolled'
                    );

                    if ($last_section and $type == 'teacher') {
                        $params['primary_flag'] = 0;
                    }

                    $users = $class::get_all($params);

                    $this->unenroll_users($group, $users);
                }

                if ($last_section) {
                    $course->visible = 0;

                    $DB->update_record('course', $course);

                    $this->log('Unloading ' . $course->idnumber);

                    events_trigger('cps_course_severed', $course);
                }

                $section->idnumber = null;
            }
            $section->status = cps::SKIPPED;
            $section->save();
        }

        $this->log('');
    }

    public function handle_processed_sections($sections) {
        if ($sections) {
            $this->log('Found ' . count($sections) . ' Sections ready to be manifested.');
        }

        foreach ($sections as $section) {
            if ($section->status == cps::PENDING) {
                continue;
            }

            $semester = $section->semester();

            $course = $section->course();

            $success = $this->manifestation($semester, $course, $section);

            if ($success) {
                $section->status = cps::MANIFESTED;
                $section->save();
            }
        }

        $this->log('');
    }

    public function get_instance($courseid) {
        global $DB;

        $instances = enrol_get_instances($courseid, true);

        $attempt = array_filter($instances, function($in) {
            return $in->enrol == 'cps';
        });

        // Cannot enrol without an instance
        if (empty($attempt)) {
            $course_params = array('id' => $courseid);
            $course = $DB->get_record('course', $course_params);

            $id = $this->add_instance($course);

            return $DB->get_record('enrol', array('id' => $id));
        } else {
            return current($attempt);
        }

    }

    private function manifestation($semester, $course, $section) {
        // Check for instructor changes
        $teacher_params = array(
            'sectionid' => $section->id,
            'primary_flag' => 1
        );

        $new_primary = cps_teacher::get($teacher_params + array(
            'status' => cps::PROCESSED
        ));

        $old_primary = cps_teacher::get($teacher_params + array(
            'status' => cps::PENDING
        ));

        // Campuses may want to handle primary instructor changes differently
        if ($new_primary and $old_primary) {
            events_trigger('cps_primary_change', array(
                'section' => $section,
                'old_teacher' => $old_teacher,
                'new_teacher' => $new_teacher
            ));
        }

        // For certain we are working with a real course
        $moodle_course = $this->manifest_course($semester, $course, $section);

        $this->manifest_course_enrollment($moodle_course, $course, $section);

        return true;
    }

    private function manifest_course_enrollment($moodle_course, $course, $section) {
        $group = $this->manifest_group($moodle_course, $course, $section);

        $general_params = array('sectionid' => $section->id);

        $actions = array(
            cps::PENDING => 'unenroll',
            cps::PROCESSED => 'enroll'
        );

        $unenroll_count = $enroll_count = 0;

        foreach (array('teacher', 'student') as $type) {
            $class = 'cps_' . $type;

            foreach ($actions as $status => $action) {
                $action_params = $general_params + array('status' => $status);
                ${$action . '_count'} = $class::count($action_params);

                if (${$action . '_count'}) {
                    $to_action = $class::get_all($action_params);
                    $this->{$action . '_users'}($group, $to_action);
                }
            }
        }

        if ($unenroll_count and $enroll_count) {
            $this->log('Manifesting enrollment for: ' . $moodle_course->idnumber .
            ' ' . $section->sec_number);

            $out = '';
            if ($unenroll_count) {
                $out .= 'Unenrolled ' . $unenroll_count . ' users; ';
            }

            if ($enroll_count) {
                $out .= 'Enrolled ' . $enroll_count . ' users';
            }

            $this->log($out);
        }
    }

    private function enroll_users($group, $users) {
        $instance = $this->get_instance($group->courseid);

        foreach ($users as $user) {
            $shortname = $this->determine_role($user);
            $roleid = $this->setting($shortname . '_role');

            $this->enrol_user($instance, $user->userid, $roleid);

            groups_add_member($group->id, $user->userid);

            $user->status = cps::ENROLLED;
            $user->save();

            $event_params = array(
                'group' => $group,
                'cps_user' => $user
            );

            events_trigger('cps_' . $shortname . '_enroll', $event_params);
        }
    }

    private function unenroll_users($group, $users) {
        global $DB;

        $instance = $this->get_instance($group->courseid);

        $course = $DB->get_record('course', array('id' => $group->courseid));

        foreach ($users as $user) {
            $shortname = $this->determine_role($user);

            $class = 'cps_' . $shortname;

            $roleid = $this->setting($shortname . '_role');

            groups_remove_member($group->id, $user->userid);

            $to_status = $user->status == cps::PENDING ?
                cps::UNENROLLED :
                cps::PROCESSED;

            $user->status = $to_status;
            $user->save();

            $sections = $user->sections_by_status(cps::ENROLLED);

            $enrolled_sections = array_filter($sections, function($section) use ($course) {
                return $section->idnumber == $course->idnumber;
            });

            if (!count($enrolled_sections)) {
                $this->unenrol_user($instance, $user->userid, $roleid);
            }

            if ($to_status == cps::UNENROLLED) {
                $event_params = array(
                    'group' => $group,
                    'cps_user' => $user
                );

                events_trigger('cps_' . $shortname . '_unenroll', $event_params);
            }
        }

        $count_params = array('groupid' => $group->id);
        if (!$DB->count_records('groups_members', $count_params)) {

            // Going ahead and delete as delete
            groups_delete_group($group);

            events_trigger('cps_group_emptied', $group);
        }
    }

    private function manifest_group($moodle_course, $course, $section) {
        global $DB;

        $group_params = array(
            'courseid' => $moodle_course->id,
            'name' => "{$course->department} {$course->cou_number} {$section->sec_number}"
        );

        if (!$group = $DB->get_record('groups', $group_params)) {
            $group = (object) $group_params;
            $group->id = groups_create_group($group);
        }

        return $group;
    }

    private function manifest_course($semester, $course, $section) {
        global $DB;

        $teacher_params = array(
            'sectionid = ' . $section->id,
            'primary_flag = 1',
            "(status = '".cps::PROCESSED."' OR status = '".cps::ENROLLED."')"
        );

        $primary_teacher = current(cps_teacher::get_select($teacher_params));

        if (!$primary_teacher) {
            $teacher_params[1] = 'primary_flag = 0';

            $primary_teacher = current(cps_teacher::get_select($teacher_params));
        }

        $assumed_idnumber = $semester->year . $semester->name .
            $course->department . $semester->session_key . $course->cou_number .
            $primary_teacher->userid;

        // Take into consideration of outside forces manipulating idnumbers
        // Therefore we must check the section's idnumber before creating one
        // Possibility the course was deleted externally

        $idnumber = $section->idnumber ? $section->idnumber : $assumed_idnumber;

        $course_params = array('idnumber' => $idnumber);

        $moodle_course = $DB->get_record('course', $course_params);

        if (!$moodle_course) {
            $user = $primary_teacher->user();

            $session = empty($semester->session_key) ? '' :
                '(' . $semester->session_key . ')';

            $assumed_fullname = sprintf('%s %s %s %s %s for %s', $semester->year,
                $semester->name, $course->department, $session, $course->cou_number,
                fullname($user));

            $category = $this->manifest_category($course);

            $a = new stdclass;
            $a->year = $semester->year;
            $a->name = $semester->name;
            $a->session = $session;
            $a->department = $course->department;
            $a->course_number = $course->cou_number;
            $a->fullname = fullname($user);

            $pattern = $this->setting('course_shortname');

            $shortname = cps::format_string($pattern, $a);

            $moodle_course->idnumber = $idnumber;
            $moodle_course->shortname = $shortname;
            $moodle_course->fullname = $assumed_fullname;
            $moodle_course->category = $category->id;
            $moodle_course->summary = $course->fullname;
            $moodle_course->startdate = $semester->classes_start;
            $moodle_course->visible = $this->setting('course_visible');
            $moodle_course->format = $this->setting('course_format');
            $moodle_course->numsections = $this->setting('course_numsections');

            // Actually needs to happen, before the create call
            events_trigger('cps_course_create', $moodle_course);

            $moodle_course = create_course($moodle_course);

            $this->add_instance($moodle_course);
        }

        if (!$section->idnumber) {
            $section->idnumber = $moodle_course->idnumber;
            $section->save();
        }

        return $moodle_course;
    }

    private function manifest_category($course) {
        global $DB;

        $cat_params = array('name' => $course->department);
        $category = $DB->get_record('course_categories', $cat_params);

        if (!$category) {
            $category = new stdClass;

            $category->name = $course->department;
            $category->sortorder = 999;
            $category->parent = 0;
            $category->description = 'Courses under ' . $course->department;
            $category->id = $DB->insert_record('course_categories', $category);
        }

        return $category;
    }

    private function create_user($u) {
        $by_idnumber = array('idnumber' => $u->idnumber);

        $by_username = array('username' => $u->username);

        $exact_params = $by_idnumber + $by_username;

        $user = cps_user::upgrade($u);

        if ($prev = cps_user::get($exact_params, true)) {
            $user->id = $prev->id;
        } else if ($prev = cps_user::get($by_idnumber, true)) {
            $user->id = $prev->id;
        } else if ($prev = cps_user::get($by_username, true)) {
            $user->id = $prev->id;
        } else {
            $user->email = $user->username . $this->setting('user_email');
            $user->confirmed = $this->setting('user_confirm');
            $user->city = $this->setting('user_city');
            $user->country = $this->setting('user_country');
            $user->firstaccess = time();

            $created = true;
        }

        $user->save();

        // TODO: should we fire updated ???
        if (!empty($created)) {
            events_trigger('user_created', $user);
        } else if ($prev and
            (fullname($prev) != fullname($user) and
            $prev->username != $user->username and
            $prev->idnumber != $user->idnumber)) {

            events_trigger('user_updated', $user);
        }

        return $user;
    }

    private function fill_role($type, $section, $users, &$current_users, $extra_params = null) {
        $class = 'cps_' . $type;

        $already_enrolled = array(cps::ENROLLED, cps::PROCESSED);

        foreach ($users as $user) {
            $cps_user = $this->create_user($user);

            $params = array(
                'sectionid' => $section->id,
                'userid' => $cps_user->id
            );

            if ($extra_params) {
                $params += $extra_params($cps_user);
            }

            $cps_type = $class::upgrade($cps_user);

            unset($cps_type->id);

            if ($prev = $class::get($params, true)) {
                $cps_type->id = $prev->id;
                unset($current_users[$prev->id]);

                if (in_array($prev->status, $already_enrolled)) {
                    continue;
                }
            }

            $cps_type->userid = $cps_user->id;
            $cps_type->sectionid = $section->id;
            $cps_type->status = cps::PROCESSED;

            $cps_type->save();

            if (empty($prev) or $prev->status == cps::UNENROLLED) {
                events_trigger($class . '_process', $cps_type);
            }
        }
    }

    private function determine_role($user) {
        if (isset($user->primary_flag)) {
            $role = $user->primary_flag ? 'editingteacher' : 'teacher';
        } else {
            $role = 'student';
        }

        return $role;
    }

    public function log($what) {
        if (!$this->is_silent) {
            mtrace($what);
        }

        $this->emaillog[] = $what;
    }
}

function enrol_cps_supports($feature) {
    switch ($feature) {
        case ENROL_RESTORE_TYPE: return ENROL_RESTORE_EXACT;

        default: return null;
    }
}
