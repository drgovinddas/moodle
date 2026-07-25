<?php
/**
 * Event observer for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile;

defined('MOODLE_INTERNAL') || die();

class observer {
    /**
     * Observer for user_loggedin event.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $USER, $DB, $SESSION;

        // Skip guests and CLI.
        if (isguestuser() || is_siteadmin() || CLI_SCRIPT) {
            return;
        }

        // Check if the feature is enabled.
        if (!get_config('local_studentprofile', 'enablecompletion')) {
            return;
        }

        // Check for bypass capability.
        if (has_capability('local/studentprofile:bypasscompletion', \context_system::instance(), $USER->id)) {
            return;
        }

        // Check if the user has already completed the profile.
        $record = $DB->get_record('local_studentprofile_data', ['userid' => $USER->id]);

        if (!$record) {
            // No record at all — needs completion.
            $SESSION->local_studentprofile_needs_completion = true;
            $SESSION->local_studentprofile_has_draft = false;
        } elseif (isset($record->draft) && $record->draft == 1) {
            // Draft exists but not yet finalized.
            $SESSION->local_studentprofile_needs_completion = true;
            $SESSION->local_studentprofile_has_draft = true;
        }
        // If draft == 0 and record exists, profile is complete — do nothing.
    }

    /**
     * Observer for profile_completed event.
     * Evaluates course assignment rules.
     *
     * @param \local_studentprofile\event\profile_completed $event
     */
    public static function profile_completed(\local_studentprofile\event\profile_completed $event) {
        global $DB;

        $userid = $event->objectid;
        if (empty($userid)) {
            return;
        }

        // Check if user exists and is not deleted.
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            return;
        }

        // Fetch student profile data.
        $studentdata = $DB->get_record('local_studentprofile_data', ['userid' => $userid]);
        if (!$studentdata) {
            return;
        }

        // Fetch active rules ordered by priority ascending.
        $rules = $DB->get_records('local_studentprofile_rules', ['status' => 1], 'priority ASC, id ASC');
        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            // Evaluate conditions using rule engine.
            $match = \local_studentprofile\rule_engine::evaluate((string)$rule->conditions, clone $studentdata);
            
            if ($match) {
                // Rule matched, assign courses.
                $courses = @json_decode($rule->courses, true);
                if (!empty($courses) && is_array($courses)) {
                    self::assign_courses($userid, $courses);
                }

                // Check if we should stop processing further rules.
                if ($rule->stopprocessing == 1) {
                    break;
                }
            }
        }
    }

    /**
     * Assigns courses to a user using manual enrol plugin.
     *
     * @param int $userid
     * @param array $courseids
     */
    private static function assign_courses(int $userid, array $courseids) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/enrol/locallib.php');

        $manualplugin = enrol_get_plugin('manual');
        if (!$manualplugin) {
            return; // Manual enrolment plugin is disabled or missing.
        }

        // Standard student role.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MULTIPLE);
        if (!$studentroleid) {
            $studentroleid = 5; // Fallback to standard Moodle student ID if custom modified.
        }

        foreach ($courseids as $courseid) {
            $courseid = (int)$courseid;
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }

            // Find or create manual enrol instance for the course.
            $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
            if (!$instance) {
                $instanceid = $manualplugin->add_instance($course);
                $instance = $DB->get_record('enrol', ['id' => $instanceid]);
            }

            if ($instance) {
                // Check if already enrolled to avoid duplicated enrolments.
                $is_enrolled = $DB->record_exists('user_enrolments', [
                    'enrolid' => $instance->id,
                    'userid'  => $userid,
                ]);

                if (!$is_enrolled) {
                    $manualplugin->enrol_user($instance, $userid, $studentroleid);
                }
            }
        }
    }
}
