<?php
/**
 * Form for adding/editing a college entry.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class college_form extends \moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Short Name.
        $mform->addElement('text', 'shortname', get_string('collegeshortname', 'local_studentprofile'), ['maxlength' => 50, 'size' => 15]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 50), 'maxlength', 50, 'client');
        $mform->addHelpButton('shortname', 'collegeshortname', 'local_studentprofile');

        // College Name (Full Display Name).
        $mform->addElement('text', 'collegename', get_string('collegename', 'local_studentprofile'), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('collegename', PARAM_TEXT);
        $mform->addRule('collegename', null, 'required', null, 'client');
        $mform->addRule('collegename', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('collegename', 'collegename', 'local_studentprofile');


        // Degrees multi-select autocomplete based on local_studentprofile_degrees table.
        global $DB;
        $dbdegrees = $DB->get_records('local_studentprofile_degrees', null, 'sortorder ASC, degreename ASC');
        $degreeoptions = [];
        foreach ($dbdegrees as $d) {
            $degreeoptions[$d->degreename] = $d->degreename;
        }

        $mform->addElement(
            'autocomplete',
            'degrees',
            get_string('degrees', 'local_studentprofile'),
            $degreeoptions,
            ['multiple' => true, 'placeholder' => get_string('all')]
        );
        $mform->addHelpButton('degrees', 'degrees', 'local_studentprofile');

        // Sort Order.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_studentprofile'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
    }

    /**
     * Validation.
     *
     * @param array $data  Form data.
     * @param array $files Uploaded files.
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // Ensure shortname is unique (case-insensitive).
        $shortname = clean_param(trim($data['shortname']), PARAM_TEXT);
        if (!empty($shortname)) {
            $conditions = ['shortname' => $shortname];
            $existing = $DB->get_record('local_studentprofile_colleges', $conditions);
            if ($existing && (empty($data['id']) || $existing->id != (int)$data['id'])) {
                $errors['shortname'] = get_string('duplicate') . ' ' . get_string('collegeshortname', 'local_studentprofile');
            }
        }
        return $errors;
    }
}
