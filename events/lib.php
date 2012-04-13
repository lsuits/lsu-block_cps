<?php

require_once $CFG->dirroot . '/blocks/ues_meta_viewer/classes/lib.php';

class cps_meta_ui_element extends meta_data_text_box {
    public function format($user) {
        switch ($this->key()) {
            case 'username':
                $url = new moodle_url('/user/profile.php', array('id' => $user->id));
                return html_writer::link($url, $user->username);
            case 'user_degree':
            case 'user_ferpa': return $this->format_bool($user);
            case 'user_reg_status': return $this->format_date($user);
            default: return parent::format($user);
        }
    }

    private function format_bool($user) {
        $field = $this->key();

        if (isset($user->{$field})) {
            return $user->{$field} == 1 ? 'Y' : 'N';
        }

        return parent::format($user);
    }

    private function format_date($user) {
        $pattern = 'm-d-Y';

        $field = $this->key();

        return isset($user->{$field}) ?
            date($pattern, $user->$field) : parent::format($user);
    }
}
