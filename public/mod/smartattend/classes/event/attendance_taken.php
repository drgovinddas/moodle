<?php
/**
 * Event for when an attendance is taken in Smart Attendance AI.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_smartattend\event;

class attendance_taken extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'smartattend_logs';
    }

    public function get_description() {
        return "User with id {$this->userid} had their attendance logged for session id {$this->other['sessionid']}.";
    }

    public static function get_name() {
        return 'Attendance taken';
    }

    public function get_url() {
        return new \moodle_url('/mod/smartattend/view.php', ['id' => $this->contextinstanceid]);
    }

    protected function validate_data() {
        if (empty($this->other['sessionid'])) {
            throw new \coding_exception('The event mod_smartattend\event\attendance_taken must specify sessionid.');
        }
        parent::validate_data();
    }
}
