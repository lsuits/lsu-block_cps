<?php

require_once dirname(__FILE__) . '/lib.php';

class cps_semester extends cps_dao {
    protected function required_fields() {
        return array('year', 'name', 'campus', 'class_start', 'grades_due');
    }

    function __construct($params) {
        foreach ($params as $key => $value) {
            $this->$key = $value;
        }
    }
}
