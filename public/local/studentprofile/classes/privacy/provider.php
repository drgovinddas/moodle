<?php
/**
 * Privacy provider for local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        return $collection->add_database_table('local_studentprofile_data', [
            'userid' => 'privacy:metadata:userid',
            'collegename' => 'privacy:metadata:collegename',
            'fullname' => 'privacy:metadata:fullname',
            'shortname' => 'privacy:metadata:shortname',
            'admissionyear' => 'privacy:metadata:admissionyear',
            'degree' => 'privacy:metadata:degree',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:tableexplanation');
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_studentprofile_data} sp
               JOIN {context} ctx ON ctx.instanceid = sp.userid AND ctx.contextlevel = :contextlevel
              WHERE sp.userid = :userid",
            [
                'userid' => $userid,
                'contextlevel' => CONTEXT_USER,
            ]
        );
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {local_studentprofile_data} WHERE userid = :userid",
            ['userid' => $context->instanceid]
        );
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $user->id) {
                $record = $DB->get_record('local_studentprofile_data', ['userid' => $user->id]);
                if ($record) {
                    $data = (object)[
                        'collegename' => $record->collegename,
                        'fullname' => $record->fullname,
                        'shortname' => $record->shortname,
                        'admissionyear' => $record->admissionyear,
                        'degree' => $record->degree,
                        'timecreated' => transform_datetime($record->timecreated),
                        'timemodified' => transform_datetime($record->timemodified),
                    ];
                    \core_privacy\local\request\writer::with_context($context)->export_data(
                        [get_string('pluginname', 'local_studentprofile')],
                        $data
                    );
                }
            }
        }
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('local_studentprofile_data', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $DB->delete_records('local_studentprofile_data', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $user->id) {
                $DB->delete_records('local_studentprofile_data', ['userid' => $user->id]);
            }
        }
    }
}
