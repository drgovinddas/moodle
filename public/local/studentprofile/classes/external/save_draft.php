<?php
/**
 * External web service for auto-saving profile draft.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class save_draft extends \external_api {

    /**
     * Describe parameters for save_draft.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'firstname'        => new \external_value(PARAM_TEXT, 'First name', VALUE_DEFAULT, ''),
            'middlename'       => new \external_value(PARAM_TEXT, 'Middle name', VALUE_DEFAULT, ''),
            'lastname'         => new \external_value(PARAM_TEXT, 'Last name', VALUE_DEFAULT, ''),
            'collegeid_key'    => new \external_value(PARAM_TEXT, 'College selection key', VALUE_DEFAULT, ''),
            'customcollegename'=> new \external_value(PARAM_TEXT, 'Custom college name', VALUE_DEFAULT, ''),
            'admissionyear'    => new \external_value(PARAM_INT, 'Admission year', VALUE_DEFAULT, 0),
            'degree'           => new \external_value(PARAM_TEXT, 'Degree name', VALUE_DEFAULT, ''),
            'degreetype'       => new \external_value(PARAM_ALPHA, 'Degree type UG/PG', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save a draft of the profile form.
     *
     * @param string $firstname
     * @param string $middlename
     * @param string $lastname
     * @param string $collegeid_key
     * @param string $customcollegename
     * @param int    $admissionyear
     * @param string $degree
     * @param string $degreetype
     * @return array
     */
    public static function execute(
        string $firstname = '',
        string $middlename = '',
        string $lastname = '',
        string $collegeid_key = '',
        string $customcollegename = '',
        int    $admissionyear = 0,
        string $degree = '',
        string $degreetype = ''
    ): array {
        global $DB, $USER;

        // Authorization: only the logged-in user can save their own draft.
        $params = self::validate_parameters(self::execute_parameters(), [
            'firstname'         => $firstname,
            'middlename'        => $middlename,
            'lastname'          => $lastname,
            'collegeid_key'     => $collegeid_key,
            'customcollegename' => $customcollegename,
            'admissionyear'     => $admissionyear,
            'degree'            => $degree,
            'degreetype'        => $degreetype,
        ]);

        self::validate_context(\context_system::instance());

        if (isguestuser() || !isloggedin()) {
            return ['success' => false, 'message' => 'Not authenticated'];
        }

        // Sanitize all inputs before storing as JSON.
        $draftdata = [
            'firstname'         => clean_param($params['firstname'], PARAM_TEXT),
            'middlename'        => clean_param($params['middlename'], PARAM_TEXT),
            'lastname'          => clean_param($params['lastname'], PARAM_TEXT),
            'collegeid_key'     => clean_param($params['collegeid_key'], PARAM_TEXT),
            'customcollegename' => clean_param($params['customcollegename'], PARAM_TEXT),
            'admissionyear'     => (int)$params['admissionyear'],
            'degree'            => clean_param($params['degree'], PARAM_TEXT),
            'degreetype'        => clean_param($params['degreetype'], PARAM_ALPHA),
        ];

        $now = time();
        $existing = $DB->get_record('local_studentprofile_data', ['userid' => $USER->id]);

        if ($existing && $existing->draft == 0) {
            // Profile already completed — do not overwrite with draft.
            return ['success' => false, 'message' => 'Profile already completed'];
        }

        $record = new \stdClass();
        $record->userid        = $USER->id;
        $record->draft         = 1;
        $record->draftdata     = json_encode($draftdata, JSON_UNESCAPED_UNICODE);
        $record->timemodified  = $now;
        // Minimal required NOT NULL fields.
        $record->collegename   = $draftdata['customcollegename'] ?: $draftdata['collegeid_key'];
        $record->fullname      = trim(implode(' ', array_filter([
            $draftdata['firstname'], $draftdata['middlename'], $draftdata['lastname']
        ])));
        $record->shortname     = '';
        $record->admissionyear = $draftdata['admissionyear'] ?: 0;
        $record->degree        = $draftdata['degree'] ?: '';

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_studentprofile_data', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_studentprofile_data', $record);
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Describe returns for save_draft.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the draft was saved'),
            'message' => new \external_value(PARAM_TEXT, 'Error message if any'),
        ]);
    }
}
