<?php
/**
 * Admin page for managing college list.
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

$action  = optional_param('action', '', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);
$search  = optional_param('search', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/studentprofile/admin_colleges.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managecolleges', 'local_studentprofile'));
$PAGE->set_heading(get_string('managecolleges', 'local_studentprofile'));

// Handle NMC Sync action.
if ($action === 'sync') {
    require_sesskey();
    require_once($CFG->libdir . '/filelib.php');
    
    $url = 'https://www.nmc.org.in/MCIRest/open/getDataFromService?service=getAllUgColleges';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $success = 0;
    $updated = 0;
    
    // Get the current max sort order to append new colleges at the end
    $max_sortorder = (int)$DB->get_field_sql("SELECT MAX(sortorder) FROM {local_studentprofile_colleges}");
    
    if ($response) {
        // Fix any malformed UTF-8 characters from the NMC API
        $response = mb_convert_encoding($response, 'UTF-8', 'UTF-8, ISO-8859-1, ASCII');
        
        $data = json_decode($response, true);
        if (isset($data['ugCollege']) && is_array($data['ugCollege'])) {
            foreach ($data['ugCollege'] as $c) {
                if (empty($c['collegeName'])) continue;
                
                // Parse name "AN/001/G/1: Andaman & Nicobar..."
                $parts = explode(':', $c['collegeName'], 2);
                $collegename = trim(isset($parts[1]) ? $parts[1] : $parts[0]);
                $address = trim($c['address'] ?? '');
                // Generate shortname from collegename acronym
                $clean_name = str_replace(['&', ',', '-', '.', '(', ')'], ' ', $collegename);
                $words = explode(' ', $clean_name);
                $shortname_base = '';
                foreach ($words as $w) {
                    $w = trim($w);
                    if (strlen($w) > 0 && !in_array(strtolower($w), ['of', 'and', 'the', 'in', 'for'])) {
                        $shortname_base .= strtoupper($w[0]);
                    }
                }
                $shortname_base = substr($shortname_base, 0, 15);
                if (empty($shortname_base)) {
                    $shortname_base = 'COLLEGE';
                }
                $shortname = $shortname_base;
                
                // Check if exists by collegename
                $existing = $DB->get_record('local_studentprofile_colleges', ['collegename' => $collegename], '*', IGNORE_MULTIPLE);
                
                if ($existing) {
                    $existing->longname = $address;
                    if ($existing->sortorder == 0) {
                        $max_sortorder++;
                        $existing->sortorder = $max_sortorder;
                    }
                    $existing->timemodified = time();
                    $DB->update_record('local_studentprofile_colleges', $existing);
                    $updated++;
                } else {
                    // Check by shortname just in case
                    $existing_short = $DB->get_record('local_studentprofile_colleges', ['shortname' => $shortname], '*', IGNORE_MULTIPLE);
                    if ($existing_short) {
                        $existing_short->longname = $address;
                        if ($existing_short->sortorder == 0) {
                            $max_sortorder++;
                            $existing_short->sortorder = $max_sortorder;
                        }
                        $existing_short->timemodified = time();
                        $DB->update_record('local_studentprofile_colleges', $existing_short);
                        $updated++;
                    } else {
                        // Ensure shortname uniqueness before inserting
                        $counter = 1;
                        while ($DB->record_exists('local_studentprofile_colleges', ['shortname' => $shortname])) {
                            $shortname = $shortname_base . '_' . $counter;
                            $counter++;
                        }
                        
                        // Insert new
                        $max_sortorder++;
                        $newcollege = new stdClass();
                        $newcollege->shortname = $shortname;
                        $newcollege->collegename = $collegename;
                        $newcollege->longname = $address;
                        $newcollege->sortorder = $max_sortorder;
                        $newcollege->timecreated = time();
                        $newcollege->timemodified = time();
                        $DB->insert_record('local_studentprofile_colleges', $newcollege);
                        $success++;
                    }
                }
            }
            $msg = get_string('syncnmc_success', 'local_studentprofile', ['inserted' => $success, 'updated' => $updated]);
            $type = \core\output\notification::NOTIFY_SUCCESS;
        } else {
            $err = json_last_error_msg();
            $msg = get_string('syncnmc_error', 'local_studentprofile') . ' (JSON Error: ' . $err . ')';
            $type = \core\output\notification::NOTIFY_ERROR;
        }
    } else {
        $msg = get_string('syncnmc_error', 'local_studentprofile') . ' (CURL Error: ' . $curl_error . ')';
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    
    redirect(new moodle_url('/local/studentprofile/admin_colleges.php'), $msg, null, $type);
}

// Handle delete action (requires sesskey for CSRF protection).
if ($action === 'delete' && $id > 0) {
    require_sesskey();
    $DB->delete_records('local_studentprofile_colleges', ['id' => $id]);
    redirect(
        new moodle_url('/local/studentprofile/admin_colleges.php'),
        get_string('collegedeleted', 'local_studentprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Handle add/edit via form.
$form = new \local_studentprofile\form\college_form();

if ($action === 'edit' && $id > 0) {
    $record = $DB->get_record('local_studentprofile_colleges', ['id' => $id], '*', MUST_EXIST);
    $form->set_data($record);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/studentprofile/admin_colleges.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    $now = time();
    // Sanitize inputs server-side.
    $record = new stdClass();
    $record->shortname    = clean_param(trim($data->shortname), PARAM_TEXT);
    $record->collegename  = clean_param(trim($data->collegename), PARAM_TEXT);
    $record->sortorder    = (int)$data->sortorder;
    $record->timemodified = $now;

    if (!empty($data->id)) {
        $record->id = (int)$data->id;
        $DB->update_record('local_studentprofile_colleges', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_studentprofile_colleges', $record);
    }
    redirect(
        new moodle_url('/local/studentprofile/admin_colleges.php'),
        get_string('collegesaved', 'local_studentprofile'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

$backurl = new moodle_url('/admin/settings.php', ['section' => 'local_studentprofile']);
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back to Settings', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

// Show add/edit form above the list.
if ($action === 'edit' || $action === '') {
    echo html_writer::tag('h3', $id > 0 ? get_string('editcollege', 'local_studentprofile') : get_string('addcollege', 'local_studentprofile'));
    $form->display();
    echo html_writer::empty_tag('hr');
}

$addurl  = new moodle_url('/local/studentprofile/admin_colleges.php');
$syncurl = new moodle_url('/local/studentprofile/admin_colleges.php', ['action' => 'sync', 'sesskey' => sesskey()]);

echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::start_div('d-flex gap-2');
echo html_writer::link($addurl, get_string('addcollege', 'local_studentprofile'), ['class' => 'btn btn-primary']);
echo html_writer::link($syncurl, get_string('syncnmc', 'local_studentprofile'), ['class' => 'btn btn-outline-primary']);
echo html_writer::end_div();

// Search form.
echo html_writer::start_tag('form', ['action' => new moodle_url('/local/studentprofile/admin_colleges.php'), 'method' => 'get', 'class' => 'form-inline']);
echo html_writer::tag('input', '', ['type' => 'text', 'name' => 'search', 'value' => s($search), 'class' => 'form-control mr-2', 'placeholder' => 'Search colleges...']);
echo html_writer::tag('button', 'Search', ['type' => 'submit', 'class' => 'btn btn-secondary ms-2']);
if (!empty($search)) {
    echo html_writer::link(new moodle_url('/local/studentprofile/admin_colleges.php'), 'Clear', ['class' => 'btn btn-outline-secondary ms-2']);
}
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Show list using flexible_table (table_sql) for pagination.
$table = new \local_studentprofile\table\admin_colleges('local-studentprofile-admin-colleges', $search);
$table->baseurl = new moodle_url('/local/studentprofile/admin_colleges.php', ['search' => $search]);
$table->setup();
$table->out(50, true);

echo $OUTPUT->footer();
