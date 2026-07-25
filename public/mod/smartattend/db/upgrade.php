<?php
/**
 * Upgrade code for mod_smartattend.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_smartattend_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024041901) {

        // Define field name to be added to smartattend_sessions.
        $table = new xmldb_table('smartattend_sessions');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'smartattendid');

        // Conditionally launch add field name.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Smartattend savepoint reached.
        upgrade_mod_savepoint(true, 2024041901, 'smartattend');
    }

    if ($oldversion < 2026051701) {

        // Create smartattend_faces table for pure-PHP pHash face recognition.
        $table = new xmldb_table('smartattend_faces');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('phash', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);


        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026051701, 'smartattend');
    }


    if ($oldversion < 2026051702) {

        // Change phash field to TEXT to accommodate multi-crop hashes (256 * 3 + separators).
        $table = new xmldb_table('smartattend_faces');
        $field = new xmldb_field('phash', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        upgrade_mod_savepoint(true, 2026051702, 'smartattend');
    }

    if ($oldversion < 2026062700) {
        $table_inst = new xmldb_table('smartattend');
        
        $field_timeslots = new xmldb_field('timeslots', XMLDB_TYPE_TEXT, null, null, null, null, null, 'allowface');
        if (!$dbman->field_exists($table_inst, $field_timeslots)) {
            $dbman->add_field($table_inst, $field_timeslots);
        }

        $field_spanbehavior = new xmldb_field('spanbehavior', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'start_only', 'timeslots');
        if (!$dbman->field_exists($table_inst, $field_spanbehavior)) {
            $dbman->add_field($table_inst, $field_spanbehavior);
        }

        $table_sess = new xmldb_table('smartattend_sessions');

        $field_lecturetype = new xmldb_field('lecturetype', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'qrexpires');
        if (!$dbman->field_exists($table_sess, $field_lecturetype)) {
            $dbman->add_field($table_sess, $field_lecturetype);
        }

        $field_isholiday = new xmldb_field('isholiday', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'lecturetype');
        if (!$dbman->field_exists($table_sess, $field_isholiday)) {
            $dbman->add_field($table_sess, $field_isholiday);
        }

        upgrade_mod_savepoint(true, 2026062700, 'smartattend');
    }

    return true;
}

