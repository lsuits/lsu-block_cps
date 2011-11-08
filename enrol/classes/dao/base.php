<?php

abstract class cps_base {
    /** Protected static helper function to maintain calling class static
     * overrides
     */
    protected static function with_class($fun) {
        return $fun(get_called_class());
    }

    protected static function call($fun, $params = array()) {
        return self::with_class(function ($class) use ($fun, $params) {
            return call_user_func(array($class, $fun), $params);
        });
    }

    protected static function get_internal(array $params, $fields = '*', $trans = null) {
        return current(self::get_all_internal($params, $fields, $trans));
    }

    protected static function get_all_internal(array $params, $fields = '*', $trans = null) {
        global $DB;

        $res = $DB->get_records(self::call('tablename'), $params, '', $fields);

        $ret = array();
        foreach ($res as $r) {
            $temp = self::call('upgrade', $r);

            $ret[$r->id] = $trans ? $trans($temp) : $temp;
        }

        return $ret;
    }

    protected static function get_select_internal($filters, $trans = null) {
        global $DB;

        $where = is_array($filters) ? implode(' AND ', $filters) : $filters;

        $records = $DB->get_records_select(self::call('tablename'), $where);

        $ret = array();
        foreach ($records as $record) {
            $transformed = self::call('upgrade', $record);

            $ret[$record->id] = $trans ? $trans($transformed) : $transformed;
        }

        return $ret;
    }

    protected static function delete_all_internal(array $params, $trans = null) {
        global $DB;

        $to_delete = $DB->count_records(self::call('tablename'), $params);

        if ($trans and $to_delete) {
            $trans(self::call('tablename'));
        }

        return $DB->delete_records(self::call('tablename'), $params);
    }

    public static function count(array $params = array()) {
        global $DB;

        return $DB->count_records(self::call('tablename'), $params);
    }

    public static function count_select($filters = array()) {
        global $DB;

        $where = is_array($filters) ? implode(' AND ', $filters) : $filters;

        return $DB->count_records_select(self::call('tablename'), $where);
    }

    public static function update(array $fields, array $params = array()) {
        global $DB;

        list($map, $trans) = self::update_helpers();

        list($set_params, $set_keys) = $trans('set', $fields);

        $set = implode(' ,', $set_keys);

        $sql = 'UPDATE {' . self::call('tablename') .'} SET ' . $set;

        if ($params) {
            $where_keys = array_keys($params);
            $where_params = array_map($map, $where_keys, $where_keys);

            $where = implode(' AND ', $where_params);

            $sql .= ' WHERE ' . $where;
        }

        return $DB->execute($sql, $set_params + $params);
    }

    private static function update_helpers() {
        $map = function ($key, $field) { return "$key = :$field"; };

        $trans = function ($new_key, $fields) use ($map) {
            $oldkeys = array_keys($fields);

            $newnames = function ($field) use ($new_key) {
                return "{$new_key}_{$field}";
            };

            $newkeys = array_map($newnames, $oldkeys);

            $params = array_map($map, $oldkeys, $newkeys);

            $new_params = array_combine($newkeys, array_values($fields));
            return array($new_params, $params);
        };

        return array($map, $trans);
    }

    public static function update_select($values, $filters = null, $tables = null) {
        global $DB;

        list($map, $trans) = self::update_helpers();

        $sql = 'UPDATE {' . self::call('tablename') . '}';

        if ($tables) {
            $sql .= ' this, ' . implode(', ', $tables);
        }

        list($set_params, $set_keys) = $trans('set', $values);
        $set = implode(' ,', $set_keys);

        $sql .= ' SET ' . $set;

        if ($filters) {
            $sql .= ' WHERE ' . implode(' AND ', $filters);
        }

        return $DB->execute($sql, $set_params);
    }

    public static function get_name() {
        return end(explode('cps_', get_called_class()));
    }

    public static function tablename() {
        return sprintf('enrol_cps_%s', self::call('get_name').'s');
    }

    public static function upgrade($db_object) {
        return self::with_class(function ($class) use ($db_object) {

            $fields = $db_object ? get_object_vars($db_object) : array();

            // Children can handle their own instantiation
            $self = new $class($fields);

            return $self->fill_params($fields);
        });
    }

    /** Instance based interaction */
    public function fill_params(array $params = array()) {
        if (!empty($params)) {
            foreach ($params as $field => $value) {
                $this->$field = $value;
            }
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

        return true;
    }

    public static function delete($id) {
        global $DB;

        return $DB->delete_records(self::call('tablename'), array('id' => $id));
    }

}

