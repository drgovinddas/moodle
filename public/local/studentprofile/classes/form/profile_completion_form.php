<?php
/**
 * Form for student profile completion.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class profile_completion_form extends \moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('header', 'profileheader', get_string('completeprofile', 'local_studentprofile'));
        $mform->addElement('html', '<p>' . get_string('profilecompletiondesc', 'local_studentprofile') . '</p>');

        // --- Personal Name Section ---
        $mform->addElement('text', 'firstname', get_string('firstname', 'local_studentprofile'), ['maxlength' => 100, 'size' => 35]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');
        $mform->addRule('firstname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addHelpButton('firstname', 'firstname', 'local_studentprofile');

        $mform->addElement('text', 'middlename', get_string('middlename', 'local_studentprofile'), ['maxlength' => 100, 'size' => 35]);
        $mform->setType('middlename', PARAM_TEXT);
        $mform->addRule('middlename', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addHelpButton('middlename', 'middlename', 'local_studentprofile');

        $mform->addElement('text', 'lastname', get_string('lastname', 'local_studentprofile'), ['maxlength' => 100, 'size' => 35]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');
        $mform->addRule('lastname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addHelpButton('lastname', 'lastname', 'local_studentprofile');

        $mform->addElement('text', 'rollno', get_string('rollno', 'local_studentprofile'), ['maxlength' => 50, 'size' => 20]);
        $mform->setType('rollno', PARAM_TEXT);
        $mform->addRule('rollno', get_string('maximumchars', '', 50), 'maxlength', 50, 'client');
        $mform->addHelpButton('rollno', 'rollno', 'local_studentprofile');

        // --- College Selection ---
        // Build options from the DB-managed list.
        $colleges = $DB->get_records('local_studentprofile_colleges', null, 'sortorder ASC, collegename ASC');
        $collegeoptions = ['' => get_string('choosedots')];
        foreach ($colleges as $c) {
            $collegeoptions[$c->id] = $c->collegename . ' (' . $c->shortname . ')';
        }
        $collegeoptions['__custom__'] = get_string('othercollege', 'local_studentprofile');

        $mform->addElement(
            'autocomplete',
            'collegeid_key',
            get_string('collegename', 'local_studentprofile'),
            $collegeoptions,
            ['id' => 'id_collegeid_key']
        );
        $mform->addRule('collegeid_key', null, 'required', null, 'client');
        $mform->addHelpButton('collegeid_key', 'collegename', 'local_studentprofile');

        // Custom college name (shown via JS when "Other" is selected).
        $mform->addElement('text', 'customcollegename', get_string('customcollegename', 'local_studentprofile'), ['maxlength' => 255, 'size' => 50, 'id' => 'id_customcollegename']);
        $mform->setType('customcollegename', PARAM_TEXT);
        $mform->addRule('customcollegename', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('customcollegename', 'customcollegename', 'local_studentprofile');
        // Hidden by default; shown by JS.
        $mform->hideIf('customcollegename', 'collegeid_key', 'neq', '__custom__');

        // --- Admission Year ---
        $yearrange = explode('-', get_config('local_studentprofile', 'admissionyears'));
        $startyear = isset($yearrange[0]) ? (int)trim($yearrange[0]) : 2010;
        $endyear   = isset($yearrange[1]) ? (int)trim($yearrange[1]) : 2030;
        $yearoptions = ['' => get_string('choosedots')];
        for ($i = $endyear; $i >= $startyear; $i--) {
            $yearoptions[$i] = $i;
        }
        $mform->addElement('select', 'admissionyear', get_string('admissionyear', 'local_studentprofile'), $yearoptions);
        $mform->addRule('admissionyear', null, 'required', null, 'client');
        $mform->addHelpButton('admissionyear', 'admissionyear', 'local_studentprofile');

        // --- Degree Type (UG / PG) ---
        $typeoptions = [
            ''   => get_string('choosedots'),
            'UG' => get_string('ugpg_ug', 'local_studentprofile'),
            'PG' => get_string('ugpg_pg', 'local_studentprofile'),
        ];
        $mform->addElement('select', 'degreetype', get_string('degreetype', 'local_studentprofile'), $typeoptions, ['id' => 'id_degreetype_select']);
        $mform->addRule('degreetype', null, 'required', null, 'client');
        $mform->addHelpButton('degreetype', 'degreetype', 'local_studentprofile');

        // --- Degree ---
        $degrees = $DB->get_records('local_studentprofile_degrees', null, 'sortorder ASC, degreename ASC');
        $degreeoptions = ['' => get_string('choosedots')];
        foreach ($degrees as $d) {
            $degreeoptions[$d->degreename] = $d->degreename;
        }
        // Fallback to config if no DB degrees exist yet.
        if (count($degrees) === 0) {
            $rawdegrees = explode("\n", str_replace("\r", "", (string)get_config('local_studentprofile', 'degrees')));
            foreach ($rawdegrees as $deg) {
                $deg = trim($deg);
                if (!empty($deg)) {
                    $degreeoptions[$deg] = $deg;
                }
            }
        }
        $mform->addElement('select', 'degree', get_string('degree', 'local_studentprofile'), $degreeoptions, ['id' => 'id_degree_select']);
        $mform->addRule('degree', null, 'required', null, 'client');
        $mform->addHelpButton('degree', 'degree', 'local_studentprofile');

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // Validate college selection.
        $key = $data['collegeid_key'] ?? '';
        if ($key === '__custom__') {
            $custom = clean_param(trim($data['customcollegename'] ?? ''), PARAM_TEXT);
            if (empty($custom)) {
                $errors['customcollegename'] = get_string('required');
            }
        } elseif (!empty($key)) {
            // Validate the college ID is from the real list.
            $cid = (int)$key;
            if ($cid <= 0 || !$DB->record_exists('local_studentprofile_colleges', ['id' => $cid])) {
                $errors['collegeid_key'] = get_string('errorinvalidcollege', 'local_studentprofile');
            }
        }

        // Validate degree type.
        $allowed_types = ['UG', 'PG'];
        if (!in_array($data['degreetype'] ?? '', $allowed_types)) {
            $errors['degreetype'] = get_string('required');
        }

        // Validate admission year range.
        $yearrange = explode('-', (string)get_config('local_studentprofile', 'admissionyears'));
        $startyear = isset($yearrange[0]) ? (int)trim($yearrange[0]) : 2010;
        $endyear   = isset($yearrange[1]) ? (int)trim($yearrange[1]) : 2030;
        $year = (int)($data['admissionyear'] ?? 0);
        if ($year < $startyear || $year > $endyear) {
            $errors['admissionyear'] = get_string('errorinvalidyear', 'local_studentprofile');
        }

        return $errors;
    }
}
