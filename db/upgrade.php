<?php

function xmldb_block_cps_upgrade($oldversion) {
    global $DB, $CFG;

    $result = true;

    $dbman = $DB->get_manager();

    // Clear out old event handlers
    if ($oldversion < 2012012514) {

        $params = array(
            'component' => 'block_cps',
            'handlerfile' => '/blocks/cps/eventslib.php'
        );

        $result = ($result and $DB->delete_records('events_handlers', $params));

        upgrade_block_savepoint($result, 2012012514, 'cps');
    }

    // Id numbers are re-assigned; fixes settings from before #44
    if ($oldversion < 2012072013) {
        require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

        // Warning: this will fire a bunch of updates and events
        // This could potentially take a while... if there are a
        // lot of settings to iterate over
        foreach (array('split', 'crosslist', 'team_section') as $setting) {
            $class = "cps_{$setting}";

            $settings = $class::get_all();
            foreach ($settings as $obj) {
                // Update Course shortname / fullname / idnumber.
                // Normally save() would work, but it is only updating so this
                // behavior is fine.
                $obj->update_manifest();
                $obj->apply();
            }
        }

        upgrade_block_savepoint($result, 2012072013, 'cps');
    }

    if ($oldversion < 2012072209) {
        require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

        foreach (array('split') as $setting) {
            $class = "cps_{$setting}";

            $settings = $class::get_all();
            foreach ($settings as $obj) {
                $obj->update_manifest();
                $obj->apply();
            }
        }

        upgrade_block_savepoint($result, 2012072209, 'cps');
    }

    if ($oldversion < 2012082013) {
        require_once $CFG->dirroot . '/blocks/cps/classes/lib.php';

        // Gather all team_sections
        $all_sections = cps_team_section::get_all();

        // Be safe: clear out all section associations
        foreach ($all_sections as $section) {
            $section->delete($section->id);
            unset($section->id);
        }

        // Define field requesterid to be added to enrol_cps_team_sections
        $table = new xmldb_table('enrol_cps_team_sections');
        $field = new xmldb_field('requesterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');

        // Conditionally launch add field requesterid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field courseid to be added to enrol_cps_team_sections
        $table = new xmldb_table('enrol_cps_team_sections');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'requesterid');

        // Conditionally launch add field courseid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key coursetouescourse (foreign) to be added to enrol_cps_team_sections
        $table = new xmldb_table('enrol_cps_team_sections');
        $key = new xmldb_key('coursetouescourse', XMLDB_KEY_FOREIGN, array('courseid'), 'enrol_ues_courses', array('id'));

        // Launch add key coursetouescourse
        $dbman->add_key($table, $key);

        // Define index rqucou (not unique) to be added to enrol_cps_team_sections
        $table = new xmldb_table('enrol_cps_team_sections');
        $index = new xmldb_index('courequ', XMLDB_INDEX_NOTUNIQUE, array('requesterid', 'courseid'));

        // Conditionally launch add index rqucou
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Re-allocate team_ections
        foreach ($all_sections as $section) {
            $request = $section->request();
            $section->requesterid = $request->userid;
            $section->courseid = $request->courseid;

            $section->save();
            $section->update_manifest();
            $section->apply();
        }

        // cps savepoint reached
        upgrade_block_savepoint($result, 2012082013, 'cps');
    }

    return $result;
}
