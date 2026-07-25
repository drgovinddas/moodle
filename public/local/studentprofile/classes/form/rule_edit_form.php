<?php
/**
 * Form for creating/editing a rule.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class rule_edit_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $rule = $this->_customdata['rule'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Name
        $mform->addElement('text', 'rulename', get_string('rulename', 'local_studentprofile'), ['size' => '50']);
        $mform->setType('rulename', PARAM_TEXT);
        $mform->addRule('rulename', get_string('required'), 'required', null, 'client');

        // Status
        $mform->addElement('select', 'status', get_string('status', 'local_studentprofile'), [
            1 => 'Active',
            0 => 'Inactive'
        ]);
        $mform->setType('status', PARAM_INT);

        // Priority
        $mform->addElement('text', 'priority', get_string('priority', 'local_studentprofile'), ['size' => '5']);
        $mform->setType('priority', PARAM_INT);
        $mform->setDefault('priority', 0);

        // Courses - Autocomplete
        $courses = $DB->get_records_menu('course', [], 'fullname ASC', 'id, fullname');
        if (isset($courses[SITEID])) {
            unset($courses[SITEID]);
        }
        $mform->addElement('autocomplete', 'courses', get_string('courses', 'local_studentprofile'), $courses, [
            'multiple' => true,
        ]);

        // Stop Processing
        $mform->addElement('advcheckbox', 'stopprocessing', get_string('stopprocessing', 'local_studentprofile'), get_string('stopprocessing_help', 'local_studentprofile'), null, [0, 1]);
        $mform->setDefault('stopprocessing', 0);

        // Hidden field to store JSON conditions from JS builder.
        $mform->addElement('hidden', 'conditions');
        $mform->setType('conditions', PARAM_RAW);
        
        // Placeholder for JS UI
        $mform->addElement('html', '<div id="rule-builder-container" class="my-4 border p-3 bg-light">Loading Condition Builder...</div>');

        // Buttons
        $this->add_action_buttons(true, get_string('savechanges'));

        $this->set_data($rule);
    }
}
