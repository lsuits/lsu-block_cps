<?php

require_once dirname(__FILE__) . '/lib.php';

class cps_semester extends cps_dao {

    public static function required_fields() {
        return array('year', 'name', 'campus', 'classes_start', 'grades_due');
    }

}
