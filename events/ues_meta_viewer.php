<?php

require_once $CFG->dirroot . '/blocks/cps/events/lib.php';

abstract class cps_ues_meta_viewer_handler {
    public static function ues_user_data_ui_keys($fields) {
        // Remove unecessary sport codes
        $sports = array();

        foreach (range(2, 4) as $code_num) {
            $sports[] = "user_sport$code_num";
        }

        $not_sports = function ($key) use ($sports) {
            return !in_array($key, $sports);
        };

        $fields->keys = array_filter($fields->keys, $not_sports);

        return true;
    }

    public static function ues_user_data_ui_element($handler) {

        // Play nice, only handle what I need
        $handled = array(
            'username',
            'user_ferpa',
            'user_reg_status',
            'user_degree',
            'user_year',
            'user_major',
            'user_college',
            'user_keypadid',
            'user_sport1',
            'user_anonymous_number'
        );

        if (in_array($handler->ui_element->key(), $handled)) {
            $field = $handler->ui_element->key();

            $name = get_string($field, 'block_cps');

            $handler->ui_element = new cps_meta_ui_element($field, $name);
        }

        return true;
    }
}
