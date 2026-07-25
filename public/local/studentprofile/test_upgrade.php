<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/upgradelib.php');

$plugin = new stdClass();
require(__DIR__ . '/version.php');

$oldversion = get_config('local_studentprofile', 'version');
if ($oldversion < $plugin->version) {
    echo "Upgrading from $oldversion to $plugin->version...\n";
    upgrade_plugins('local', 'local', 'admin/settings.php?section=localplugins', false);
    echo "Done!\n";
} else {
    echo "No upgrade needed (current version: $oldversion, code version: $plugin->version)\n";
}
