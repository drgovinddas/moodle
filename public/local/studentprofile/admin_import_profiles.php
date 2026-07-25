<?php
/**
 * Admin import for student profiles.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/import_profiles_form.php');

// Authorization: must be site admin.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/local/studentprofile/admin_import_profiles.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('importprofiles', 'local_studentprofile'));
$PAGE->set_heading(get_string('importprofiles', 'local_studentprofile'));

$form = new \local_studentprofile\form\import_profiles_form($url);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/studentprofile/admin_rollnumbers.php'));
} else if ($data = $form->get_data()) {
    $content = $form->get_file_content('importfile');
    if ($content !== false) {
        $lines = explode("\n", str_replace("\r", "", $content));
        $headers = [];
        $errors = [];
        $successcount = 0;
        
        foreach ($lines as $i => $line) {
            $row = str_getcsv($line);
            if ($i === 0) {
                $headers = $row;
                // Normalize headers.
                foreach ($headers as &$h) {
                    $h = strtolower(trim($h));
                }
                continue;
            }
            if (empty(array_filter($row))) {
                continue; // Skip empty rows.
            }
            
            // Map row to headers.
            if (count($headers) !== count($row)) {
                $errors[] = "Row " . ($i + 1) . " has a column count mismatch.";
                continue;
            }
            $rowdata = array_combine($headers, $row);
            
            $email = trim($rowdata['email'] ?? '');
            if (empty($email)) {
                $errors[] = "Row " . ($i + 1) . " has no email address.";
                continue;
            }
            
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], '*', IGNORE_MULTIPLE);
            if (!$user) {
                $errors[] = "User not found with email: " . s($email);
                continue;
            }
            
            $shortname = trim($rowdata['college_shortname'] ?? '');
            if (empty($shortname)) {
                $errors[] = "College shortname is empty for email: " . s($email);
                continue;
            }
            
            $college = $DB->get_record('local_studentprofile_colleges', ['shortname' => $shortname], '*', IGNORE_MULTIPLE);
            if (!$college) {
                $errors[] = "College not found for shortname '" . s($shortname) . "' for email: " . s($email);
                continue;
            }
            
            $status_str = strtolower(trim($rowdata['rollnostatus'] ?? 'pending'));
            $rollnostatus = 0;
            if ($status_str === 'approved') {
                $rollnostatus = 1;
            } elseif ($status_str === 'disapproved') {
                $rollnostatus = 2;
            }
            
            // Update Moodle user table basic fields if provided
            $userupdate = new stdClass();
            $userupdate->id = $user->id;
            $userupdate->firstname = trim($rowdata['firstname'] ?? $user->firstname);
            $userupdate->lastname = trim($rowdata['lastname'] ?? $user->lastname);
            $DB->update_record('user', $userupdate);
            
            $existing = $DB->get_record('local_studentprofile_data', ['userid' => $user->id]);
            
            $record = new stdClass();
            $record->userid = $user->id;
            $record->firstname = $userupdate->firstname;
            $record->lastname = $userupdate->lastname;
            $record->middlename = trim($rowdata['middlename'] ?? ($existing ? $existing->middlename : ''));
            $record->fullname = trim(implode(' ', array_filter([$record->firstname, $record->middlename, $record->lastname])));
            
            $record->collegeid = $college->id;
            $record->collegename = $college->collegename;
            $record->shortname = $college->shortname;
            $record->longname = $college->longname ?? '';
            
            $record->admissionyear = (int)($rowdata['admissionyear'] ?? ($existing ? $existing->admissionyear : 0));
            $record->degreetype = trim($rowdata['degreetype'] ?? ($existing ? $existing->degreetype : 'UG'));
            $record->degree = trim($rowdata['degree'] ?? ($existing ? $existing->degree : ''));
            
            $record->rollno = trim($rowdata['rollno'] ?? ($existing ? $existing->rollno : ''));
            $record->rollnotype = trim($rowdata['rollnotype'] ?? ($existing ? $existing->rollnotype : 'permanent'));
            $record->rollnostatus = $rollnostatus;
            
            $record->draft = 0;
            $record->timemodified = time();
            
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_studentprofile_data', $record);
            } else {
                $record->timecreated = time();
                $DB->insert_record('local_studentprofile_data', $record);
            }
            
            $successcount++;
        }
        
        $msg = "Successfully imported/updated $successcount profiles.";
        if (!empty($errors)) {
            $msg .= "<br>Errors encountered:<br>" . implode("<br>", $errors);
            \core\notification::add($msg, \core\output\notification::NOTIFY_WARNING);
        } else {
            \core\notification::add($msg, \core\output\notification::NOTIFY_SUCCESS);
        }
        
        redirect(new moodle_url('/local/studentprofile/admin_rollnumbers.php'));
    }
}

echo $OUTPUT->header();

$backurl = new moodle_url('/local/studentprofile/admin_rollnumbers.php');
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

$form->display();

echo $OUTPUT->footer();
