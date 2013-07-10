<?php

class block_cps extends block_list {
    function init() {
        $this->title= get_string('pluginname', 'block_cps');
    }

    function applicable_formats() {
        return array('site' => true, 'my' => true, 'course' => false);
    }
    
    function has_config() {
        return true;
    }

    function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }

        global $CFG, $OUTPUT, $USER;

        require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

        $sections = ues_user::sections(true);
        $semesters = ues_semester::merge_sections($sections);

        $content = new stdClass;

        $content->items = array();
        $content->icons = array();
        $content->footer = '';

        $preferences = cps_preferences::settings();

        foreach ($preferences as $setting => $name) {
            $url = new moodle_url("/blocks/cps/$setting.php");

            $obj = 'cps_' . $setting;

            if (!$obj::is_valid($semesters)) {
                continue;
            }

            $content->items[] = html_writer::link($url, $name) .
                $OUTPUT->help_icon($setting, 'block_cps');
            $content->icons[] = $OUTPUT->pix_icon($setting, $name,
                'block_cps', array('class' => 'icon'));
        }

        $this->content = $content;
        return $content;
    }
}
