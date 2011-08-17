<?php

defined('MOODLE_INTERNAL') or die();

require_once dirname(__FILE__) . '/cps_enrollment.class.php';

class enrol_cps_plugin extends enrol_plugin {
    var $enrollment;

    function __construct() {
        $this->enrollment = new cps_enrollment();
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

        if ($this->enrollment->provider) {
            $this->enrollment->full_process();
        }

        if (!empty($this->enrollment->errors)) {
            //TODO: report errors
        }
    }

}
