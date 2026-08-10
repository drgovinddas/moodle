<?php
/**
 * Version details.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026080800; // The current module version (Date: YYYYMMDDXX).
$plugin->requires  = 2022041900; // Requires this Moodle version (Moodle 4.0+).
$plugin->component = 'mod_smartattend'; // Full name of the plugin (used for diagnostics).
$plugin->cron      = 0;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0'; // Pure-PHP face recognition via pHash (no Python dependency).
