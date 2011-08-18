<?php

defined('MOODLE_INTERNAL') or die();

class cps_enrollment {
    /** Typical errolog for cron run */
    var $errors = array();

    var $provider;

    function __construct() {
        try {
            $this->provider = self::create_provider();

            $lib = self::base('classes/dao');

            require_once $lib . '/lib.php';
            require_once $lib . '/daos.php';

        } catch (Exception $e) {
            $a = self::translate_error($e);

            $this->errors[] = self::_s('provider_cron_problem', $a);
        }
    }

    public function full_process() {

        $now = strftime('%Y-%m-%d', time());

        $this->provider->preprocess();

        $semesters = $this->provider->semester_source()->semesters($now);

        $processed_semesters = $this->process_semesters($semesters);

        foreach ($processed_semesters as $semester) {

            $courses = $this->provider->course_source()->courses($semester);

            $process_courses = $this->process_courses($semester, $courses);

            foreach ($process_courses as $course) {
                $this->process_course_enrollment($semester, $course);
            }
        }

        $this->provider->postprocess();
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

                $cps->save();

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
                    'cou_number' => $course->number
                );

                $cps_course = cps_course::upgrade_and_get($course, $params);

                $cps_course->save();

                $processed_sections = array();
                foreach ($cps_course->sections as $section) {
                    $params = array(
                        'courseid' => $cps_course->id,
                        'semesterid' => $semester->id,
                        'sec_number' => $section->sec_number
                    );

                    $cps_section = cps_section::upgrade_and_get($section, $params);

                    $cps_section->status = 'processed';

                    $cps_section->save();

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
    public function process_course_enrollment($semester, $course) {
        $teacher_source = $this->provider->teacher_source();

        $student_source = $this->provider->student_source();

        foreach ($course->sections as $section) {
            $teachers = $teacher_source->teachers($semester, $course, $section);

            $students = $student_source->students($semester, $course, $section);

            try {
                $this->process_teachers($teachers);

                $this->process_students($students);
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
    }

    public function process_teachers($section, $users) {
        return $this->fill_role('teacher', $section, $users, function($user) {
            return array('primary_flag' => $user->primary_flag);
        });
    }

    public function process_students($section, $users) {
        return $this->fill_role('student', $section, $users);
    }

    public function create_user($u) {
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
            $user->email = $user->username . $this->settting('user_email');
            $user->confirmed = $this->settting('user_confirm');
            $user->city = $this->setting('user_city');
            $user->country = $this->setting('user_country');
            $user->firstaccess = time();
        }

        $user->save();

        return $user;
    }

    public function setting($key) {
        return get_config('enrol_cps', $key);
    }

    private function fill_role($type, $section, $users, $extra_params = null) {
        $class = 'cps_'.$type;

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
            }

            $cps_type->status = 'enroll';

            $cps_type->save();
        }
    }

    public static function gen_str() {
        return function ($key, $a=null) {
            return get_string($key, 'enrol_cps', $a);
        };
    }

    public static function _s($key, $a=null) {
        return get_string($key, 'enrol_cps', $a);
    }

    public static function plugin_base() {
        return self::base('plugins');
    }

    public static function base($dir='') {
        return dirname(__FILE__) . (empty($dir) ? '' : '/'.$dir);
    }

    public static function list_plugins() {

        $base = self::plugin_base();

        $all_files_folders = scandir($base);

        $plugins = array_filter($all_files_folders, function ($file) use ($base) {
            return is_dir($base . '/' . $file) and !preg_match('/^\./', $file);
        });

        if (empty($plugins)) {
            return array();
        }

        $provide_append = function ($name) {
            return cps_enrollment::_s("{$name}_name");
        };

        return array_combine($plugins, array_map($provide_append, $plugins));
    }

    public static function provider_class() {
        $provider_name = get_config('enrol_cps', 'enrollment_provider');

        if (!$provider_name) {
            return false;
        }

        $class_file = self::plugin_base() . '/' . $provider_name . '/provider.php';

        if (!file_exists($class_file)) {
            return false;
        }

        // Require library code
        $lib_base = self::base('classes');
        require_once $lib_base . '/provider.php';
        require_once $lib_base . '/processors.php';

        // Require client code
        require_once $class_file;

        $provider_class = "{$provider_name}_enrollment_provider";

        return $provider_class;
    }

    public static function create_provider() {
        $provider_class = self::provider_class();

        return $provider_class ? new $provider_class() : false;
    }

    public static function translate_error($e) {
        $provider_class = self::provider_class();
        $provider_name = $provider_class::get_name();

        $problem = self::_s($provider_name . '_' . $e->getMessage());

        $a = new stdClass;
        $a->pluginname = self::_s($provider_name.'_name');
        $a->problem = $problem;

        return $a;
    }
}
