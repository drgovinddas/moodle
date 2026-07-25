<?php
/**
 * Hook callback for core\hook\after_config in local_studentprofile.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile\hook;

defined('MOODLE_INTERNAL') || die();

/**
 * Listener for the core after_config hook.
 */
class after_config {

    /**
     * Hook callback — replaces the legacy local_studentprofile_after_config() function.
     *
     * @param \core\hook\after_config $hook The hook instance.
     */
    public static function callback(\core\hook\after_config $hook): void {
        global $SESSION, $SCRIPT, $PAGE;

        if (
            (defined('AJAX_SCRIPT') && AJAX_SCRIPT) ||
            (defined('CLI_SCRIPT') && CLI_SCRIPT) ||
            defined('WEBSERVICE_PROGRAM')
        ) {
            return;
        }

        // 1. Force redirect to profile completion page if needed.
        if (!empty($SESSION->local_studentprofile_needs_completion) && isloggedin() && !isguestuser()) {
            $completeurl = '/local/studentprofile/complete.php';
            $logouturl   = '/login/logout.php';

            if ($SCRIPT !== $completeurl && $SCRIPT !== $logouturl) {
                redirect(new \moodle_url($completeurl));
            }
        }

        // 2. Show "Resume profile completion" banner on dashboard for users with a draft.
        if (
            !empty($SESSION->local_studentprofile_has_draft) &&
            isloggedin() &&
            !isguestuser() &&
            strpos($SCRIPT, '/my/') !== false
        ) {
            // Inject resume notice via Moodle notification — uses safe output, no raw HTML from user.
            $completeurl = new \moodle_url('/local/studentprofile/complete.php');
            $linktag = \html_writer::link($completeurl, get_string('draftresumelink', 'local_studentprofile'));
            $message = get_string('draftresumebanner', 'local_studentprofile', $completeurl->out(false));
            // Use Moodle's notification system (output is escaped by the renderer).
            \core\notification::warning($message);
        }

        // 3. Redirect /user/index.php to our custom participants page.
        if (strpos($SCRIPT, '/user/index.php') !== false) {
            $courseid = optional_param('id', 0, PARAM_INT);
            if ($courseid) {
                redirect(new \moodle_url('/local/studentprofile/participants.php', ['id' => $courseid]));
            }
        }
    }
}
