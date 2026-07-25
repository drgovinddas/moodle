<?php
/**
 * Events configuration for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_loggedin',
        'callback'    => '\local_studentprofile\observer::user_loggedin',
    ],
    [
        'eventname'   => '\local_studentprofile\event\profile_completed',
        'callback'    => '\local_studentprofile\observer::profile_completed',
    ],
];
