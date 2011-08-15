<?php

defined('MOODLE_INTERNAL') or die();

class enrol_cps_plugin extends enrol_plugin {

    /** Typical errolog for cron run */
    var $errors = array();

    var $provider;

    function __construct() {
        try {
            $this->provider = self::create_provider();
        } catch (Exception $e) {
            $a = self::translate_error($e);

            $this->errors[] = self::_s('provider_cron_problem', $a);
        }
    }

    public function is_cron_required() {
        //TODO: Make sure we first start at 2:30 or 3:00 AM
        /**
         * $now = (int)date('H');
         * if ($now >= 2 and $now <= 3) {
         *     return parent::is_cron_required();
         * }
         *
         * return false;
         */
        return parent::is_cron_required();
    }

    public function cron() {
        if ($this->provider) {
            $this->full_process();
        }

        if (!empty($this->errors)) {
            //TODO: report errors
        }
    }

    public function full_process() {
        $now = strftime('%Y-%m-%d', time());

        $this->provider->preprocess();

        $semesters = $this->provider->semester_source()->semesters($now);

        foreach ($semesters as $semester) {
            // insert or update record

            $course_source = $this->provider->course_source();

            $courses = $course_source->courses($semester->year, $semester->name, $semester->campus);

            foreach ($courses as $course) {
                $this->process_course_enrollment($course->course_number,
                    $course->department, $course->section_number, $semester->year,
                    $semester->name);
            }
        }

        $this->provider->postprocess();
        return true;
    }

    /**
     * Could be used to process a single course upon request
     */
    public function process_course_enrollment($course_nbr, $course_dept, $section_nbr, $year, $name) {
        $teacher_source = $this->provider->teacher_source();

        $teachers = $teacher_source->teachers($course_nbr, $course_dept, $section_nbr, $year, $name);

        foreach ($teachers as $teacher) {
            // Insert or update teacher users here
        }

        $student_source = $this->provider->student_source();

        $students = $student_source->students($course_nbr, $course_dept, $section_nbr, $year, $name);

        foreach ($students as $student) {
            // Insert or update student users here
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
        return self::cps_base('plugins');
    }

    public static function cps_base($dir='') {
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
            return enrol_cps_plugin::_s("{$name}_name");
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
        $lib_base = self::cps_base('classes');
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
