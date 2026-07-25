<?php
/**
 * Library functions for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The after_config logic has been migrated to local_studentprofile\hook\after_config::callback()
// registered via db/hooks.php. The legacy function is intentionally not defined here.

/**
 * Extend navigation - kept empty for backward compatibility.
 *
 * @param \global_navigation $navigation
 */
function local_studentprofile_extend_navigation(\global_navigation $navigation) {
    // Navigation extension not required since we redirect in after_config.
}

/**
 * Extend user navigation to add edit profile link.
 *
 * @param \navigation_node $navigation
 * @param \stdClass $user
 * @param \context_user $usercontext
 * @param \stdClass $course
 * @param \context_course $coursecontext
 */
function local_studentprofile_extend_navigation_user($navigation, $user, $usercontext, $course, $coursecontext) {
    global $USER;
    // Only show if the current user is looking at their own profile.
    if ($USER->id == $user->id) {
        $url = new \moodle_url('/local/studentprofile/complete.php');
        $navigation->add(get_string('editprofile', 'local_studentprofile'), $url, \navigation_node::TYPE_SETTING, null, 'local_studentprofile_edit');
    }
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param \stdClass $user user object
 * @param bool $iscurrentuser
 * @param \stdClass $course Course object
 */
function local_studentprofile_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if ($iscurrentuser) {
        $url = new \moodle_url('/local/studentprofile/complete.php');
        $node = new \core_user\output\myprofile\node('miscellaneous', 'editstudentprofile', get_string('editprofile', 'local_studentprofile'), null, $url);
        $tree->add_node($node);
    }
}
