<?php

require_once $CFG->dirroot . '/enrol/cps/publiclib.php';
cps::require_daos();

abstract class cps_preferences extends cps_base {
    public static function settings() {
        $settings = array('creation', 'split', 'crosslist',
            'team_request', 'material', 'unwant');

        $remaining_settings = array();

        foreach ($settings as $setting) {
            $class = 'cps_' . $setting;

            if (!$class::is_enabled()) {
                continue;
            }

            $remaining_settings[$setting] = $class::name();
        }

        return $remaining_settings;
    }

    public static function get_all(array $params = array(), $fields = '*') {
        return self::get_all_internal($params, $fields);
    }

    public static function get(array $params, $fields = '*') {
        return current(self::get_all($params, $fields));
    }

    public static function get_select($filters) {
        return self::get_select_internal($filters);
    }

    public static function delete_all(array $params = array()) {
        return self::delete_all_internal($params);
    }

    public static function is_enabled() {
        $setting = self::call('get_name');

        return get_config('block_cps', $setting);
    }

    public static function name() {
        return get_string(self::call('get_name'), 'block_cps');
    }

}

interface immediate_application {
    function apply();
}

// Begin Concrete classes
class cps_unwant extends cps_preferences {
    public function active_sections_for($teacher, $is_primary = true) {
        $sections = $teacher->sections($is_primary);

        $unwants = cps_unwant::get_all(array('userid' => $teacher->userid));

        foreach ($unwants as $unwant) {
            if (isset($sections[$unwant->sectionid])) {
                unset($sections[$unwant->sectionid]);
            }
        }

        return $sections;
    }
}

class cps_material extends cps_preferences {
}

class cps_creation extends cps_preferences {
}

class cps_setting extends cps_preferences {
}

class cps_split extends cps_preferences {
    public static function filter_valid($courses) {
        return array_filter($courses, function ($course) {
            return count($course->sections) > 1;
        });
    }

    public static function in_course($course) {
        global $USER;

        if (empty($course->sections)) {
            $course->sections = array();

            $teacher = cps_teacher::get(array('id' => $USER->id));

            $sections = cps_unwant::active_sections_for($teacher, true);

            foreach ($sections as $section) {
                $course->sections[$section->id] = $section;
            }
        }

        $course_section_ids = implode(',', array_keys($course->sections));

        $split_filters = array(
            'userid' => $USER->id,
            'sectionid IN (' . $course_section_ids . ')'
        );

        $splits = self::get_select($split_filters);

        return $splits;
    }

    public static function exists($course) {
        return self::in_course($course) ? true : false;
    }

    public static function groups($splits) {
        if (empty($splits)) {
            return 0;
        }

        return array_reduce($splits, function ($in, $split) {
            return $split->groupingid > $in ? $split->groupingid : $in;
        });
    }
}

class cps_crosslist extends cps_preferences {
    public static function in_courses($courses) {
        global $USER;

        // Flatten sections
        $course_to_sectionids = function ($course) {
            return implode(',', array_keys($course->sections));
        };

        $sectionids = implode(',', array_map($course_to_sectionids, $courses));

        $crosslist_params = array(
            'userid = ' . $USER->id,
            'sectionid IN (' . $sectionids . ')'
        );

        $crosslists = self::get_select($crosslist_params);

        return $crosslists;
    }
}

class cps_team_request extends cps_preferences {
}

class cps_team_section extends cps_preferences {
}
