<?php
/**
 * External API for updating roll numbers.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class update_rollno extends \external_api {
    
    public static function execute_parameters() {
        return new \external_function_parameters([
            'userid'     => new \external_value(PARAM_INT, 'User ID'),
            'rollno'       => new \external_value(PARAM_TEXT, 'The roll number to assign', VALUE_DEFAULT, ''),
            'rollnotype'   => new \external_value(PARAM_TEXT, 'The type of roll number (temporary or permanent)', VALUE_DEFAULT, 'permanent'),
            'rollnostatus' => new \external_value(PARAM_INT, 'The status of the roll number (0=Pending, 1=Approved, 2=Disapproved)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute($userid, $rollno = '', $rollnotype = 'permanent', $rollnostatus = 0) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid'       => $userid,
            'rollno'       => $rollno,
            'rollnotype'   => $rollnotype,
            'rollnostatus' => $rollnostatus,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check. The user asked for "site administrator only" for now.
        if (!is_siteadmin()) {
            throw new \moodle_exception('nopermissions', 'error', '', 'Only site administrators can assign roll numbers.');
        }

        // Validate the rollnotype
        $rollnotype = trim(strtolower($params['rollnotype']));
        if (!in_array($rollnotype, ['temporary', 'permanent'])) {
            $rollnotype = 'permanent';
        }

        // Validate the rollnostatus
        $rollnostatus = (int)$params['rollnostatus'];
        if (!in_array($rollnostatus, [0, 1, 2])) {
            $rollnostatus = 0;
        }

        $rollno = trim($params['rollno']);

        // Check if the student profile record exists
        if ($record = $DB->get_record('local_studentprofile_data', ['userid' => $params['userid']])) {
            $record->rollno = $rollno;
            $record->rollnotype = $rollnotype;
            $record->rollnostatus = $rollnostatus;
            $record->timemodified = time();
            $DB->update_record('local_studentprofile_data', $record);
        } else {
            // It's possible the user hasn't filled their profile yet. We can create a partial record.
            $newrecord = new \stdClass();
            $newrecord->userid = $params['userid'];
            $newrecord->collegename = '';
            $newrecord->fullname = '';
            $newrecord->shortname = '';
            $newrecord->admissionyear = 0;
            $newrecord->degree = '';
            $newrecord->draft = 1;
            $newrecord->rollno = $rollno;
            $newrecord->rollnotype = $rollnotype;
            $newrecord->rollnostatus = $rollnostatus;
            $newrecord->timecreated = time();
            $newrecord->timemodified = time();
            $DB->insert_record('local_studentprofile_data', $newrecord);
        }

        return ['success' => true];
    }

    public static function execute_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'True if successful'),
        ]);
    }
}
