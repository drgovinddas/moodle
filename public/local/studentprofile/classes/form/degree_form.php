<?php
/**
 * Form for adding/editing a degree entry.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class degree_form extends \moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Degree Name.
        $mform->addElement('text', 'degreename', get_string('degreename', 'local_studentprofile'), ['maxlength' => 255, 'size' => 40]);
        $mform->setType('degreename', PARAM_TEXT);
        $mform->addRule('degreename', null, 'required', null, 'client');
        $mform->addRule('degreename', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Degree Type (UG / PG).
        $typeoptions = [
            'UG' => get_string('ugpg_ug', 'local_studentprofile'),
            'PG' => get_string('ugpg_pg', 'local_studentprofile'),
        ];
        $mform->addElement('select', 'degreetype', get_string('degreetype', 'local_studentprofile'), $typeoptions);
        $mform->setType('degreetype', PARAM_ALPHA);
        $mform->addRule('degreetype', null, 'required', null, 'client');
        $mform->addHelpButton('degreetype', 'degreetype', 'local_studentprofile');

        // Sort Order.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_studentprofile'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $allowed = ['UG', 'PG'];
        if (!in_array($data['degreetype'], $allowed)) {
            $errors['degreetype'] = get_string('errorinvaliddegree', 'local_studentprofile');
        }
        return $errors;
    }
}
