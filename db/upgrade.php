<?php

function xmldb_block_cps_upgrade($oldversion) {
    global $DB;

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

    return $result;
}
