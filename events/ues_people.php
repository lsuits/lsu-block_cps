<?php

require_once $CFG->dirroot . '/blocks/cps/events/ues_people_lib.php';

abstract class cps_ues_people_handler {
    public static function ues_people_outputs($data) {
        $interfere = array(
            'user_ferpa', 'user_college', 'user_year', 'user_reg_status',
            'user_keypadid', 'user_major', 'user_degree', 'user_sport1',
            'user_anonymous_number'
        );

        $_s = ues::gen_str('block_cps');

        foreach ($interfere as $meta) {
            if (!isset($data->outputs[$meta])) {
                continue;
            }
            $data->outputs[$meta] = new cps_people_element($meta, $_s($meta));
        }

        return true;
    }
}
