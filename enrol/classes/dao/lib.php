<?php

interface meta_information {
    public function save_meta($meta);

    public function fill_meta();

    public static function meta_fields($fields);

    public static function get_meta($parentid);

    public static function get_meta_names();

    public static function metatablename();

    public static function delete_meta(array $params);

    public static function delete_all_meta(array $params);
}

abstract class cps_dao extends cps_base implements meta_information {

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
        return self::get_select_internal($filters, function ($object) use ($meta) {
            if ($meta) {
                $object->fill_meta();
            }
            return $object;
        });
    }

    public static function get_all(array $params = array(), $meta = false, $fields = '*') {
        global $DB;

        $meta_fields = self::call('meta_fields', $params);

        $tablename = self::call('tablename');

        $handler = function ($object) use ($meta) {
            if ($meta) {
                $object->fill_meta();
            }
            return $object;
        };

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
            return self::get_all_internal($params, $fields, $handler);
        }

        $ret = array();
        foreach ($res as $r) {
            $temp = self::call('upgrade', $r);
            $ret[$r->id] = $handler($temp);
        }

        return $ret;
    }

    public static function metatablename() {
        return sprintf('enrol_cps_%smeta', self::call('get_name'));
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
        self::delete_meta(array(self::call('get_name').'id' => $id));

        return parent::delete($id);
    }

    public static function delete_all(array $params = array()) {

        $metatable = self::call('metatablename');
        $name = self::call('get_name');

        $handler = function ($tablename) use ($params, $metatable, $name) {
            global $DB;

            $to_delete = $DB->get_records($tablename, $params);

            $ids = implode(',', array_keys($to_delete));

            $DB->delete_records_select($metatable, $name.'id in ('.$ids.')');
        };

        return self::delete_all_internal($params, $handler);
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

        $to_delete = $DB->get_records(self::call('metatablename'), $params);

        foreach ($to_delete as $record) {
            $query_params[self::call('get_name').'id'] = $record->id;
            $DB->delete_records(self::call('metatablename'), $query_params);
        }
    }

    public static function delete_all_meta(array $params = array()) {
        global $DB;

        $to_delete = $DB->get_records(self::call('tablename'), $params);

        $ids = implode(',', array_keys($to_delete));

        return $DB->delete_records_select(self::call('metatablename'), null,
            self::call('get_name').'id in ('.$ids.')');
    }

    public function fill_meta() {
        $meta = self::call('get_meta', $this->id);

        foreach ($meta as $m) {
            $this->{$m->name} = $m->value;
        }

        return $this;
    }

    public function save() {

        $saved = parent::save();

        $fields = get_object_vars($this);

        $extra = $this->meta_fields($fields);

        if (empty($extra)) {
            return $saved;
        }

        $fun = function ($e) use ($fields) { return $fields[$e]; };

        $meta = array_combine($extra, array_map($fun, $extra));

        $this->save_meta($meta);

        return $saved;
    }

    public function save_meta($meta) {
        global $DB;

        $dbs = self::call('get_meta', $this->id);

        $metatable = self::call('metatablename');
        $parentref = self::call('get_name');

        // Update Pre-existing changes
        foreach ($dbs as $db) {
            // Exists and changed, then write
            if (isset($meta[$db->name]) and $db->value != $meta[$db->name]) {
                $db->value = $meta[$db->name];

                $DB->update_record($metatable, $db);
            }

            unset($meta[$db->name]);
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
    }

    public static function meta_fields($fields) {

        $name = self::call('get_name');

        return array_filter(array_keys($fields), function ($field) use ($name) {
            if ($field == 'id') return false;

            return preg_match('/^'.$name.'_/', $field);
        });
    }

}
