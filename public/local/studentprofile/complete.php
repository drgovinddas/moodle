<?php
/**
 * Mandatory profile completion page.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Force user to login.
require_login();

$context = context_system::instance();

// If guest, or bypass capability, go to home.
if (isguestuser() || has_capability('local/studentprofile:bypasscompletion', $context)) {
    redirect(new moodle_url('/'));
}

$url = new moodle_url('/local/studentprofile/complete.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('profilecompletionrequired', 'local_studentprofile'));
$PAGE->set_heading(get_string('profilecompletionrequired', 'local_studentprofile'));

// Load AMD auto-save module.
$PAGE->requires->js_call_amd('local_studentprofile/profile_autosave', 'init', [
    ['formid' => 'mform1', 'interval' => 30000]
]);

// Build degree type map and college degree map for JS filter.
$degrees = $DB->get_records('local_studentprofile_degrees', null, '', 'degreename, degreetype');
$degree_map = [];
foreach ($degrees as $d) {
    $degree_map[$d->degreename] = $d->degreetype;
}

$colleges = $DB->get_records('local_studentprofile_colleges', null, '', 'id, degrees');
$college_map = [];
foreach ($colleges as $c) {
    if (!empty($c->degrees)) {
        $college_map[$c->id] = array_map('trim', explode(',', $c->degrees));
    }
}

$PAGE->requires->js_call_amd('local_studentprofile/degree_filter', 'init', [$degree_map, $college_map]);

$form = new \local_studentprofile\form\profile_completion_form();

// Pre-populate form from draft if one exists.
$existing = $DB->get_record('local_studentprofile_data', ['userid' => $USER->id]);
if ($existing && !empty($existing->draftdata) && $existing->draft == 1) {
    $draftdata = @json_decode($existing->draftdata, true);
    if (is_array($draftdata)) {
        $formdata = new stdClass();
        foreach ($draftdata as $k => $v) {
            $formdata->$k = $v;
        }
        if (empty($formdata->phone2)) {
            $formdata->phone2 = $USER->phone2 ?? '';
        }
        $form->set_data($formdata);
    }
} elseif ($existing && $existing->draft == 0) {
    // Already completed — pre-fill from saved record.
    $formdata = new stdClass();
    $formdata->firstname         = $existing->firstname ?? '';
    $formdata->middlename        = $existing->middlename ?? '';
    $formdata->lastname          = $existing->lastname ?? '';
    $formdata->phone2            = $USER->phone2 ?? '';
    $formdata->admissionyear     = $existing->admissionyear ?? '';
    $formdata->degree            = $existing->degree ?? '';
    $formdata->degreetype        = $existing->degreetype ?? '';
    $formdata->rollno            = $existing->rollno ?? '';
    // Restore college selection key.
    if (!empty($existing->collegeid) && $existing->collegeid > 0) {
        $college = $DB->get_record('local_studentprofile_colleges', ['id' => $existing->collegeid]);
        if ($college) {
            $formdata->collegeid_key = $college->id;
        }
    } else {
        $formdata->collegeid_key    = '__custom__';
        $formdata->customcollegename = $existing->collegename ?? '';
    }
    $form->set_data($formdata);
}

if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    // Process form submission.
    $now = time();

    // Resolve college data.
    $key = clean_param($data->collegeid_key, PARAM_TEXT);
    if ($key === '__custom__') {
        $collegename = clean_param(trim($data->customcollegename ?? ''), PARAM_TEXT);
        
        // 1. Check if college already exists by name (case-insensitive).
        $sql = "SELECT * FROM {local_studentprofile_colleges} WHERE " . $DB->sql_like('collegename', ':name', false, false);
        $existingcollege = $DB->get_record_sql($sql, ['name' => $collegename], IGNORE_MULTIPLE);
        
        if ($existingcollege) {
            $collegeid   = $existingcollege->id;
            $collegename = $existingcollege->collegename;
            $shortname   = $existingcollege->shortname;
            $longname    = $existingcollege->longname ?? '';
        } else {
            // 2. Generate shortname (acronym).
            $words = preg_split("/\s+|-/", $collegename);
            $acronym = '';
            foreach ($words as $w) {
                if (preg_match('/^\p{L}/u', $w, $matches)) {
                    $acronym .= mb_strtoupper($matches[0]);
                }
            }
            if (empty($acronym)) {
                $acronym = 'C'; // Fallback
            }
            
            // 3. Ensure shortname uniqueness.
            $shortname = $acronym;
            $counter = 1;
            while ($DB->record_exists('local_studentprofile_colleges', ['shortname' => $shortname])) {
                $shortname = $acronym . $counter;
                $counter++;
            }
            
            // 4. Insert into master table with degree attached.
            $degree = clean_param($data->degree ?? '', PARAM_TEXT);
            $newcollege = new \stdClass();
            $newcollege->shortname = $shortname;
            $newcollege->collegename = $collegename;
            $newcollege->longname = '';
            $newcollege->degrees = $degree;
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

    // Compose full name from parts.
    $firstname  = clean_param(trim($data->firstname), PARAM_TEXT);
    $middlename = clean_param(trim($data->middlename ?? ''), PARAM_TEXT);
    $lastname   = clean_param(trim($data->lastname), PARAM_TEXT);
    $fullname   = trim(implode(' ', array_filter([$firstname, $middlename, $lastname])));

    // Validate degree type.
    $degreetype = clean_param($data->degreetype, PARAM_ALPHA);
    if (!in_array($degreetype, ['UG', 'PG'])) {
        $degreetype = 'UG';
    }

    // Validate admission year.
    $admissionyear = (int)$data->admissionyear;

    // Validate degree name from DB.
    $degree = clean_param($data->degree, PARAM_TEXT);

    // Roll number from student.
    $rollno = clean_param(trim($data->rollno ?? ''), PARAM_TEXT);
    
    // Status should be pending (0) if roll number is changed or newly assigned by student.
    $rollnostatus = 0;
    if ($existing && $existing->rollno === $rollno) {
        $rollnostatus = $existing->rollnostatus ?? 0;
    }

    $record = new stdClass();
    $record->userid        = $USER->id;
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
    $record->mobileno      = clean_param(trim($data->phone2 ?? ''), PARAM_NOTAGS);
    $record->rollnostatus  = $rollnostatus;
    $record->draft         = 0;   // Mark as complete.
    $record->draftdata     = null; // Clear draft.
    $record->timemodified  = $now;

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_studentprofile_data', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_studentprofile_data', $record);
    }

    // Sync Moodle user's firstname/lastname and phone2.
    $userupdate = new stdClass();
    $userupdate->id        = $USER->id;
    $userupdate->firstname = $firstname;
    $userupdate->lastname  = $lastname;
    $userupdate->phone2    = clean_param(trim($data->phone2 ?? ''), PARAM_NOTAGS);
    $DB->update_record('user', $userupdate);

    // Clear session flag.
    unset($SESSION->local_studentprofile_needs_completion);
    unset($SESSION->local_studentprofile_has_draft);

    // Trigger event.
    $event = \local_studentprofile\event\profile_completed::create([
        'context'  => $context,
        'objectid' => $USER->id,
    ]);
    $event->trigger();

    redirect(new moodle_url('/my/'));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
