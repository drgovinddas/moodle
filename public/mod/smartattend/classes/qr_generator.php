<?php
/**
 * Class to manage QR generation and rotation.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_smartattend;

defined('MOODLE_INTERNAL') || die();

class qr_generator {
    /**
     * Generates a new token for a session and saves it.
     */
    public static function rotate_token($sessionid) {
        global $DB;
        $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $token = random_string(32);
        // Expiry of 2 minutes (120 seconds) as per PRD.
        $expires = time() + 120; 
        
        $session->currentqrtoken = $token;
        $session->qrexpires = $expires;
        $DB->update_record('smartattend_sessions', $session);
        
        return [
            'token' => $token,
            'expires' => $expires
        ];
    }
}
