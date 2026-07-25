<?php
/**
 * Core module functions for smartattend.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a new smartattend instance.
 *
 * @param stdClass $smartattend The data from the form.
 * @param mod_smartattend_mod_form $mform The form.
 * @return int The new course module id.
 */
function smartattend_add_instance($smartattend, $mform = null) {
    global $DB;
    
    $smartattend->timecreated = time();
    $smartattend->timemodified = time();
    $smartattend->id = $DB->insert_record('smartattend', $smartattend);
    
    return $smartattend->id;
}

/**
 * Updates an existing smartattend instance.
 *
 * @param stdClass $smartattend The data from the form.
 * @param mod_smartattend_mod_form $mform The form.
 * @return bool True on success.
 */
function smartattend_update_instance($smartattend, $mform = null) {
    global $DB;
    
    $smartattend->timemodified = time();
    $smartattend->id = $smartattend->instance;
    return $DB->update_record('smartattend', $smartattend);
}

/**
 * Deletes a smartattend instance.
 *
 * @param int $id The id of the module.
 * @return bool True on success.
 */
function smartattend_delete_instance($id) {
    global $DB;
    
    if (!$smartattend = $DB->get_record('smartattend', array('id' => $id))) {
        return false;
    }
    
    // Delete logs and sessions later.
    // $DB->delete_records('smartattend_logs', array('smartattendid' => $smartattend->id));
    // $DB->delete_records('smartattend_sessions', array('smartattendid' => $smartattend->id));
    
    return $DB->delete_records('smartattend', array('id' => $smartattend->id));
}

/**
 * Declares the features supported by this module.
 *
 * @param string $feature
 * @return mixed
 */
function smartattend_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        case FEATURE_GROUPS: return true;
        case FEATURE_GROUPINGS: return true;
        case FEATURE_MOD_PURPOSE: return MOD_PURPOSE_OTHER;
        case FEATURE_BACKUP_MOODLE2: return true;
        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block/secondary navigation.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param navigation_node $smartattendnode The node to add module settings to
 */
function smartattend_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $smartattendnode) {
    $context = $settingsnav->get_page()->cm->context;
    $cm = $settingsnav->get_page()->cm;
    
    if (has_capability('mod/smartattend:manage', $context)) {
        $node = navigation_node::create(
            'Report',
            new moodle_url('/mod/smartattend/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'report'
        );
        $smartattendnode->add_node($node);
    }
}
