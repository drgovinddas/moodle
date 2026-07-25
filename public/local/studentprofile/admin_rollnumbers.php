<?php
/**
 * Admin page for assigning student roll numbers.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Authorization: must be site admin.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/studentprofile/admin_rollnumbers.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managestudentprofiles', 'local_studentprofile'));
$PAGE->set_heading(get_string('managestudentprofiles', 'local_studentprofile'));

echo $OUTPUT->header();

$backurl = new moodle_url('/admin/settings.php', ['section' => 'local_studentprofile']);
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back to Settings', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

echo html_writer::tag('p', get_string('managestudentprofiles_desc', 'local_studentprofile'));

// Import/Export Buttons
$exporturl = new moodle_url('/local/studentprofile/admin_export_profiles.php');
$importurl = new moodle_url('/local/studentprofile/admin_import_profiles.php');

echo \html_writer::start_div('mb-4 d-flex gap-2');
echo \html_writer::link($importurl, get_string('importprofiles', 'local_studentprofile'), ['class' => 'btn btn-primary']);
echo \html_writer::link($exporturl, get_string('exportprofiles', 'local_studentprofile'), ['class' => 'btn btn-outline-primary']);
echo \html_writer::end_div();

$table = new \local_studentprofile\table\admin_rollnumbers('local-studentprofile-admin-rollnumbers');
$table->baseurl = new moodle_url('/local/studentprofile/admin_rollnumbers.php');
$table->setup();
$table->out(50, true);

// Include JS for inline editing
$PAGE->requires->js_call_amd('local_studentprofile/rollno_updater', 'init');

echo $OUTPUT->footer();
