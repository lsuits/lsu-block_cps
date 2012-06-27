<?php

require_once $CFG->dirroot . '/blocks/ues_people/lib.php';

class cps_people_element extends ues_people_element_output {
    private function span_yes() {
        $class = end(explode('_', $this->field));
        return html_writer::tag('span', 'Y', array('class' => "$class yes"));
    }

    public function format($user) {
        switch ($this->field) {
            case 'user_ferpa':
            case 'user_degree':
                return !empty($user->{$this->field}) ? $this->span_yes() : 'N';
            case 'user_reg_status':
                return isset($user->{$this->field}) ?
                    date('m-d-Y', $user->{$this->field}) : '';
            default:
                return parent::format($user);
        }
    }
}
