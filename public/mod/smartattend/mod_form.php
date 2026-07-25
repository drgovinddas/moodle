<?php
/**
 * Settings form for the smartattend module.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_smartattend_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $OUTPUT;
        
        $mform = $this->_form;
        
        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        // Name.
        $mform->addElement('text', 'name', get_string('name', 'mod_smartattend'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        
        // Intro.
        $this->standard_intro_elements();
        
        // Module specific settings.
        $mform->addElement('header', 'settings', get_string('settings', 'mod_smartattend'));
        
        $mform->addElement('advcheckbox', 'allowface', get_string('allowface', 'mod_smartattend'));
        $mform->addHelpButton('allowface', 'allowface', 'mod_smartattend');
        $mform->setDefault('allowface', 1);

        $mform->addElement('textarea', 'timeslots', 'Time Slots Configuration', 'wrap="virtual" rows="8" cols="50"');
        $mform->setType('timeslots', PARAM_TEXT);
        $mform->setDefault('timeslots', "09|9 to 10\n10|10 to 11\n11|11 to 12\n12|12 to 1\n13|LB\n14|2 to 3\n15|3 to 4\n16|4 to 5");
        
        $mform->addElement('select', 'spanbehavior', 'Session Spanning Behavior', [
            'start_only' => 'Show in Start Column Only',
            'duplicate' => 'Duplicate across Spanned Columns'
        ]);
        $mform->setType('spanbehavior', PARAM_ALPHANUMEXT);
        $mform->setDefault('spanbehavior', 'start_only');

        global $DB;
        if (!empty($this->current->id) && $DB->record_exists('smartattend_sessions', ['smartattendid' => $this->current->id])) {
            $mform->freeze('timeslots');
            $mform->freeze('spanbehavior');
            $mform->setConstant('timeslots', $this->current->timeslots);
            $mform->setConstant('spanbehavior', $this->current->spanbehavior);
        }

        // Standard course module elements.
        $this->standard_coursemodule_elements();

        // Standard buttons.
        $this->add_action_buttons();
    }
}
