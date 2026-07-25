<?php
/**
 * Mobile configuration for smartattend
 *
 * @package    mod_smartattend
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = array(
    'mod_smartattend' => array(
        'handlers' => array(
            'smartattend' => array(
                'displaydata' => array(
                    'icon' => $CFG->wwwroot . '/mod/smartattend/pix/icon.svg',
                    'class' => '',
                ),
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
            )
        )
    )
);
