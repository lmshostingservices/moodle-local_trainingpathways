<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_trainingpathways_upgrade($oldversion) {
    // Initial install via install.xml — no schema changes for v1.0.0–1.0.6.

    if ($oldversion < 2026060301) {
        // v1.0.7 — Added db/events.php observer for core\event\course_completed.
        // No schema changes; marker savepoint only so Moodle registers the new
        // event subscription on upgrade.
        upgrade_plugin_savepoint(true, 2026060301, 'local', 'trainingpathways');
    }

    if ($oldversion < 2026060302) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026060302, 'local', 'trainingpathways');
    }

    return true;
}