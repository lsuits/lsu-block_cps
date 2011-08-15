<?php

abstract class cps_dao {
    protected function required_fields();

    public static function get(array $params) {
        global $DB;

        $res = $DB->get_record(self::tablename(), $params);

        return self::upgrade($res);
    }

    public static function get_all(array $params) {
        global $DB;

        $res = $DB->get_records(self::tablename(), $params);

        $fun = function($r) use ($this) { return $this::upgrade($r); };

        return array_map($fun, $res);
    }

    public static function tablename() {
        $single = current(explode('cps_', get_called_class()));
        return sprintf('enrol_cps_%s', $single.'s');
    }

    public static function upgrade($db_object) {
        $calling = get_called_class();

        $fields = $db_object ? get_object_vars($db_object) : array();

        return new $calling($fields);
    }

    public function save() {
        $fields = get_object_vars($this);

        $db_object = new stdClass;
        foreach ($this->required_fields() as $key => $value) {
            $db_object->$key = $value;
        }

        if ($fields['id']) {
            $db_object->id = $fields['id'];
            update_record(self::tablename(), $db_object);
        } else {
            inser_record(self::tablename(), $db_object);
        }
    }
}
