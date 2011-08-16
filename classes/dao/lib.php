<?php

abstract class cps_dao {
    public function required_fields();

    public static function get_meta($parentid) {
        global $DB;

        $params = array(self::get_name().'id' => $parentid);
        $res = $DB->get_records(self::metatablename(), $params);

        return $res;
    }

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

    public static function get_name() {
        return current(explode('cps_', get_called_class()));
    }

    public static function tablename() {
        return sprintf('enrol_cps_%s', self::get_name().'s');
    }

    public static function metatablename() {
        return sprintf('enrol_cps_%smeta'. self::get_name());
    }

    public static function upgrade($db_object) {
        $calling = get_called_class();

        $fields = $db_object ? get_object_vars($db_object) : array();

        return new $calling($fields);
    }

    public function save() {
        $db_object = new stdClass;

        $required = $this->required_fields();
        foreach ($required as $key) {
            $db_object->$key = $this->$key;
        }

        if ($this->id) {
            $db_object->id = $this->id;
            update_record(self::tablename(), $db_object);
        } else {
            $this->id = inser_record(self::tablename(), $db_object, true);
        }

        $fields = get_object_vars($this);
        unset($fields['id']);

        $extra = array_diff(array_keys($fields), $required);

        if (empty($extra)) {
            return true;
        }

        $fun = function ($e) use ($fields) { return $fields[$e]; };

        $meta = array_combine($extra, array_map($fun, $extra));

        $this->save_meta($meta);
    }

    private function save_meta($meta) {
        $dbs = self::get_meta($this->id);

        $metatable = self::metatablename();
        $parentref = self::get_name();

        // Update Pre-existing changes
        foreach ($dbs as $db) {
            if (isset($meta[$db->name])) {
                $db->value = $meta[$db->name];

                update_record($metatable, $db);

                unset($meta[$db->name]);
            }
        }

        // Persist other changes
        foreach ($meta as $name => $value) {
            $m = new stdClass;

            $m->{$parentref. 'id'} = $this->id;
            $m->name = $name;
            $m->value = $value;

            $m->id = insert_record($metatable, $m, true);

            $dbs[$m->id] = $m;
        }

        foreach ($dbs as $db) {
            $this->{$db->name} = $db->value;
        }
    }
}
