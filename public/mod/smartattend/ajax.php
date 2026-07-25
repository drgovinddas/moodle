<?php
/**
 * AJAX endpoint for QR scanning and face submission.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

$action = required_param('action', PARAM_ALPHANUMEXT);

require_login();

// Required to bypass sesskey for some APIs? We should check sesskey for good practice.
require_sesskey();

if ($action === 'scan_qr') {
    $token = required_param('token', PARAM_ALPHANUMEXT);
    
    // Find valid session for this token
    $session = $DB->get_record('smartattend_sessions', ['currentqrtoken' => $token]);
    
    if (!$session) {
        echo json_encode(['error' => 'Invalid QR token.']);
        die;
    }
    
    if (time() > $session->qrexpires) {
        echo json_encode(['error' => 'QR token expired.']);
        die;
    }
    
    $smartattend = $DB->get_record('smartattend', ['id' => $session->smartattendid], '*', MUST_EXIST);
    
    // Check capability
    $cm = get_coursemodule_from_instance('smartattend', $smartattend->id);
    require_capability('mod/smartattend:scanqr', \context_module::instance($cm->id));
    
    // Log attendance
    $existing = $DB->get_record('smartattend_logs', ['sessionid' => $session->id, 'userid' => $USER->id]);
    if (!$existing) {
        $log = new stdClass();
        $log->sessionid = $session->id;
        $log->userid = $USER->id;
        $log->status = 'present';
        $log->method = 'qr';
        $log->timecreated = time();
        $log->id = $DB->insert_record('smartattend_logs', $log);
        
        $event = \mod_smartattend\event\attendance_taken::create([
            'objectid' => $log->id,
            'context' => \context_module::instance($cm->id),
            'other' => ['sessionid' => $session->id]
        ]);
        $event->trigger();
    } else {
        $existing->status = 'present';
        $existing->method = 'qr';
        $DB->update_record('smartattend_logs', $existing);
    }
    
    echo json_encode(['success' => true, 'message' => 'Attendance logged successfully!']);
    die;
}

if ($action === 'verify_face') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $image = required_param('image', PARAM_RAW); // base64 string
    
    $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('smartattend', $session->smartattendid);
    require_capability('mod/smartattend:submitface', \context_module::instance($cm->id));
    
    $match = \mod_smartattend\face_manager::verify_face($USER->id, $image);
    
    // Check if failure was due to the API server being down.
    if (!$match && \mod_smartattend\face_manager::is_api_down()) {
        echo json_encode([
            'success'   => false,
            'api_down'  => true,
            'error'     => 'Face recognition service is currently unavailable. Please try again later or contact your administrator.',
        ]);
        die;
    }
    
    if ($match) {
        $existing = $DB->get_record('smartattend_logs', ['sessionid' => $session->id, 'userid' => $USER->id]);
        if (!$existing) {
            $log = new stdClass();
            $log->sessionid = $session->id;
            $log->userid = $USER->id;
            $log->status = 'present';
            $log->method = 'face';
            $log->timecreated = time();
            $log->id = $DB->insert_record('smartattend_logs', $log);
            
            $event = \mod_smartattend\event\attendance_taken::create([
                'objectid' => $log->id,
                'context' => \context_module::instance($cm->id),
                'other' => ['sessionid' => $session->id]
            ]);
            $event->trigger();
        } else {
            $existing->status = 'present';
            $existing->method = 'face';
            $DB->update_record('smartattend_logs', $existing);
        }
        echo json_encode(['success' => true, 'message' => 'Face verified! Attendance logged.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Face not recognized.']);
    }
    die;
}

if ($action === 'verify_face_as_teacher') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $studentid = required_param('studentid', PARAM_INT);
    $image = required_param('image', PARAM_RAW); // base64 string
    
    $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('smartattend', $session->smartattendid);
    require_capability('mod/smartattend:manage', \context_module::instance($cm->id));
    
    $match = \mod_smartattend\face_manager::verify_face($studentid, $image);
    
    // Check if failure was due to the API server being down.
    if (!$match && \mod_smartattend\face_manager::is_api_down()) {
        echo json_encode([
            'success'   => false,
            'api_down'  => true,
            'error'     => 'Face recognition service is currently unavailable. Please try again later or contact your administrator.',
        ]);
        die;
    }
    
    if ($match) {
        $existing = $DB->get_record('smartattend_logs', ['sessionid' => $session->id, 'userid' => $studentid]);
        if (!$existing) {
            $log = new stdClass();
            $log->sessionid = $session->id;
            $log->userid = $studentid;
            $log->status = 'present';
            $log->method = 'face_kiosk';
            $log->timecreated = time();
            $log->id = $DB->insert_record('smartattend_logs', $log);
            
            $event = \mod_smartattend\event\attendance_taken::create([
                'objectid' => $log->id,
                'context' => \context_module::instance($cm->id),
                'other' => ['sessionid' => $session->id]
            ]);
            $event->trigger();
        } else {
            $existing->status = 'present';
            $existing->method = 'face_kiosk';
            $DB->update_record('smartattend_logs', $existing);
        }
        $student_user = $DB->get_record('user', ['id' => $studentid], 'firstname, lastname');
        $name = $student_user ? fullname($student_user) : "Student";
        echo json_encode(['success' => true, 'message' => "Face verified for $name! Attendance logged."]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Face not recognized.']);
    }
    die;
}

if ($action === 'auto_scan_face') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $image = required_param('image', PARAM_RAW); // base64 string
    
    $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('smartattend', $session->smartattendid);
    require_capability('mod/smartattend:manage', \context_module::instance($cm->id));
    
    $studentid = \mod_smartattend\face_manager::recognize_face($image);
    
    // Check if failure was due to the API server being down.
    if (!$studentid && \mod_smartattend\face_manager::is_api_down()) {
        echo json_encode([
            'success'   => false,
            'api_down'  => true,
            'error'     => 'Face recognition service is currently unavailable. Please try again later or contact your administrator.',
        ]);
        die;
    }
    
    if ($studentid) {
        $existing = $DB->get_record('smartattend_logs', ['sessionid' => $session->id, 'userid' => $studentid]);
        if (!$existing) {
            $log = new stdClass();
            $log->sessionid = $session->id;
            $log->userid = $studentid;
            $log->status = 'present';
            $log->method = 'face_auto';
            $log->timecreated = time();
            $log->id = $DB->insert_record('smartattend_logs', $log);
            
            $event = \mod_smartattend\event\attendance_taken::create([
                'objectid' => $log->id,
                'context' => \context_module::instance($cm->id),
                'other' => ['sessionid' => $session->id]
            ]);
            $event->trigger();
        } else {
            $existing->status = 'present';
            $existing->method = 'face_auto';
            $DB->update_record('smartattend_logs', $existing);
        }
        $user = $DB->get_record('user', ['id' => $studentid], 'firstname, lastname');
        echo json_encode(['success' => true, 'userid' => $studentid, 'name' => fullname($user)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Face not recognized.']);
    }
    die;
}

if ($action === 'train_and_log_face') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $studentid = required_param('studentid', PARAM_INT);
    $image = required_param('image', PARAM_RAW);
    
    $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('smartattend', $session->smartattendid);
    require_capability('mod/smartattend:manage', \context_module::instance($cm->id));
    
    $registered = \mod_smartattend\face_manager::register_face($studentid, $image);
    
    // Check if failure was due to the API server being down.
    if (!$registered && \mod_smartattend\face_manager::is_api_down()) {
        echo json_encode([
            'success'   => false,
            'api_down'  => true,
            'error'     => 'Face recognition service is currently unavailable. Training could not be completed. Please try again later.',
        ]);
        die;
    }
    
    $existing = $DB->get_record('smartattend_logs', ['sessionid' => $session->id, 'userid' => $studentid]);
    if (!$existing) {
        $log = new stdClass();
        $log->sessionid = $session->id;
        $log->userid = $studentid;
        $log->status = 'present';
        $log->method = 'face_trained';
        $log->timecreated = time();
        $log->id = $DB->insert_record('smartattend_logs', $log);
        
        $event = \mod_smartattend\event\attendance_taken::create([
            'objectid' => $log->id,
            'context' => \context_module::instance($cm->id),
            'other' => ['sessionid' => $session->id]
        ]);
        $event->trigger();
    } else {
        $existing->status = 'present';
        $existing->method = 'face_trained';
        $DB->update_record('smartattend_logs', $existing);
    }
    $user = $DB->get_record('user', ['id' => $studentid], 'firstname, lastname');
    echo json_encode(['success' => true, 'message' => 'Trained and logged for ' . fullname($user)]);
    die;
}

if ($action === 'generate_qr') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    
    $cm = get_coursemodule_from_instance('smartattend', $session->smartattendid);
    require_capability('mod/smartattend:manage', \context_module::instance($cm->id));
    
    $token_data = \mod_smartattend\qr_generator::rotate_token($sessionid);
    
    echo json_encode(['success' => true, 'token' => $token_data['token'], 'expires' => $token_data['expires']]);
    die;
}

if ($action === 'mark_all_present') {
    $sessionid = required_param('sessionid', PARAM_INT);

    $session    = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $smartattend = $DB->get_record('smartattend', ['id' => $session->smartattendid], '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('smartattend', $smartattend->id);
    $context    = \context_module::instance($cm->id);

    require_capability('mod/smartattend:manage', $context);

    // Get ALL enrolled participants (no capability filter = includes all roles)
    $students = get_enrolled_users($context, '', 0, 'u.id');
    $now      = time();

    foreach ($students as $student) {
        $existing = $DB->get_record('smartattend_logs', ['sessionid' => $sessionid, 'userid' => $student->id]);
        if ($existing) {
            // Update existing record to present
            $existing->status = 'present';
            $existing->method = 'bulk';
            $DB->update_record('smartattend_logs', $existing);
        } else {
            // Insert new record
            $log = new stdClass();
            $log->sessionid   = $sessionid;
            $log->userid      = $student->id;
            $log->status      = 'present';
            $log->method      = 'bulk';
            $log->timecreated = $now;
            $log->id = $DB->insert_record('smartattend_logs', $log);

            $event = \mod_smartattend\event\attendance_taken::create([
                'objectid' => $log->id,
                'context'  => $context,
                'other'    => ['sessionid' => $sessionid],
            ]);
            $event->trigger();
        }
    }

    $total = count($students);
    echo json_encode([
        'success' => true,
        'marked'  => $total,
        'total'   => $total,
        'message' => "$total student(s) marked present.",
    ]);
    die;
}

if ($action === 'mark_all_absent') {
    $sessionid = required_param('sessionid', PARAM_INT);

    $session    = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $smartattend = $DB->get_record('smartattend', ['id' => $session->smartattendid], '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('smartattend', $smartattend->id);
    $context    = \context_module::instance($cm->id);

    require_capability('mod/smartattend:manage', $context);

    // Get ALL enrolled participants (no capability filter = includes all roles)
    $students = get_enrolled_users($context, '', 0, 'u.id');
    $now      = time();

    foreach ($students as $student) {
        $existing = $DB->get_record('smartattend_logs', ['sessionid' => $sessionid, 'userid' => $student->id]);
        if ($existing) {
            // Update existing record to absent
            $existing->status = 'absent';
            $existing->method = 'bulk_absent';
            $DB->update_record('smartattend_logs', $existing);
        } else {
            // Insert new record
            $log = new stdClass();
            $log->sessionid   = $sessionid;
            $log->userid      = $student->id;
            $log->status      = 'absent';
            $log->method      = 'bulk_absent';
            $log->timecreated = $now;
            $log->id = $DB->insert_record('smartattend_logs', $log);

            $event = \mod_smartattend\event\attendance_taken::create([
                'objectid' => $log->id,
                'context'  => $context,
                'other'    => ['sessionid' => $sessionid],
            ]);
            $event->trigger();
        }
    }

    $total = count($students);
    echo json_encode([
        'success' => true,
        'marked'  => $total,
        'total'   => $total,
        'message' => "$total student(s) marked absent.",
    ]);
    die;
}

if ($action === 'update_attendance') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $userid    = required_param('userid',    PARAM_INT);
    $status    = required_param('status',    PARAM_ALPHA);

    if (!in_array($status, ['present', 'absent'])) {
        echo json_encode(['error' => 'Invalid status value.']);
        die;
    }

    $session     = $DB->get_record('smartattend_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    $cm_local    = get_coursemodule_from_instance('smartattend', $session->smartattendid);
    $ctx_local   = \context_module::instance($cm_local->id);
    require_capability('mod/smartattend:manage', $ctx_local);

    $existing = $DB->get_record('smartattend_logs', ['sessionid' => $sessionid, 'userid' => $userid]);
    if ($existing) {
        $existing->status = $status;
        $existing->method = 'manual';
        $DB->update_record('smartattend_logs', $existing);
        $logid = $existing->id;
    } else {
        $log = new stdClass();
        $log->sessionid   = $sessionid;
        $log->userid      = $userid;
        $log->status      = $status;
        $log->method      = 'manual';
        $log->timecreated = time();
        $logid = $DB->insert_record('smartattend_logs', $log);
    }

    echo json_encode(['success' => true, 'status' => $status, 'logid' => $logid]);
    die;
}

echo json_encode(['error' => 'Invalid action']);
die;
