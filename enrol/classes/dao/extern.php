<?php

abstract class cps_external extends cps_base {
    public static function get_all(array $params = array(), $fields = '*') {
        return self::get_all_internal($params, $fields);
    }

    public static function get(array $params, $fields = '*') {
        return current(self::get_all($params, $fields));
    }

    public static function get_select($filters) {
        return self::get_select_internal($filters);
    }

    public static function delete_all(array $params = array()) {
        return self::delete_all_internal($params);
    }
}
