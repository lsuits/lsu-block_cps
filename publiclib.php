<?php

defined('MOODLE_INTERNAL') or die();

abstract class cps {
    public static function require_libs() {
        self::require_daos();
        self::require_extensions();
    }

    public static function require_daos() {
        $dao = self::base('classes/dao');

        require_once $dao . '/lib.php';
        require_once $dao . '/daos.php';

    }

    public static function require_extensions() {
        $classes = self::base('classes');

        require_once $classes . '/processors.php';
        require_once $classes . '/provider.php';
    }

    public static function reprocess_course($course) {
        $sections = cps_section::from_course($course, true);

        return self::reprocess_sections($sections);
    }

    public static function reprocess_sections($sections) {
        $enrol = enrol_get_plugin('cps');

        if (!$enrol or $enrol->errors) {
            return false;
        }

        foreach ($sections as $section) {
            $section->status = $enrol::PENDING;
            $section->save();

            $enrol->process_enrollment(
                $section->semester(), $section->course(), $section
            );
        }

        $enrol->handle_enrollments();

        return true;
    }

    public static function reprocess_for($teacher) {
        $cps_user = $teacher->user();

        $provider = self::create_provider();

        if ($provider and $provider->supports_reverse_lookups()) {
            $enrol = enrol_get_plugin('cps');

            $info = $provider->teacher_info_source();

            $semesters = cps_semester::in_session();

            foreach ($semesters as $semester) {
                $courses = $info->teacher_info($semester, $cps_user);

                $processed = $enrol->process_courses($semester, $courses);

                foreach ($processed as $course) {

                    foreach ($course->sections as $section) {
                        $enrol->process_enrollment(
                            $semester, $course, $section
                        );
                    }
                }
            }

            $enrol->handle_enrollments();
            return true;
        }

        return self::reprocess_sections($teacher->sections());
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
            return cps::_s("{$name}_name");
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
        self::require_libs();

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
