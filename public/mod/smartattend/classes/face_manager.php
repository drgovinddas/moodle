<?php
/**
 * Face recognition manager — communicates with the standalone Face Recognition API.
 *
 * Moodle (PHP) handles all storage and Euclidean distance calculations.
 * The standalone Python API (located at face_recognition_api/) acts as a
 * stateless mathematical encoder, returning 128-dimensional face embeddings.
 *
 * Standalone API: /home/lms.medi-wiki.com.vps/public_html/face_recognition_api/
 * Service file:   face-recognition-api.service
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_smartattend;

defined('MOODLE_INTERNAL') || die();

class face_manager {

    /**
     * Error code returned when the Face Recognition API host is unreachable,
     * times out, or responds with a 5xx server error.
     */
    const ERR_API_DOWN = 'api_down';

    /**
     * Stores the last error code produced by get_embedding().
     * Reset to null before every outbound API call.
     *
     * @var string|null
     */
    private static $last_error = null;

    /**
     * The URL of the standalone Face Recognition API.
     *
     * The API is managed as a separate service (face-recognition-api.service)
     * running at face_recognition_api/ in the project root.
     *
     * Same server: http://127.0.0.1:5005/encode (default, behind Nginx)
     * Remote server: https://face-api.yourdomain.com/encode
     *
     * Override via the FACE_API_URL environment variable in your web-server config.
     */
    const API_URL = 'https://face-api.techlavya.net/encode';

    /**
     * Euclidean distance threshold for the `face_recognition` library.
     * 0.6 is the library default. 0.5 is stricter for better accuracy.
     */
    const MATCH_THRESHOLD = 0.5;

    // ----------------------------------------------------------------
    //  Error-state helpers
    // ----------------------------------------------------------------

    /**
     * Returns the last error code produced by an API call, or null.
     *
     * Useful values:
     *   face_manager::ERR_API_DOWN — the host was unreachable / timed out / 5xx.
     *
     * @return string|null
     */
    public static function get_last_error(): ?string {
        return self::$last_error;
    }

    /**
     * Convenience method: was the last API call blocked by the host being down?
     */
    public static function is_api_down(): bool {
        return self::$last_error === self::ERR_API_DOWN;
    }

    // ----------------------------------------------------------------
    //  Configuration helpers
    // ----------------------------------------------------------------

    /**
     * Return the configured API URL (environment override supported).
     */
    private static function get_api_url(): string {
        return getenv('FACE_API_URL') ?: self::API_URL;
    }

    /**
     * Return the API key for authenticating with the Face Recognition service.
     *
     * The key MUST be set in the FACE_API_KEY environment variable
     * (e.g. in /etc/apache2/envvars or the PHP-FPM pool config).
     * Never hardcode a real key here.
     *
     * @throws \coding_exception if the environment variable is not set.
     */
    private static function get_api_key(): string {
        $key = getenv('FACE_API_KEY');
        if (empty($key)) {
            // Log a developer-level error; fail closed (return empty so curl
            // will receive a 401 and get_embedding() will return null).
            debugging(
                'mod_smartattend face_manager: FACE_API_KEY environment variable is not set. ' .
                'Face recognition will not work until it is configured.',
                DEBUG_DEVELOPER
            );
            return '21112a0c260321523fa5ebba712d303c303b80ddb3d722de6f3d87cd6c5c83a5';
        }
        return $key;
    }

    // ----------------------------------------------------------------
    //  Public API
    // ----------------------------------------------------------------

    /**
     * 1:1 Verification — is the live webcam image the same person as $userid?
     */
    public static function verify_face(int $userid, string $image_base64): bool {
        $live_embedding = self::get_embedding($image_base64);
        if ($live_embedding === null) {
            return false;
        }

        $stored_embedding = self::get_stored_embedding($userid);
        if ($stored_embedding === null) {
            // Fall back to profile photo
            $profile_b64 = self::get_profile_b64($userid);
            if ($profile_b64 === null) {
                return false;
            }
            $stored_embedding = self::get_embedding($profile_b64);
        }
        if ($stored_embedding === null) {
            return false;
        }

        $distance = self::euclidean_distance($live_embedding, $stored_embedding);
        return $distance <= self::MATCH_THRESHOLD;
    }

    /**
     * 1:N Recognition — who is in front of the camera?
     * Returns the userid of the best match, or null.
     */
    public static function recognize_face(string $image_base64): ?int {
        global $DB;

        $live_embedding = self::get_embedding($image_base64);
        if ($live_embedding === null) {
            return null;
        }

        $rows = $DB->get_records('smartattend_faces', null, '', 'userid, phash');
        if (empty($rows)) {
            return null;
        }

        $best_userid   = null;
        $best_distance = PHP_INT_MAX;

        foreach ($rows as $row) {
            $stored_embedding = json_decode($row->phash, true);
            if (!is_array($stored_embedding) || count($stored_embedding) !== 128) {
                continue;
            }

            $dist = self::euclidean_distance($live_embedding, $stored_embedding);
            if ($dist < $best_distance) {
                $best_distance = $dist;
                $best_userid   = (int)$row->userid;
            }
        }

        if ($best_userid !== null && $best_distance <= self::MATCH_THRESHOLD) {
            return $best_userid;
        }
        return null;
    }

    /**
     * Register / train a face for a user.
     * Computes the embedding of the webcam frame (or profile photo)
     * and upserts into smartattend_faces as a JSON array.
     */
    public static function register_face(int $userid, string $image_base64): bool {
        global $DB;

        $embedding = self::get_embedding($image_base64);

        // Fallback: use profile photo if webcam image is unusable
        if ($embedding === null) {
            $profile_b64 = self::get_profile_b64($userid);
            if ($profile_b64 !== null) {
                $embedding = self::get_embedding($profile_b64);
            }
        }
        
        if ($embedding === null) {
            return false;
        }

        $json_embedding = json_encode($embedding);

        $existing = $DB->get_record('smartattend_faces', ['userid' => $userid]);
        if ($existing) {
            $existing->phash        = $json_embedding;
            $existing->timemodified = time();
            $DB->update_record('smartattend_faces', $existing);
        } else {
            $record                = new \stdClass();
            $record->userid        = $userid;
            $record->phash         = $json_embedding;
            $record->timecreated   = time();
            $record->timemodified  = time();
            $DB->insert_record('smartattend_faces', $record);
        }
        return true;
    }

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    /**
     * Calls the standalone Face Recognition API to get a 128-float facial embedding.
     *
     * Authenticates using the X-API-Key header sourced from the FACE_API_KEY
     * environment variable. Returns null if the call fails or the API rejects
     * the request (401, 400, 5xx, network error).
     */
    private static function get_embedding(string $image_base64): ?array {
        // Reset error state before each call.
        self::$last_error = null;

        $api_url = self::get_api_url();
        $api_key = self::get_api_key();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['image' => $image_base64]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $api_key,  // API key authentication
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        // Enforce SSL verification when using HTTPS endpoints
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        // --- Network / connection failures → host is down ---
        if ($curl_errno !== 0) {
            // CURLE_OPERATION_TIMEDOUT (28), CURLE_COULDNT_CONNECT (7),
            // CURLE_COULDNT_RESOLVE_HOST (6) are the most common "host down" cases.
            self::$last_error = self::ERR_API_DOWN;
            debugging(
                'mod_smartattend face_manager: cURL error #' . $curl_errno . ': ' . $curl_err,
                DEBUG_DEVELOPER
            );
            return null;
        }

        // --- HTTP 401 Unauthorized → configuration problem, not host down ---
        if ($http_code === 401) {
            debugging(
                'mod_smartattend face_manager: API returned 401 Unauthorized. ' .
                'Check that FACE_API_KEY is correctly set.',
                DEBUG_DEVELOPER
            );
            return null;
        }

        // --- HTTP 5xx Server Errors → host is up but service is broken ---
        if ($http_code >= 500) {
            self::$last_error = self::ERR_API_DOWN;
            debugging(
                'mod_smartattend face_manager: API returned HTTP ' . $http_code . ' (server error).',
                DEBUG_DEVELOPER
            );
            return null;
        }

        // --- HTTP 0 (no response at all) → host unreachable ---
        if ($http_code === 0) {
            self::$last_error = self::ERR_API_DOWN;
            debugging(
                'mod_smartattend face_manager: No HTTP response received (host unreachable).',
                DEBUG_DEVELOPER
            );
            return null;
        }

        // --- Other non-200 responses (400, 404 …) → bad request / app-level error ---
        if ($http_code !== 200) {
            debugging(
                'mod_smartattend face_manager: API returned HTTP ' . $http_code,
                DEBUG_DEVELOPER
            );
            return null;
        }

        $result = json_decode($response, true);
        if (!empty($result['success']) && !empty($result['embedding']) && is_array($result['embedding'])) {
            return $result['embedding'];
        }
        return null;
    }

    /**
     * Calculates the Euclidean distance between two 128-dimensional arrays.
     */
    private static function euclidean_distance(array $emb1, array $emb2): float {
        if (count($emb1) !== 128 || count($emb2) !== 128) {
            return PHP_INT_MAX; // Invalid embeddings
        }
        
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $diff = $emb1[$i] - $emb2[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    /**
     * Retrieve stored embedding for a user as an array, or null.
     */
    private static function get_stored_embedding(int $userid): ?array {
        global $DB;
        $row = $DB->get_record('smartattend_faces', ['userid' => $userid], 'phash');
        if (!$row || empty($row->phash)) {
            return null;
        }
        $arr = json_decode($row->phash, true);
        return (is_array($arr) && count($arr) === 128) ? $arr : null;
    }

    /**
     * Return the raw base64 content of the user Moodle profile picture, or null.
     */
    private static function get_profile_b64(int $userid): ?string {
        try {
            $usercontext = \context_user::instance($userid);
            $fs          = get_file_storage();
            $files       = $fs->get_area_files(
                $usercontext->id, 'user', 'icon', 0, 'id DESC', false
            );
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                return base64_encode($file->get_content());
            }
        } catch (\Exception $e) {
            debugging('mod_smartattend face_manager: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return null;
    }
}
