<?php
/**
 * Admin page for editing a specific student's profile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$userid = required_param('userid', PARAM_INT);

// Authorization: must be site admin.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/local/studentprofile/admin_edit_profile.php', ['userid' => $userid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('editprofile', 'local_studentprofile'));
$PAGE->set_heading(get_string('editprofile', 'local_studentprofile'));

// Build degree type map for JS filter.
$degrees = $DB->get_records('local_studentprofile_degrees', null, '', 'degreename, degreetype');
$degree_map = [];
foreach ($degrees as $d) {
    $degree_map[$d->degreename] = $d->degreetype;
}
$PAGE->requires->js_call_amd('local_studentprofile/degree_filter', 'init', [$degree_map]);

// Load the form
$form = new \local_studentprofile\form\profile_completion_form($url);

$existing = $DB->get_record('local_studentprofile_data', ['userid' => $userid]);
if (!$existing) {
    throw new \moodle_exception('invaliduser');
}

// Pre-populate form
$formdata = new stdClass();
$formdata->firstname         = $existing->firstname ?? '';
$formdata->middlename        = $existing->middlename ?? '';
$formdata->lastname          = $existing->lastname ?? '';
$formdata->admissionyear     = $existing->admissionyear ?? '';
$formdata->degree            = $existing->degree ?? '';
$formdata->degreetype        = $existing->degreetype ?? '';
$formdata->rollno            = $existing->rollno ?? '';

if (!empty($existing->collegeid) && $existing->collegeid > 0) {
    $college = $DB->get_record('local_studentprofile_colleges', ['id' => $existing->collegeid]);
    if ($college) {
        $formdata->collegeid_key = $college->id;
    }
} else {
    $formdata->collegeid_key     = '__custom__';
    $formdata->customcollegename = $existing->collegename ?? '';
}
$form->set_data($formdata);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/studentprofile/admin_rollnumbers.php'));
} else if ($data = $form->get_data()) {
    // Process form submission
    $now = time();

    // Resolve college data
    $key = clean_param($data->collegeid_key, PARAM_TEXT);
    if ($key === '__custom__') {
        $collegename = clean_param(trim($data->customcollegename ?? ''), PARAM_TEXT);
        $sql = "SELECT * FROM {local_studentprofile_colleges} WHERE " . $DB->sql_like('collegename', ':name', false, false);
        $existingcollege = $DB->get_record_sql($sql, ['name' => $collegename], IGNORE_MULTIPLE);
        
        if ($existingcollege) {
            $collegeid   = $existingcollege->id;
            $collegename = $existingcollege->collegename;
            $shortname   = $existingcollege->shortname;
            $longname    = $existingcollege->longname ?? '';
        } else {
            $words = preg_split("/\s+|-/", $collegename);
            $acronym = '';
            foreach ($words as $w) {
                if (preg_match('/^\p{L}/u', $w, $matches)) {
                    $acronym .= mb_strtoupper($matches[0]);
                }
            }
            if (empty($acronym)) {
                $acronym = 'C';
            }
            
            $shortname = $acronym;
            $counter = 1;
            while ($DB->record_exists('local_studentprofile_colleges', ['shortname' => $shortname])) {
                $shortname = $acronym . $counter;
                $counter++;
            }
            
            $newcollege = new \stdClass();
            $newcollege->shortname = $shortname;
            $newcollege->collegename = $collegename;
            $newcollege->longname = '';
            $newcollege->sortorder = 0;
            $newcollege->timecreated = $now;
            $newcollege->timemodified = $now;
            
            $collegeid = $DB->insert_record('local_studentprofile_colleges', $newcollege);
            $longname  = '';
        }
    } else {
        $parts       = explode('|', $key, 4); // Keep for backwards compatibility with old form submissions if any
        $collegeid   = (int)($parts[0] ?? $key);
        if ($collegeid > 0) {
            $college = $DB->get_record('local_studentprofile_colleges', ['id' => $collegeid]);
            if ($college) {
                $collegename = $college->collegename;
                $shortname   = $college->shortname;
                $longname    = $college->longname ?? '';
            } else {
                $collegeid = 0;
            }
        }
    }

    $firstname  = clean_param(trim($data->firstname), PARAM_TEXT);
    $middlename = clean_param(trim($data->middlename ?? ''), PARAM_TEXT);
    $lastname   = clean_param(trim($data->lastname), PARAM_TEXT);
    $fullname   = trim(implode(' ', array_filter([$firstname, $middlename, $lastname])));

    $degreetype = clean_param($data->degreetype, PARAM_ALPHA);
    if (!in_array($degreetype, ['UG', 'PG'])) {
        $degreetype = 'UG';
    }

    $admissionyear = (int)$data->admissionyear;
    $degree = clean_param($data->degree, PARAM_TEXT);
    $rollno = clean_param(trim($data->rollno ?? ''), PARAM_TEXT);
    
    // Status should be pending (0) if roll number is changed
    $rollnostatus = 0;
    if ($existing && $existing->rollno === $rollno) {
        $rollnostatus = $existing->rollnostatus ?? 0;
    }

    $record = new stdClass();
    $record->id            = $existing->id;
    $record->firstname     = $firstname;
    $record->middlename    = $middlename;
    $record->lastname      = $lastname;
    $record->fullname      = $fullname;
    $record->collegeid     = $collegeid;
    $record->collegename   = $collegename;
    $record->shortname     = $shortname;
    $record->longname      = $longname;
    $record->admissionyear = $admissionyear;
    $record->degree        = $degree;
    $record->degreetype    = $degreetype;
    $record->rollno        = $rollno;
    $record->rollnostatus  = $rollnostatus;
    $record->timemodified  = $now;

    $DB->update_record('local_studentprofile_data', $record);

    $userupdate = new stdClass();
    $userupdate->id        = $userid;
    $userupdate->firstname = $firstname;
    $userupdate->lastname  = $lastname;
    $DB->update_record('user', $userupdate);

    redirect(new moodle_url('/local/studentprofile/admin_rollnumbers.php'));
}

echo $OUTPUT->header();

$backurl = new moodle_url('/local/studentprofile/admin_rollnumbers.php');
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

$form->display();
echo $OUTPUT->footer();
