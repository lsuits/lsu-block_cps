<?php

require_once $CFG->dirroot . '/blocks/cps/events/lib.php';

abstract class cps_ues_meta_viewer_handler {
    public static function ues_user_data_ui_element($handler) {

        // Play nice, only handle what I need
        $handled = array(
            'user_ferpa',
            'user_reg_status',
            'user_degree',
            'user_year',
            'user_major',
            'user_college',
            'user_keypadid'
        );

        if (in_array($handler->ui_element->key(), $handled)) {
            $field = $handler->ui_element->key();

            $name = get_string($field, 'block_cps');

            $handler->ui_element = new cps_meta_ui_element($field, $name);
        }

        return true;
    }
}
