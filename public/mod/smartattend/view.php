<?php
/**
 * Main view page for the smartattend module.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // course_module ID
$cm = get_coursemodule_from_id('smartattend', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$smartattend = $DB->get_record('smartattend', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/smartattend:view', $context);

$PAGE->set_url('/mod/smartattend/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($smartattend->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($smartattend->name));

$is_teacher = has_capability('mod/smartattend:manage', $context);

// Process actions
$action = optional_param('action', '', PARAM_ALPHA);

if ($is_teacher && $action === 'addsession') {
    require_sesskey();
    
    $session_date = required_param('session_date', PARAM_TEXT); // e.g., '2026-04-10'
    $session_start = required_param('session_start_time', PARAM_TEXT); // e.g., '09:00'
    $session_end = required_param('session_end_time', PARAM_TEXT); // e.g., '10:00'
    
    // Parse into timestamps using Moodle timezone or server default timezone
    $start_ts = strtotime($session_date . ' ' . $session_start);
    $end_ts = strtotime($session_date . ' ' . $session_end);
    
    $month = date('m', $start_ts);
    $year = date('Y', $start_ts);

    $session_name = optional_param('session_name', '', PARAM_TEXT);
    $lecturetype = optional_param('lecturetype', '', PARAM_TEXT);

    $session = new stdClass();
    $session->smartattendid = $smartattend->id;
    $session->name = $session_name;
    $session->lecturetype = $lecturetype;
    $session->starttime = $start_ts;
    $session->endtime = $end_ts;
    $session->timecreated = time();
    $DB->insert_record('smartattend_sessions', $session);
    
    redirect(new moodle_url('/mod/smartattend/view.php', ['id' => $cm->id, 'month' => $month, 'year' => $year]));
}

if ($is_teacher && $action === 'copysession') {
    require_sesskey();
    
    $source_sessionid = required_param('source_sessionid', PARAM_INT);
    $session_date = required_param('session_date', PARAM_TEXT);
    $session_start = required_param('session_start_time', PARAM_TEXT);
    $session_end = required_param('session_end_time', PARAM_TEXT);
    
    $start_ts = strtotime($session_date . ' ' . $session_start);
    $end_ts = strtotime($session_date . ' ' . $session_end);
    
    $month = date('m', $start_ts);
    $year = date('Y', $start_ts);

    $session_name = optional_param('session_name', '', PARAM_TEXT);
    $lecturetype = optional_param('lecturetype', '', PARAM_TEXT);

    $session = new stdClass();
    $session->smartattendid = $smartattend->id;
    $session->name = $session_name;
    $session->lecturetype = $lecturetype;
    $session->starttime = $start_ts;
    $session->endtime = $end_ts;
    $session->timecreated = time();
    $new_sessionid = $DB->insert_record('smartattend_sessions', $session);
    
    // Copy attendance records
    $old_logs = $DB->get_records('smartattend_logs', ['sessionid' => $source_sessionid]);
    if ($old_logs) {
        $now = time();
        foreach ($old_logs as $log) {
            $new_log = clone $log;
            unset($new_log->id);
            $new_log->sessionid = $new_sessionid;
            $new_log->timecreated = $now;
            $DB->insert_record('smartattend_logs', $new_log);
        }
    }
    
    redirect(new moodle_url('/mod/smartattend/view.php', ['id' => $cm->id, 'month' => $month, 'year' => $year]));
}

if ($is_teacher && $action === 'editsession') {
    require_sesskey();
    $sessionid = required_param('sessionid_edit', PARAM_INT);
    $session_name = optional_param('session_name', '', PARAM_TEXT);
    $session_date = required_param('session_date', PARAM_TEXT);
    $session_start = required_param('session_start_time', PARAM_TEXT);
    $session_end = required_param('session_end_time', PARAM_TEXT);
    $lecturetype = optional_param('lecturetype', '', PARAM_TEXT);
    
    $start_ts = strtotime($session_date . ' ' . $session_start);
    $end_ts = strtotime($session_date . ' ' . $session_end);
    
    $session = $DB->get_record('smartattend_sessions', ['id' => $sessionid, 'smartattendid' => $smartattend->id]);
    if ($session) {
        $session->name = $session_name;
        $session->lecturetype = $lecturetype;
        $session->starttime = $start_ts;
        $session->endtime = $end_ts;
        $DB->update_record('smartattend_sessions', $session);
    }
    
    $month = date('m', $start_ts);
    $year = date('Y', $start_ts);
    redirect(new moodle_url('/mod/smartattend/view.php', ['id' => $cm->id, 'month' => $month, 'year' => $year]));
}

if ($is_teacher && $action === 'markholiday') {
    require_sesskey();
    
    $session_date = required_param('session_date', PARAM_TEXT); // e.g., '2026-04-10'
    $session_name = required_param('session_name', PARAM_TEXT);
    
    $start_ts = strtotime($session_date . ' 00:00:00');
    $end_ts = strtotime($session_date . ' 23:59:59');
    
    $month = date('m', $start_ts);
    $year = date('Y', $start_ts);

    $session = new stdClass();
    $session->smartattendid = $smartattend->id;
    $session->name = $session_name;
    $session->starttime = $start_ts;
    $session->endtime = $end_ts;
    $session->lecturetype = 'Holiday';
    $session->isholiday = 1;
    $session->timecreated = time();
    $DB->insert_record('smartattend_sessions', $session);
    
    redirect(new moodle_url('/mod/smartattend/view.php', ['id' => $cm->id, 'month' => $month, 'year' => $year]));
}

if ($is_teacher && $action === 'deletesession') {
    require_sesskey();
    $sessionid_to_delete = required_param('sessionid', PARAM_INT);
    // Grab month and year to stay on the same UI view
    $m_ret = optional_param('month', date('m'), PARAM_INT);
    $y_ret = optional_param('year', date('Y'), PARAM_INT);

    // Verify session belongs to this instance
    $session_to_delete = $DB->get_record('smartattend_sessions', ['id' => $sessionid_to_delete, 'smartattendid' => $smartattend->id]);
    if ($session_to_delete) {
        $DB->delete_records('smartattend_logs', ['sessionid' => $session_to_delete->id]);
        $DB->delete_records('smartattend_sessions', ['id' => $session_to_delete->id]);
    }
    
    redirect(new moodle_url('/mod/smartattend/view.php', ['id' => $cm->id, 'month' => $m_ret, 'year' => $y_ret]));
}

if ($is_teacher) {
    echo html_writer::tag('h3', 'Monthly Session Dashboard');
    
    // Fetch enrolled students for Face ID scanner.
    $students = get_enrolled_users($context, 'mod/smartattend:scanqr', 0, 'u.id, u.firstname, u.lastname');
    $student_list = [];
    foreach ($students as $student) {
        $student_list[] = [
            'id' => $student->id,
            'name' => fullname($student)
        ];
    }
    $student_json = json_encode($student_list);

    // We will drop the massive matrix include here
    require_once(__DIR__ . '/monthly_kanban_ui.php');
    
    // Output everything built by monthly_kanban_ui
    echo $kanban_html;
    
    

} else {
    // Student View - Premium Experience
    echo '
    <style>
    .student-app-wrapper {
        max-width: 500px;
        margin: 40px auto;
        padding: 0 20px;
        font-family: \'Outfit\', sans-serif;
    }
    .status-card {
        background: white;
        border-radius: 24px;
        padding: 40px 30px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.06);
        text-align: center;
        border: 1px solid rgba(0,0,0,0.03);
    }
    .app-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        border-radius: 20px;
        margin: 0 auto 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 40px;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
    }
    .action-group {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 35px;
    }
    .student-btn {
        width: 100%;
        padding: 18px;
        border-radius: 16px;
        border: none;
        font-weight: 800;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .student-btn:active { transform: scale(0.97); }
    .btn-qr-main {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 8px 15px rgba(16, 185, 129, 0.2);
    }
    .btn-face-main {
        background: linear-gradient(135deg, #6d28d9 0%, #4c1d95 100%);
        color: white;
        box-shadow: 0 8px 15px rgba(109, 40, 217, 0.2);
    }
    .input-premium {
        width: 100%;
        padding: 16px;
        border-radius: 14px;
        border: 2px solid #f1f5f9;
        background: #f8fafc;
        font-size: 18px;
        font-weight: 600;
        text-align: center;
        margin-bottom: 20px;
        transition: all 0.2s;
    }
    .input-premium:focus {
        border-color: #6366f1;
        outline: none;
        background: white;
    }
    
    /* Live Scan Overlay */
    .scan-overlay {
        display:none;
        position:fixed;
        top:0; left:0; width:100%; height:100%;
        background: #0f172a;
        z-index: 10000;
        flex-direction: column;
        padding: 30px;
        box-sizing: border-box;
    }
    .scanner-header {
        display:flex;
        justify-content: space-between;
        align-items: center;
        color: white;
        margin-bottom: 20px;
    }
    .video-container {
        flex: 1;
        position: relative;
        background: #000;
        border-radius: 30px;
        overflow: hidden;
    }
    #studentVideo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .scanner-mask {
        position: absolute;
        top: 15%; left: 15%; right: 15%; bottom: 15%;
        border: 2px solid rgba(255,255,255,0.4);
        border-radius: 100%;
        box-shadow: 0 0 0 1000px rgba(15, 23, 42, 0.5);
    }
    
    @media (max-width: 480px) {
        .student-app-wrapper {
            margin: 20px auto;
            padding: 0 15px;
        }
        .status-card {
            padding: 25px 20px;
        }
        .app-icon {
            width: 60px;
            height: 60px;
            font-size: 30px;
            margin-bottom: 20px;
        }
        .student-btn {
            padding: 15px;
            font-size: 15px;
        }
    }
    </style>

    <div class="student-app-wrapper">
        <div class="status-card">
            <div class="app-icon">QR</div>
            <h2 style="font-weight:900; color:#1e293b; margin-bottom:10px;">Check In</h2>
            <p style="color:#64748b; font-size:15px; line-height:1.6;">Welcome back! Please verify your attendance using one of the methods below.</p>
            
            <div class="action-group" id="student-main-view">';
            
    $can_scan_qr = has_capability('mod/smartattend:scanqr', $context);
    $can_submit_face = has_capability('mod/smartattend:submitface', $context);
    
    if ($can_scan_qr) {
        echo '
                <div id="qr-entry-box">
                    <input type="text" id="qr-token-input" class="input-premium" placeholder="Enter QR Token">
                    <button class="student-btn btn-qr-main" id="scan-qr-btn">Confirm QR Token</button>
                </div>';
    }
    
    if ($can_scan_qr && $can_submit_face) {
        echo '
                <div style="margin: 15px 0; color:#94a3b8; font-weight:700;">OR</div>';
    }
    
    if ($can_submit_face) {
        echo '
                <button class="student-btn btn-face-main" onclick="openStudentScanner()">Launch Face ID</button>';
    }
    
    if (!$can_scan_qr && !$can_submit_face) {
        echo '
                <p style="color:#ef4444; font-weight:700; padding:15px; background:#fef2f2; border-radius:12px;">You do not have permission to check in.</p>';
    }
    
    echo '
            </div>
        </div>
    </div>

    <!-- Student Scanner Overlay -->
    <div id="studentScannerOverlay" class="scan-overlay">
        <div class="scanner-header">
            <h4 style="margin:0; font-weight:800;">Face ID Scanner</h4>
            <button onclick="closeStudentScanner()" style="background:rgba(255,255,255,0.1); border:none; padding:10px 20px; border-radius:12px; color:white; font-weight:700;">Close</button>
        </div>
        
        <div class="video-container">
            <video id="studentVideo" autoplay playsinline></video>
            <div class="scanner-mask"></div>
            <div style="position:absolute; bottom:30px; width:100%; text-align:center;">
                <p style="color:white; font-size:14px; opacity:0.8;">Align your face in the circle</p>
            </div>
        </div>
        
        <div style="padding: 30px 0;">
            <button class="student-btn btn-face-main" id="studentCaptureBtn" onclick="studentCaptureAndVerify()" style="padding:22px; font-size:18px;">Capture & Check In</button>
            <input type="file" id="studentFallbackInput" accept="image/*" capture="user" style="display:none; margin-top:20px; width:100%; padding:15px; border-radius:12px; background:rgba(255,255,255,0.1); color:white; font-weight:700;" onchange="studentFallbackVerify(event)">
        </div>
    </div>

    <script>
    let studentStream = null;

    function openStudentScanner() {
        const overlay = document.getElementById("studentScannerOverlay");
        overlay.style.display = "flex";
        
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => {
                studentStream = stream;
                document.getElementById("studentVideo").srcObject = stream;
            })
            .catch(err => {
                document.getElementById("studentFallbackInput").style.display = "block"; alert("WebRTC restricted by Mobile App. Falling back to native camera app.");
                closeStudentScanner();
            });
    }

    function closeStudentScanner() {
        if (studentStream) {
            studentStream.getTracks().forEach(t => t.stop());
        }
        document.getElementById("studentScannerOverlay").style.display = "none";
    }

    function studentCaptureAndVerify() {
        const sessionid = prompt("Enter Session ID (displayed on projector):");
        if (!sessionid) return;

        const video = document.getElementById("studentVideo");
        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const base64 = canvas.toDataURL("image/jpeg", 0.8);

        const btn = document.getElementById("studentCaptureBtn");
        btn.disabled = true;
        btn.innerHTML = "Processing...";

        let formData = new FormData();
        formData.append("action", "verify_face");
        formData.append("sessionid", sessionid);
        formData.append("image", base64);
        formData.append("sesskey", M.cfg.sesskey);

        fetch("ajax.php", { method: "POST", body: formData })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                btn.innerHTML = "Capture & Check In";
                if (d.success) {
                    alert("SUCCESS: " + d.message);
                    closeStudentScanner();
                } else {
                    alert("FAILED: " + d.error);
                }
            })
            .catch(err => {
                alert("Error: " + err.message);
                btn.disabled = false;
                btn.innerHTML = "Capture & Check In";
            });
    }

    function studentFallbackVerify(event) {
        const file = event.target.files[0];
        if (!file) return;

        const sessionid = prompt("Enter Session ID (displayed on projector):");
        if (!sessionid) {
            event.target.value = "";
            return;
        }

        const btn = document.getElementById("studentCaptureBtn");
        btn.disabled = true;
        btn.innerHTML = "Processing Image...";

        const reader = new FileReader();
        reader.onload = function(e) {
            const base64 = e.target.result;
            let formData = new FormData();
            formData.append("action", "verify_face");
            formData.append("sessionid", sessionid);
            formData.append("image", base64);
            formData.append("sesskey", M.cfg.sesskey);

            fetch("ajax.php", { method: "POST", body: formData })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false;
                    btn.innerHTML = "Capture & Check In";
                    event.target.value = "";
                    if (d.success) {
                        alert("SUCCESS: " + d.message);
                        closeStudentScanner();
                    } else {
                        alert("FAILED: " + d.error);
                    }
                })
                .catch(err => {
                    alert("Error: " + err.message);
                    btn.disabled = false;
                    btn.innerHTML = "Capture & Check In";
                    event.target.value = "";
                });
        };
        reader.readAsDataURL(file);
    }

    document.getElementById("scan-qr-btn")?.addEventListener("click", function() {
        let token = document.getElementById("qr-token-input").value;
        if (!token) return alert("Please enter token");

        let formData = new FormData();
        formData.append("action", "scan_qr");
        formData.append("token", token);
        formData.append("sesskey", M.cfg.sesskey);

        fetch("ajax.php", { method: "POST", body: formData })
            .then(r => r.json())
            .then(d => alert(d.success ? d.message : d.error));
    });
    </script>
    ';
}

echo $OUTPUT->footer();

