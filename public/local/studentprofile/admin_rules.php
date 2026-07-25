<?php
/**
 * Admin interface for managing course assignment rules.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security checks.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$ruleid = optional_param('id', 0, PARAM_INT);

// Handle Delete.
if ($action === 'delete' && $ruleid) {
    require_sesskey();
    $DB->delete_records('local_studentprofile_rules', ['id' => $ruleid]);
    redirect(new moodle_url('/local/studentprofile/admin_rules.php'), get_string('deleted'));
}

// Handle Run All.
if ($action === 'runall') {
    require_sesskey();
    // Fetch all active profiles for non-deleted users.
    $sql = "SELECT spd.* 
              FROM {local_studentprofile_data} spd
              JOIN {user} u ON u.id = spd.userid
             WHERE spd.draft = 0 AND u.deleted = 0";
    $students = $DB->get_records_sql($sql);
    $count = 0;
    
    // Simulate event for each student.
    foreach ($students as $student) {
        $event = \local_studentprofile\event\profile_completed::create([
            'context'  => $context,
            'objectid' => $student->userid,
        ]);
        \local_studentprofile\observer::profile_completed($event);
        $count++;
    }
    
    redirect(new moodle_url('/local/studentprofile/admin_rules.php'), "Rules engine executed for $count students.");
}

$url = new moodle_url('/local/studentprofile/admin_rules.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('managerules', 'local_studentprofile'));
$PAGE->set_heading(get_string('managerules', 'local_studentprofile'));

echo $OUTPUT->header();

$backurl = new moodle_url('/admin/settings.php', ['section' => 'local_studentprofile']);
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back to Settings', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

echo $OUTPUT->heading(get_string('managerules', 'local_studentprofile'));

// Action buttons.
$addurl = new moodle_url('/local/studentprofile/admin_rule_edit.php');
$runurl = new moodle_url('/local/studentprofile/admin_rules.php', ['action' => 'runall', 'sesskey' => sesskey()]);

echo \html_writer::start_div('mb-3 d-flex justify-content-between');
echo \html_writer::link($addurl, get_string('addnewrule', 'local_studentprofile'), ['class' => 'btn btn-primary']);
echo \html_writer::link($runurl, get_string('runrulesengine', 'local_studentprofile'), [
    'class' => 'btn btn-warning',
    'onclick' => 'return confirm("Are you sure you want to run the engine for ALL completed student profiles?");'
]);
echo \html_writer::end_div();

$table = new \local_studentprofile\table\admin_rules('local-studentprofile-rules-table');
$table->baseurl = $url;
$table->setup();
$table->out(50, true);

echo $OUTPUT->footer();
