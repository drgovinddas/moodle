<?php
/**
 * Form for importing student profiles from CSV.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class import_profiles_form extends \moodleform {

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'importheader', get_string('importprofiles', 'local_studentprofile'));
        $mform->addElement('html', '<p>' . get_string('importprofiles_desc', 'local_studentprofile') . '</p>');

        // The filepicker element.
        $mform->addElement('filepicker', 'importfile', get_string('file'), null, ['accepted_types' => ['.csv']]);
        $mform->addRule('importfile', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('importprofiles', 'local_studentprofile'));
    }
}
