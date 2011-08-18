<?php

abstract class cps_dao {
    /** Private static helper function to maintain calling class static
     * overrides
     */
    private static function with_class($fun) {
        return $fun(get_called_class());
    }

    private static function call($fun, $params = array()) {
        return self::with_class(function ($class) use ($fun, $params) {
            return call_user_func(array($class, $fun), $params);
        });
    }

    /** Public api to interact with cps_dao's */
    public static function get_meta($parentid) {
        global $DB;

        $params = array(self::call('get_name'). 'id' => $parentid);
        $res = $DB->get_records(self::call('metatablename'), $params);

        return $res;
    }

    public static function get_meta_names() {
        global $DB;

        $meta = self::call('metatablename');
        $names = $DB->get_records_sql('SELECT DISTINCT(name) FROM {'.$meta.'}');

        return array_keys($names);
    }

    public static function get(array $params, $meta = false, $fields = '*') {
        return self::with_class(function ($class) use ($params, $meta, $fields) {
            return current($class::get_all($params, $meta, $fields));
        });
    }

    public static function get_select($filters, $meta = false) {
        global $DB;

        $where = is_array($filters) ? implode(' AND ', $filters) : $filters;

        $records = $DB->get_records_select(self::call('tablename'), $where);

        $ret = array();
        foreach ($records as $record) {
            $ret[$record->id] = self::call('upgrade', $record);
        }

        return $ret;
    }

    public static function get_all(array $params = array(), $meta = false, $fields = '*') {
        global $DB;

        $meta_fields = self::call('meta_fields', $params);

        $tablename = self::call('tablename');

        if ($meta_fields) {
            $z_fields = array_map(function($field) { return 'z.' . $field; },
                explode(',', $fields));

            $alpha = range('a', 'x');

            $name = self::call('get_name');

            $tables = array('{'.$tablename.'} z');
            $filters = array('z.id = a.'.self::call('get_name').'id');

            foreach ($meta_fields as $i => $key) {
                $letter = $alpha[$i];
                $tables[] = '{'.self::call('metatablename').'} '.$letter;
                $filters[] = $letter.'.name' . " = '" . $key ."'";
                $filters[] = $letter.'.value' . " = '" . $params[$key] . "'";

                if ($i > 0) {
                    $i --;
                    $prev = $alpha[$i];
                    $filters[] = "{$letter}.{$name}id = {$prev}.{$name}id";
                }
            }

            $sql = "SELECT ". implode(',', $z_fields) . ' FROM ' .
                implode(',', $tables) . ' WHERE ' . implode(' AND ', $filters);

            $res = $DB->get_records_sql($sql);
        } else {
            $res = $DB->get_records($tablename, $params, '', $fields);
        }

        $ret = array();
        foreach ($res as $r) {
            $temp = self::call('upgrade', $r);
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
        return sprintf('enrol_cps_%s', self::call('get_name').'s');
    }

    public static function metatablename() {
        return sprintf('enrol_cps_%smeta', self::call('get_name'));
    }

    public static function upgrade($db_object) {
        return self::with_class(function ($class) use ($db_object) {

            $fields = $db_object ? get_object_vars($db_object) : array();

            // Children can handle their own instantiation
            $self = new $class($fields);

            return $self->fill_params($fields);
        });
    }

    public static function upgrade_and_get($object, array $params) {
        return self::with_class(function ($class) use ($object, $params) {
            $cps = $class::upgrade($object);

            if ($prev = $class::get($params)) {
                $cps->id = $prev->id;
            }

            return $cps;
        });
    }

    public static function delete($id) {
        global $DB;

        $params = array('id' => $id);
        return self::call('delete_all', $params);
    }

    public static function delete_all(array $params = array()) {
        global $DB;

        $to_delete = $DB->get_records(self::call('tablename'), $params);

        if (empty($to_delete)) {
            return true;
        }

        $ids = implode(',', array_keys($to_delete));

        $DB->delete_records_select(self::call('metatablename'),
            self::get_name().'id in ('.$ids.')');

        return $DB->delete_records(self::call('tablename'), $params);
    }

    public static function delete_meta(array $params) {
        global $DB;

        $meta_fields = self::call('meta_fields', $params);

        $query_params = array();
        if ($meta_fields) {
            foreach ($meta_fields as $field) {
                $query_params[$field] = $params[$field];
                unset($params[$field]);
            }
        }

        $to_delete = $DB->get_records(self::call('tablename'), $params);

        foreach ($to_delete as $record) {
            $query_params[self::call('get_name').'id'] = $record->id;
            $DB->delete_records(self::metatablename, $query_params);
        }
    }

    public static function delete_all_meta(array $params = array()) {
        global $DB;

        $to_delete = $DB->get_records(self::call('tablename'), $params);

        $ids = implode(',', array_keys($to_delete));

        return $DB->delete_records_select(self::call('metatablename'), null,
            self::call('get_name').'id in ('.$ids.')');
    }

    /** Instance based ineteraction */
    public function fill_params(array $params = array()) {
        if (!empty($params)) {
            foreach ($params as $field => $value) {
                $this->$field = $value;
            }
        }

        return $this;
    }

    public function fill_meta() {
        $meta = self::call('get_meta', $this->id);

        foreach ($meta as $m) {
            $this->{$m->name} = $m->value;
        }

        return $this;
    }

    public function save() {
        global $DB;

        $tablename = self::call('tablename');

        if (!isset($this->id)) {
            $this->id = $DB->insert_record($tablename, $this, true);
        } else {
            $DB->update_record($tablename, $this);
        }

        $fields = get_object_vars($this);

        $extra = $this->meta_fields($fields);

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

        $dbs = self::call('get_meta', $this->id);

        $metatable = self::call('metatablename');
        $parentref = self::call('get_name');

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

    public static function meta_fields($fields) {

        $name = self::call('get_name');

        return array_filter(array_keys($fields), function ($field) use ($name) {
            if ($field == 'id') return false;

            return preg_match('/^'.$name.'_/', $field);
        });
    }

}
