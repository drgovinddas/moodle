<?php
/**
 * Hook callbacks for local_studentprofile.
 *
 * Migrates the legacy after_config callback to Moodle 5.x hook system.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$callbacks = [
    [
        'hook'     => \core\hook\after_config::class,
        'callback' => [\local_studentprofile\hook\after_config::class, 'callback'],
    ],
];
