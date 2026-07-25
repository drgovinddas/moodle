<?php
/**
 * Scheduled task to autogenerate sessions based on schedule.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_smartattend\task;

defined('MOODLE_INTERNAL') || die();

class create_sessions extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcreatesessions', 'mod_smartattend');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        
        $todays_day_of_week = date('D'); // e.g. 'Mon', 'Sat'
        $today_start = strtotime('today midnight'); // unix timestamp of start of today
        
        $instances = $DB->get_records('smartattend');
        
        foreach ($instances as $instance) {
            if (empty($instance->schedule)) {
                continue;
            }
            
            $schedule = json_decode($instance->schedule, true);
            if (!$schedule || !isset($schedule[$todays_day_of_week])) {
                continue;
            }
            
            foreach ($schedule[$todays_day_of_week] as $slot) {
                // slot could be "09:00-10:00"
                list($st, $et) = explode('-', $slot);
                
                $start_ts = strtotime("today $st");
                $end_ts = strtotime("today $et");
                
                // Check if session already exists for this instance and starttime
                $exists = $DB->record_exists('smartattend_sessions', array(
                    'smartattendid' => $instance->id,
                    'starttime' => $start_ts
                ));
                
                if (!$exists) {
                    $session = new \stdClass();
                    $session->smartattendid = $instance->id;
                    $session->starttime = $start_ts;
                    $session->endtime = $end_ts;
                    $session->timecreated = time();
                    
                    $DB->insert_record('smartattend_sessions', $session);
                }
            }
        }
    }
}
