<?php

abstract class cps_dao {
    abstract public static function required_fields();

    public static function get_meta($parentid) {
        global $DB;

        $params = array(self::get_name().'id' => $parentid);
        $res = $DB->get_records(self::metatablename(), $params);

        return $res;
    }

    public static function get($meta = false, array $params) {
        return current(self::get_all($meta, $params));
    }

    public static function get_all($meta = false, array $params = array()) {
        global $DB;

        $res = $DB->get_records(self::tablename(), $params);

        $ret = array();
        foreach ($res as $r) {
            $temp = self::upgrade($r);
            if ($meta) {
                $temp->fill_meta();
            }

            $ret[$r->id] = $temp;
        }

        return $ret;
    }

    public static function get_name() {
        return end(explode('cps_', get_called_class()));
    }

    public static function tablename() {
        return sprintf('enrol_cps_%s', self::get_name().'s');
    }

    public static function metatablename() {
        return sprintf('enrol_cps_%smeta', self::get_name());
    }

    public static function upgrade($db_object) {
        $calling = get_called_class();

        $fields = $db_object ? get_object_vars($db_object) : array();

        // Children can handle their own instantiation
        $self = new $calling($fields);

        return $self->fill_params($fields);
    }

    public static function delete($id) {
        global $DB;

        $params = array(self::get_name().'id' => $id);
        $DB->delete_records(self::metatablename(), $params);

        return $DB->delete_records(self::tablename(), array('id' => $id));
    }

    public static function delete_all(array $params = array()) {
        global $DB;

        $to_delete = $DB->get_records(self::tablename(), $params);

        if (empty($to_delete)) {
            return true;
        }

        $ids = implode(',', array_keys($to_delete));

        $DB->delete_records_select(self::metatablename(),
            self::get_name().'id in ('.$ids.')');

        return $DB->delete_records(self::tablename(), $params);
    }

    public function fill_params(array $params = array()) {
        if (!empty($params)) {
            foreach ($params as $field => $value) {
                $this->$field = $value;
            }
        }

        return $this;
    }

    public function fill_meta() {
        $meta = self::get_meta($this->id);

        foreach ($meta as $m) {
            $this->{$m->name} = $m->value;
        }
    }

    public function save() {
        global $DB;

        $db_object = new stdClass;

        $required = $this->required_fields();
        foreach ($required as $key) {
            if (isset($this->$key)) {
                $db_object->$key = $this->$key;
            }
        }

        if (!isset($this->id)) {
            $this->id = $DB->insert_record(self::tablename(), $db_object, true);
        } else {
            $db_object->id = $this->id;
            $DB->update_record(self::tablename(), $db_object);
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
        return true;
    }

    private function save_meta($meta) {
        global $DB;

        $dbs = self::get_meta($this->id);

        $metatable = self::metatablename();
        $parentref = self::get_name();

        // Update Pre-existing changes
        foreach ($dbs as $db) {
            if (isset($meta[$db->name])) {
                $db->value = $meta[$db->name];

                $DB->update_record($metatable, $db);

                unset($meta[$db->name]);
            }
        }

        // Persist other changes
        foreach ($meta as $name => $value) {
            $m = new stdClass;

            $m->{$parentref. 'id'} = $this->id;
            $m->name = $name;
            $m->value = $value;

            $m->id = $DB->insert_record($metatable, $m, true);

            $dbs[$m->id] = $m;
        }

        foreach ($dbs as $db) {
            $this->{$db->name} = $db->value;
        }
    }
}
