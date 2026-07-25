<?php
/**
 * Create or edit a course assignment rule.
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

$id = optional_param('id', 0, PARAM_INT);

// Load existing rule if editing.
$rule = new stdClass();
$rule->id = 0;
if ($id) {
    $rule = $DB->get_record('local_studentprofile_rules', ['id' => $id], '*', MUST_EXIST);
    // Courses comes as JSON, autocomplete needs an array.
    $rule->courses = @json_decode($rule->courses, true);
    if (!is_array($rule->courses)) {
        $rule->courses = [];
    }
}

// Build form.
$mform = new \local_studentprofile\form\rule_edit_form(null, ['rule' => $rule]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/studentprofile/admin_rules.php'));
} else if ($data = $mform->get_data()) {
    
    $record = new stdClass();
    $record->rulename = $data->rulename;
    $record->status = $data->status;
    $record->priority = $data->priority;
    $record->stopprocessing = !empty($data->stopprocessing) ? 1 : 0;
    $record->conditions = $data->conditions; // Raw JSON from JS builder.
    
    // Process courses array to JSON.
    $record->courses = json_encode(!empty($data->courses) ? array_values($data->courses) : []);
    
    $record->timemodified = time();

    if ($id) {
        $record->id = $id;
        $DB->update_record('local_studentprofile_rules', $record);
        $msg = "Rule updated successfully.";
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_studentprofile_rules', $record);
        $msg = "Rule created successfully.";
    }

    redirect(new moodle_url('/local/studentprofile/admin_rules.php'), $msg);
}

$url = new moodle_url('/local/studentprofile/admin_rule_edit.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('editrule', 'local_studentprofile'));
$PAGE->set_heading(get_string('editrule', 'local_studentprofile'));

// Fetch reference data for the JS query builder.
$colleges = $DB->get_records('local_studentprofile_colleges', null, 'sortorder ASC', 'id, collegename');
$college_opts = [];
foreach ($colleges as $c) {
    $college_opts[$c->collegename] = $c->collegename;
}

$degrees = $DB->get_records('local_studentprofile_degrees', null, 'sortorder ASC', 'id, degreename');
$degree_opts = [];
foreach ($degrees as $d) {
    $degree_opts[$d->degreename] = $d->degreename;
}

// Build reporting week options from oldest profile timecreated to current/newest profile timecreated.
$min_time = $DB->get_field_sql("SELECT MIN(timecreated) FROM {local_studentprofile_data} WHERE timecreated > 0");
$max_time = $DB->get_field_sql("SELECT MAX(timecreated) FROM {local_studentprofile_data} WHERE timecreated > 0");

if (!$min_time) {
    $min_time = strtotime('-1 month');
}
if (!$max_time) {
    $max_time = time();
}
if ($max_time < time()) {
    $max_time = time();
}

$reporting_week_opts = [];
// Iterate month by month from min_time to max_time
$start_yr = (int)date('Y', $min_time);
$start_mo = (int)date('n', $min_time);
$end_yr   = (int)date('Y', $max_time);
$end_mo   = (int)date('n', $max_time);

$curr_yr = $start_yr;
$curr_mo = $start_mo;

while ($curr_yr < $end_yr || ($curr_yr == $end_yr && $curr_mo <= $end_mo)) {
    $dateObj = DateTime::createFromFormat('!n-Y', $curr_mo . '-' . $curr_yr);
    $monthCode = strtolower($dateObj->format('M')); // jan, feb, etc.
    $monthTitle = $dateObj->format('M');           // Jan, Feb, etc.
    
    for ($w = 1; $w <= 4; $w++) {
        $key   = $monthCode . ' ' . $curr_yr . ' ' . $w . ' week';
        $label = $monthTitle . ' ' . $curr_yr . ' - Week ' . $w;
        $reporting_week_opts[$key] = $label;
    }
    
    $curr_mo++;
    if ($curr_mo > 12) {
        $curr_mo = 1;
        $curr_yr++;
    }
}

// Prepare configuration for JS.
$builder_config = [
    'existing_conditions' => $id ? $rule->conditions : null,
    'fields' => [
        [
            'id' => 'collegename',
            'label' => 'College Name',
            'type' => 'string',
            'input' => 'select',
            'values' => $college_opts,
            'multiple' => true,
            'operators' => ['in', 'not_in', 'equal', 'not_equal']
        ],
        [
            'id' => 'admissionyear',
            'label' => 'Admission Year',
            'type' => 'integer',
            'input' => 'number',
            'operators' => ['equal', 'not_equal', 'greater', 'greater_or_equal', 'less', 'less_or_equal', 'between']
        ],
        [
            'id' => 'degreetype',
            'label' => 'Degree Type',
            'type' => 'string',
            'input' => 'select',
            'values' => [
                'UG' => 'UG (Undergraduate)',
                'PG' => 'PG (Postgraduate)'
            ],
            'operators' => ['equal', 'not_equal']
        ],
        [
            'id' => 'degree',
            'label' => 'Degree',
            'type' => 'string',
            'input' => 'select',
            'values' => $degree_opts,
            'multiple' => true,
            'operators' => ['in', 'not_in', 'equal', 'not_equal']
        ],
        [
            'id' => 'firstname',
            'label' => 'First Name',
            'type' => 'string',
            'operators' => ['equal', 'not_equal', 'contains', 'starts_with', 'ends_with']
        ],
        [
            'id' => 'lastname',
            'label' => 'Last Name',
            'type' => 'string',
            'operators' => ['equal', 'not_equal', 'contains', 'starts_with', 'ends_with']
        ],
        [
            'id' => 'reporting_week',
            'label' => 'Reporting Week (Profile Created At)',
            'type' => 'string',
            'input' => 'select',
            'values' => $reporting_week_opts,
            'multiple' => true,
            'operators' => ['in', 'not_in', 'equal', 'not_equal']
        ],
    ]
];

// Include a lightweight third-party jQuery QueryBuilder if possible, or we will write a custom one.
// Since we can't easily npm install here without messing with Moodle core, 
// we'll use an AMD module that renders a simple HTML builder.
$PAGE->requires->js_call_amd('local_studentprofile/rule_builder', 'init', [$builder_config]);

echo $OUTPUT->header();

$backurl = new moodle_url('/admin/settings.php', ['section' => 'local_studentprofile']);
echo \html_writer::start_div('mb-3');
echo \html_writer::link($backurl, '← Back to Settings', ['class' => 'btn btn-secondary']);
echo \html_writer::end_div();

echo $OUTPUT->heading(get_string('editrule', 'local_studentprofile'));

$mform->display();

echo $OUTPUT->footer();
