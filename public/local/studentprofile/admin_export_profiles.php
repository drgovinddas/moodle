<?php
/**
 * Admin export for student profiles.
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

// Set headers for download.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=admin_student_profiles_export_' . time() . '.csv');

$output = fopen('php://output', 'w');

// Header row.
fputcsv($output, [
    'email',
    'firstname',
    'middlename',
    'lastname',
    'college_shortname',
    'admissionyear',
    'degreetype',
    'degree',
    'rollno',
    'rollnotype',
    'rollnostatus'
]);

$sql = "SELECT u.id, u.email, u.firstname, u.lastname, 
               spd.middlename, spd.shortname AS college_shortname, 
               spd.admissionyear, spd.degreetype, spd.degree, 
               spd.rollno, spd.rollnotype, spd.rollnostatus
          FROM {user} u
          JOIN {local_studentprofile_data} spd ON spd.userid = u.id
         WHERE u.deleted = 0 AND spd.draft = 0
      ORDER BY u.firstname, u.lastname";

$records = $DB->get_recordset_sql($sql);

foreach ($records as $r) {
    // Map status numeric to string
    $status_str = 'Pending';
    if ($r->rollnostatus == 1) {
        $status_str = 'Approved';
    } elseif ($r->rollnostatus == 2) {
        $status_str = 'Disapproved';
    }

    fputcsv($output, [
        $r->email,
        $r->firstname,
        $r->middlename,
        $r->lastname,
        $r->college_shortname,
        $r->admissionyear,
        $r->degreetype,
        $r->degree,
        $r->rollno,
        $r->rollnotype,
        $status_str
    ]);
}

$records->close();
fclose($output);
exit();
