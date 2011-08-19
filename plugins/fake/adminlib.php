<?php

class admin_setting_link extends admin_setting {
    function __construct($name, $visiblename, $description, $url) {
        $this->url = new moodle_url($url);
        parent::__construct($name, $visiblename, $description, '');
    }

    public function write_setting($data) {
        return '';
    }

    public function get_setting() {
        return NULL;
    }

    function output_html($data, $query='') {
        $link = html_writer::link($this->url, $this->visiblename);
        return format_admin_setting($this, $this->visiblename, $link, $this->description, true);
    }
}
