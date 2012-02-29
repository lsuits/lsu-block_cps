<?php

require_once $CFG->dirroot . '/blocks/ues_people/lib.php';

class cps_people_element extends ues_people_element_output {
    public function format($user) {
        switch ($this->field) {
            case 'user_degree':
            case 'user_ferpa':
                return !empty($user->{$this->field}) ? 'Y' : 'N';
            case 'user_reg_status':
                return isset($user->{$this->field}) ?
                    date('m-d-Y', $user->{$this->field}) : '';
            default:
                return parent::format($user);
        }
    }
}
