<?php

cps::require_daos();

abstract class cps_preferences extends cps_base {
    public static function get_all(array $params = array(), $fields = '*') {
        return self::get_all_internal($params, $fields);
    }

    public static function get(array $params, $fields = '*') {
        return self::get_all($params, $fields);
    }

    public static function get_select($filters) {
        return self::get_select_internal($params, $fields);
    }

    public static function delete_all(array $params = array()) {
        return self::delete_all_internal($params);
    }
}

// Begin Concrete classes
class cps_unwant extends cps_preferences {
}

class cps_material extends cps_preferences {
}

class cps_creation extends cps_preferences {
}

class cps_setting extends cps_preferences {
}

class cps_split extends cps_preferences {
}

class cps_crosslist extends cps_preferences {
}

class cps_team_request extends cps_preferences {
}

class cps_team_section extends cps_preferences {
}
