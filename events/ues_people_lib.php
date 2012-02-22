<?php

require_once $CFG->dirroot . '/blocks/ues_people/lib.php';

class cps_people_element extends ues_people_element_output {
    public function format($user) {
        switch ($this->field) {
            case 'user_reg_status':
                return date('d/M/Y', $user->{$this->field});
            case 'user_degree':
            case 'user_ferpa':
                return !empty($user->{$this->field}) ? 'Y' : 'N';
            default:
                return parent::format($user);
        }
    }
}
