<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_trainingpathways_upgrade($oldversion) {
    if ($oldversion < 2026060300) {
        upgrade_plugin_savepoint(true, 2026060300, 'local', 'trainingpathways');
    }
    return true;
}
