<?php

function xmldb_block_cps_upgrade($oldversion) {
    global $DB, $CFG;

    $result = true;

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

    return $result;
}
