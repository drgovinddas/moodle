<?php
/**
 * Web service definitions for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // AJAX table rendering (existing).
    'local_studentprofile_get_participants_table' => [
        'classname'   => 'local_studentprofile\external',
        'methodname'  => 'get_participants_table',
        'description' => 'Get the filtered participants table HTML.',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    // Auto-save draft (new).
    'local_studentprofile_save_draft' => [
        'classname'     => 'local_studentprofile\external\save_draft',
        'methodname'    => 'execute',
        'description'   => 'Auto-save a draft of the student profile completion form.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_studentprofile_update_rollno' => [
        'classname'     => 'local_studentprofile\external\update_rollno',
        'methodname'    => 'execute',
        'description'   => 'Update student roll number and roll number type.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
