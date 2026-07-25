<?php
/**
 * Event triggered when a student profile is completed.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\event;

defined('MOODLE_INTERNAL') || die();

class profile_completed extends \core\event\base {
    /**
     * Init event.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_studentprofile_data';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has completed their student profile.";
    }

    /**
     * Return legacy event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventprofilecompleted', 'local_studentprofile');
    }
}
