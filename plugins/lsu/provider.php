<?php

require_once dirname(__FILE__) . '/processors.php';

class lsu_enrollment_provider extends enrollment_provider {
    var $url;
    var $wsdl;
    var $username;
    var $password;

    function init() {
        global $CFG;

        $path = pathinfo($this->wsdl);

        // Path checks
        if (!file_exists($this->wsdl)) {
            throw new Exception('no_file');
        }

        if ($path['extension'] != 'wsdl') {
            throw new Exception('bad_file');
        }

        if (!preg_match('/^[http|https]/', $this->url)) {
            throw new Exception('bad_url');
        }

        require_once $CFG->libdir . '/filelib.php';

        $curl = new curl;
        $curl->request($this->url);

        $resp = $curl->getResponse();

        list($username, $password) = explode("\n", $resp);

        if (empty($username) or empty($password)) {
            throw new Exception('bad_resp');
        }

        $this->username = $username;
        $this->password = $password;
    }

    function __construct($init_on_create = true) {
        global $CFG;

        $this->url = $this->get_setting('credential_location');

        $this->wsdl = $CFG->dataroot . '/'. $this->get_setting('wsdl_location');

        if ($init_on_create) {
            $this->init();
        }
    }

    public static function settings() {
        return array(
            'credential_location' => 'https://secure.web.lsu.edu/credentials.php',
            'wsdl_location' => 'webService.wsdl'
        );
    }

    function semester_source() {
        return new lsu_semesters();
    }

    function course_source() {
        return new lsu_courses();
    }

    function teacher_source() {
        return new lsu_teachers();
    }

    function student_source() {
        return new lsu_students();
    }

    function postprocess() {
        // Get dynamic semesters, eventually
        $semesters = array();

        $source = new lsu_student_data();
        foreach ($semesters as $semester) {
            $datas = $source->student_data($semester->year, $semester->name);

            foreach ($datas as $data) {
                // Update each student record
            }
        }
    }
}
