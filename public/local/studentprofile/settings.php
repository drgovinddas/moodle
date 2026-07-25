<?php
/**
 * Admin settings for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_studentprofile', new lang_string('settings', 'local_studentprofile'));
    $ADMIN->add('localplugins', $settings);

    // Enable/disable mandatory completion.
    $settings->add(new admin_setting_configcheckbox(
        'local_studentprofile/enablecompletion',
        new lang_string('enablecompletion', 'local_studentprofile'),
        new lang_string('enablecompletion_desc', 'local_studentprofile'),
        1
    ));

    // Configure admission year range.
    $settings->add(new admin_setting_configtext(
        'local_studentprofile/admissionyears',
        new lang_string('admissionyears', 'local_studentprofile'),
        new lang_string('admissionyears_desc', 'local_studentprofile'),
        '2010-2030',
        PARAM_TEXT
    ));

    // Link to manage colleges.
    $settings->add(new admin_setting_heading(
        'local_studentprofile/collegesheading',
        new lang_string('managecolleges', 'local_studentprofile'),
        new lang_string('managecolleges_desc', 'local_studentprofile') .
        ' ' . html_writer::link(
            new moodle_url('/local/studentprofile/admin_colleges.php'),
            get_string('managecolleges', 'local_studentprofile')
        )
    ));

    // Link to manage degrees.
    $settings->add(new admin_setting_heading(
        'local_studentprofile/degreesheading',
        new lang_string('managedegrees', 'local_studentprofile'),
        new lang_string('managedegrees_desc', 'local_studentprofile') .
        ' ' . html_writer::link(
            new moodle_url('/local/studentprofile/admin_degrees.php'),
            get_string('managedegrees', 'local_studentprofile')
        )
    ));

    // Manage Rules
    $settings->add(new admin_setting_heading(
        'local_studentprofile_rules_heading',
        get_string('managerules', 'local_studentprofile'),
        get_string('managerulesdesc', 'local_studentprofile') . '<br><a href="' . $CFG->wwwroot . '/local/studentprofile/admin_rules.php">Manage Rules</a>'
    ));

    // Link to manage student profiles.
    $settings->add(new admin_setting_heading(
        'local_studentprofile/rollnumbersheading',
        get_string('managestudentprofiles', 'local_studentprofile'),
        get_string('managestudentprofiles_desc', 'local_studentprofile') . ' ' .
        html_writer::link(
            new moodle_url('/local/studentprofile/admin_rollnumbers.php'),
            get_string('managestudentprofiles', 'local_studentprofile')
        )
    ));

    // Automatically synchronize requireconfirmation of Google OAuth2 issuer.
    global $DB;
    $enablecompletion = get_config('local_studentprofile', 'enablecompletion');
    if ($enablecompletion) {
        if ($DB->record_exists('oauth2_issuer', ['servicetype' => 'google', 'requireconfirmation' => 1])) {
            $DB->set_field('oauth2_issuer', 'requireconfirmation', 0, ['servicetype' => 'google']);
        }
    } else {
        if ($DB->record_exists('oauth2_issuer', ['servicetype' => 'google', 'requireconfirmation' => 0])) {
            $DB->set_field('oauth2_issuer', 'requireconfirmation', 1, ['servicetype' => 'google']);
        }
    }
}
