<?php

class block_cps extends block_list {
    function init() {
        $this->title= get_string('pluginname', 'block_cps');
    }

    function applicable_formats() {
        return array('site' => true, 'my' => true, 'course' => false);
    }

    function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }

        global $CFG;

        require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

        if (!ues_user::is_teacher()) {
            return $this->content;
        }

        $sections = ues_user::sections(true);
        $courses = ues_course::merge_sections($sections);

        $content = new stdClass;

        $content->items = array();
        $content->icons = array();
        $content->footer = '';

        $preferences = cps_preferences::settings();

        foreach ($preferences as $setting => $name) {
            $url = new moodle_url("/blocks/cps/$setting.php");

            $obj = 'cps_' . $setting;

            if (method_exists($obj, 'is_valid') and !$obj::is_valid($courses)) {
                continue;
            }

            $content->items[] = html_writer::link($url, $name);
        }

        $this->content = $content;
        return $content;
    }
}
