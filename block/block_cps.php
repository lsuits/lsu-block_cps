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

        $content = new stdClass;

        $content->items = array();
        $content->icons = array();
        $content->footer = '';

        $content->items[] = 'Testing';

        $this->content = $content;
        return $content;
    }
}
