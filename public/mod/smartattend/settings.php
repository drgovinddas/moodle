<?php
/**
 * Admin settings for the smartattend module.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtextarea(
        'smartattend_lecture_types',
        'Lecture Types and Colors',
        'Define lecture types and their hex colors. One per line. Format: Type|#hexcolor. Example: Practical|#10b981',
        "Practical|#10b981\nSDL|#8b5cf6\nECE|#f97316\nDrawing|#14b8a6\nDemo|#ef4444\nDissection|#475569\nIntegrated|#6366f1\nLecture|#3b82f6",
        PARAM_RAW,
        '50',
        '10'
    ));
}
