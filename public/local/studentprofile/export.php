<?php
/**
 * CSV Export for filtered student participants.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = required_param('id', PARAM_INT);
$filtersjson = optional_param('filters', '', PARAM_RAW);

// Legacy parameters fallback.
$college = optional_param('college', '', PARAM_TEXT);
$degree = optional_param('degree', '', PARAM_TEXT);
$year = optional_param('year', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
course_require_view_participants($context);

// Set headers for download.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=participants_export_' . time() . '.csv');

$output = fopen('php://output', 'w');

// Header row.
fputcsv($output, [
    get_string('fullname'),
    get_string('email'),
    get_string('collegename', 'local_studentprofile'),
    get_string('shortname', 'local_studentprofile'),
    get_string('admissionyear', 'local_studentprofile'),
    get_string('degree', 'local_studentprofile')
]);

// Fetch data using participants table logic.
$table = new \local_studentprofile\table\participants("user-index-participants-{$course->id}");

$filterset = new \local_studentprofile\table\participants_filterset();

if (!empty($filtersjson)) {
    $filtersobj = json_decode($filtersjson, true);
    if (is_array($filtersobj)) {
        if (isset($filtersobj['jointype'])) {
            $filterset->set_join_type((int)$filtersobj['jointype']);
        }
        if (isset($filtersobj['filters']) && is_array($filtersobj['filters'])) {
            foreach ($filtersobj['filters'] as $filtername => $filterdata) {
                if (isset($filterdata['name'], $filterdata['jointype'], $filterdata['values'])) {
                    $filterset->add_filter_from_params(
                        $filterdata['name'],
                        (int)$filterdata['jointype'],
                        (array)$filterdata['values']
                    );
                }
            }
        }
    }
} else {
    // Fallback to legacy parameters.
    if (!empty($college)) {
        $filterset->add_filter(new \core_table\local\filter\string_filter('college', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [$college]));
    }
    if (!empty($degree)) {
        $filterset->add_filter(new \core_table\local\filter\string_filter('degree', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [$degree]));
    }
    if ($year > 0) {
        $filterset->add_filter(new \core_table\local\filter\integer_filter('year', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [$year]));
    }
    if (!empty($search)) {
        $filterset->add_filter(new \core_table\local\filter\string_filter('keywords', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [$search]));
    }
}

// Ensure courseid filter is always present.
if (!$filterset->has_filter('courseid')) {
    $filterset->add_filter(new \core_table\local\filter\integer_filter('courseid', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [(int)$course->id]));
}

$table->set_filterset($filterset);

// Get all matching records (do not paginate).
$table->query_db(50000, false);

if (!empty($table->rawdata)) {
    // For custom field data fast lookup, let's pre-fetch all records from local_studentprofile_data for the matching user IDs.
    $userids = array_keys($table->rawdata);
    list($insql, $inparams) = $DB->get_in_or_equal($userids);
    $customfields = $DB->get_records_select('local_studentprofile_data', "userid $insql", $inparams);
    $customfieldsbyuser = [];
    foreach ($customfields as $cf) {
        $customfieldsbyuser[$cf->userid] = $cf;
    }

    foreach ($table->rawdata as $user) {
        $usercustom = isset($customfieldsbyuser[$user->id]) ? $customfieldsbyuser[$user->id] : null;

        fputcsv($output, [
            fullname($user),
            $user->email,
            $usercustom ? $usercustom->collegename : '',
            $usercustom ? $usercustom->shortname : '',
            $usercustom ? $usercustom->admissionyear : '',
            $usercustom ? $usercustom->degree : ''
        ]);
    }
}

fclose($output);
exit();
