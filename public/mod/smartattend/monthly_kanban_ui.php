<?php
defined('MOODLE_INTERNAL') || die();

$cm_id = $cm->id ?? 0;
$qr_id = $smartattend->id ?? 0;

$m_req = optional_param('month', date('n'), PARAM_INT);
$y_req = optional_param('year', date('Y'), PARAM_INT);

// Current day highlight — only relevant when viewing the current month & year
$today_day = ((int)date('n') === (int)$m_req && (int)date('Y') === (int)$y_req) ? (int)date('j') : 0;

$m_str = str_pad($m_req, 2, '0', STR_PAD_LEFT);
$y_str = $y_req;

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $m_req, $y_req);
$month_name = date("F", mktime(0, 0, 0, $m_req, 10));

// Fetch all sessions for this specific month
$month_start = strtotime("$y_str-$m_str-01 00:00:00");
$month_end = strtotime("$y_str-$m_str-$days_in_month 23:59:59");
global $DB;
$sessions = $DB->get_records_select('smartattend_sessions', "smartattendid = ? AND starttime >= ? AND starttime <= ?", [$qr_id, $month_start, $month_end], 'starttime ASC');

// Group sessions by exact day
$sessions_by_day = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $sessions_by_day[$i] = [];
}
if ($sessions) {
    foreach ($sessions as $session) {
        $day = (int)date('j', $session->starttime);
        if (isset($sessions_by_day[$day])) {
            $sessions_by_day[$day][] = $session;
        }
    }
}

$lecture_types_raw = get_config('smartattend', 'smartattend_lecture_types');
if (empty($lecture_types_raw)) {
    $lecture_types_raw = "Practical|#10b981\nSDL|#8b5cf6\nECE|#f97316\nDrawing|#14b8a6\nDemo|#ef4444\nDissection|#475569\nIntegrated|#6366f1\nLecture|#3b82f6";
}
$lecture_types = [];
$lecture_css = '';
foreach (explode("\n", str_replace("\r", "", $lecture_types_raw)) as $idx => $line) {
    if (strpos($line, '|') !== false) {
        list($type, $color) = explode('|', $line, 2);
        $type = trim($type);
        $color = trim($color);
        if ($type) {
            $safe_class = 'type-color-' . md5($type);
            $lecture_types[$type] = $safe_class;
            $lecture_css .= ".$safe_class { background-color: $color !important; border: none !important; color: #fff !important; }\n";
            $lecture_css .= ".$safe_class .t-card-name, .$safe_class .t-card-faculty { color: #fff !important; }\n";
        }
    }
}

$timeslots_raw = $smartattend->timeslots ?? "09|9 to 10\n10|10 to 11\n11|11 to 12\n12|12 to 1\n13|LB\n14|2 to 3\n15|3 to 4\n16|4 to 5";
$time_slots = [];
foreach (explode("\n", str_replace("\r", "", $timeslots_raw)) as $line) {
    if (strpos($line, '|') !== false) {
        list($k, $v) = explode('|', $line, 2);
        if (trim($k) !== '') {
            $time_slots[trim($k)] = trim($v);
        }
    }
}

$spanbehavior = $smartattend->spanbehavior ?? 'start_only';

// Generate UI String
$kanban_html = '
<style>
' . $lecture_css . '
:root {
    --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    --accent-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    --glass-bg: rgba(255, 255, 255, 0.7);
    --glass-border: rgba(255, 255, 255, 0.3);
}

.timetable-wrapper {
    background: #f1f5f9;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.04);
}

.kanban-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}
.month-selector select {
    padding: 10px 15px;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,0.1);
    background: white;
    font-size: 15px;
    font-weight: 600;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    cursor: pointer;
}

.timetable-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}

.timetable {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1000px;
}

.timetable th, .timetable td {
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px;
    vertical-align: top;
    min-width: 120px;
}

.timetable th {
    background: #f8fafc;
    font-weight: 800;
    color: #1e293b;
    font-size: 14px;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.timetable .col-date { min-width: 100px; text-align: center; font-weight: 700; color: #334155; }
.timetable .col-day { min-width: 100px; font-weight: 600; color: #64748b; }
.timetable .col-lb { min-width: 60px; text-align: center; background: #faf5ff; color: #a855f7; font-weight: 800; vertical-align: middle; }

/* Sticky first two columns */
.timetable th:nth-child(1), .timetable td:nth-child(1) {
    position: sticky; left: 0; z-index: 11; background: #fff;
    border-right: 2px solid #cbd5e1;
}
.timetable th:nth-child(1) { z-index: 12; background: #f8fafc; }

.timetable th:nth-child(2), .timetable td:nth-child(2) {
    position: sticky; left: 100px; z-index: 11; background: #fff;
    border-right: 2px solid #cbd5e1;
}
.timetable th:nth-child(2) { z-index: 12; background: #f8fafc; }

.tr-today td:not(.td-holiday) { background: #e0e7ff !important; }

/* Empty cell */
.td-empty {
    position: relative;
    cursor: pointer;
    background: #f8fafc;
    transition: background 0.2s;
    min-height: 60px;
}
.td-empty:hover { background: #f1f5f9; }
.td-empty::after {
    content: "+";
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    font-size: 24px;
    color: #94a3b8;
    opacity: 0;
    transition: opacity 0.2s;
}
.td-empty:hover::after { opacity: 1; }

/* Cards */
.t-card {
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left-width: 4px;
    font-size: 13px;
    transition: box-shadow 0.2s;
    position: relative;
}
.t-card:last-child { margin-bottom: 0; }
.t-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

.t-card-name { font-weight: 800; color: #1e293b; margin-bottom: 4px; display:block; }
.t-card-faculty { font-weight: 600; color: #64748b; font-size: 11px; display:block; }

.bg-blue { background-color: #3b82f6; border: none; color: #fff; }
.bg-blue .t-card-name, .bg-blue .t-card-faculty { color: #fff; }
.bg-green { background-color: #10b981; border: none; color: #fff; }
.bg-green .t-card-name, .bg-green .t-card-faculty { color: #fff; }
.bg-purple { background-color: #8b5cf6; border: none; color: #fff; }
.bg-purple .t-card-name, .bg-purple .t-card-faculty { color: #fff; }
.bg-orange { background-color: #f97316; border: none; color: #fff; }
.bg-orange .t-card-name, .bg-orange .t-card-faculty { color: #fff; }
.bg-teal { background-color: #14b8a6; border: none; color: #fff; }
.bg-teal .t-card-name, .bg-teal .t-card-faculty { color: #fff; }
.bg-red { background-color: #ef4444; border: none; color: #fff; }
.bg-red .t-card-name, .bg-red .t-card-faculty { color: #fff; }
.bg-darkgrey { background-color: #475569; border: none; color: #fff; }
.bg-darkgrey .t-card-name, .bg-darkgrey .t-card-faculty { color: #fff; }
.bg-indigo { background-color: #6366f1; border: none; color: #fff; }
.bg-indigo .t-card-name, .bg-indigo .t-card-faculty { color: #fff; }

.t-actions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    opacity: 0;
    transition: opacity 0.2s;
}
.t-card:hover .t-actions { opacity: 1; }

.t-btn {
    font-size: 11px;
    padding: 4px 6px;
    border-radius: 6px;
    text-decoration: none !important;
    color: white;
    font-weight: 700;
}
.t-btn-qr { background: var(--accent-gradient); }
.t-btn-face { background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); }
.t-btn-edit { background: #f1f5f9; color: #475569 !important; border: 1px solid #e2e8f0; }
.t-btn-del { background: var(--danger-gradient); }
.t-btn-all { background: #dcfce7; color: #15803d; font-weight: 800; }
.t-btn-all:hover { background: #16a34a; color: #fff; }
.t-btn-all.loading { opacity: 0.6; pointer-events: none; }
.t-btn-abs { background: #fff1f2; color: #be123c; font-weight: 800; }
.t-btn-abs:hover { background: #e11d48; color: #fff; }
.t-btn-abs.loading { opacity: 0.6; pointer-events: none; }

/* Toast notification for bulk action */
#sa-toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #1e293b;
    color: white;
    padding: 12px 24px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 700;
    z-index: 99999;
    opacity: 0;
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
    max-width: 90%;
    text-align: center;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
}
#sa-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.td-holiday {
    background: #dcfce7 !important;
    text-align: center;
    font-weight: 800;
    font-size: 16px;
    color: #166534;
    letter-spacing: 1px;
    padding: 20px !important;
    z-index: 1 !important;
}

/* Glassmorphism Modals */
.premium-modal {
    display:none; 
    position:fixed; 
    top:0; left:0; 
    width:100%; height:100%; 
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index:9999; 
    align-items:center; 
    justify-content:center;
}
.modal-content-glass {
    background: rgba(255, 255, 255, 0.95);
    padding: 30px; 
    border-radius: 24px; 
    width: 90%;
    max-width: 420px;
    box-sizing: border-box;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.kiosk-video-container {
    background: #0f172a;
    border-radius: 28px;
    overflow: hidden;
    margin-bottom: 25px;
    position: relative;
    aspect-ratio: 4/3;
}

@media (max-width: 1024px) {
    .t-actions { opacity: 1; flex-wrap: wrap; }
    .timetable th:nth-child(2), .timetable td:nth-child(2) {
        position: static; border-right: 1px solid #e2e8f0;
    }
}
@media (max-width: 768px) {
    .kanban-header { flex-direction: column; align-items: flex-start; gap: 15px; }
    .modal-content-glass { padding: 20px; }
    .kiosk-video-container { aspect-ratio: 3/4; }
}
</style>

<div class="timetable-wrapper">
    <div class="kanban-header">
        <h4 class="m-0">' . $month_name . ' ' . $y_str . '</h4>
        <div class="month-selector">
            <form method="GET" action="view.php" id="monthForm">
                <input type="hidden" name="id" value="' . $cm_id . '">
                <select name="month" onchange="document.getElementById(\'monthForm\').submit()">';

for ($m = 1; $m <= 12; $m++) {
    $sel = ($m == $m_req) ? 'selected' : '';
    $m_n = date('F', mktime(0, 0, 0, $m, 10));
    $kanban_html .= '<option value="' . $m . '" ' . $sel . '>' . $m_n . '</option>';
}

$kanban_html .= '
                </select>
                <select name="year" onchange="document.getElementById(\'monthForm\').submit()">';

$cur_y = date('Y');
for ($y = $cur_y - 1; $y <= $cur_y + 2; $y++) {
    $sel = ($y == $y_req) ? 'selected' : '';
    $kanban_html .= '<option value="' . $y . '" ' . $sel . '>' . $y . '</option>';
}

$kanban_html .= '
                </select>
            </form>
        </div>
    </div>

    <div class="timetable-container" id="timetable-container">
        <table class="timetable">
            <thead>
                <tr>
                    <th class="col-date">Date</th>
                    <th class="col-day">Day</th>';
foreach ($time_slots as $k => $v) {
    if (strtolower(trim($v)) === 'lb' || strpos(strtolower($v), 'lunch') !== false) {
        $kanban_html .= '<th class="col-lb">' . htmlspecialchars($v) . '</th>';
    } else {
        $kanban_html .= '<th>' . htmlspecialchars($v) . '</th>';
    }
}
$kanban_html .= '
                </tr>
            </thead>
            <tbody>';

for ($i = 1; $i <= $days_in_month; $i++) {
    $col_date_str = "$y_str-$m_str-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $date_obj = mktime(0, 0, 0, $m_req, $i, $y_req);
    $display_date = date('d-m-y', $date_obj);
    $display_day = date('l', $date_obj);
    
    $holiday_name = null;
    $holiday_session_id = null;
    $has_regular_sessions = false;
    
    if (isset($sessions_by_day[$i])) {
        foreach ($sessions_by_day[$i] as $sess) {
            if ($sess->isholiday == 1) {
                $holiday_name = $sess->name;
                $holiday_session_id = $sess->id;
            } else {
                $has_regular_sessions = true;
            }
        }
    }

    $is_today = ($today_day === $i);
    $tr_class = $is_today ? ' class="tr-today"' : '';
    
    $kanban_html .= '<tr' . $tr_class . ' id="kanban-day-' . $i . '">';
    
    if ($has_regular_sessions || $holiday_name) {
        $kanban_html .= '<td class="col-date">' . $display_date . ($is_today ? '<br><span class="today-badge" style="background:var(--primary-gradient);color:white;padding:2px 6px;border-radius:4px;font-size:10px;">Today</span>' : '') . '</td>';
        $kanban_html .= '<td class="col-day">' . $display_day . '</td>';
    } else {
        $kanban_html .= '<td class="col-date" style="cursor:pointer;" onclick="triggerMarkHoliday(\'' . $col_date_str . '\')" title="Click to Mark Holiday">' . $display_date . ($is_today ? '<br><span class="today-badge" style="background:var(--primary-gradient);color:white;padding:2px 6px;border-radius:4px;font-size:10px;">Today</span>' : '') . '</td>';
        $kanban_html .= '<td class="col-day" style="cursor:pointer;" onclick="triggerMarkHoliday(\'' . $col_date_str . '\')" title="Click to Mark Holiday">' . $display_day . '</td>';
    }
    
    if ($holiday_name) {
        // colspan spans only the time-slot columns; Date and Day are already output as separate <td>s
        $colspan = count($time_slots);
        $url_del_holiday = new moodle_url('/mod/smartattend/view.php', ['id' => $cm_id, 'action' => 'deletesession', 'sessionid' => $holiday_session_id, 'sesskey' => sesskey(), 'month' => $m_req, 'year' => $y_req]);
        // Use ->out(false) to get a raw URL (no HTML-entity encoding) safe for JS string context
        $raw_url_del_holiday = $url_del_holiday->out(false);
        $kanban_html .= '<td colspan="' . $colspan . '" class="td-holiday" style="cursor:pointer;" onclick="if(confirm(\'Remove Holiday: ' . addslashes(htmlspecialchars(strtoupper($holiday_name), ENT_QUOTES)) . '?\')) { window.location.href=\'' . addslashes($raw_url_del_holiday) . '\'; }" title="Click to remove holiday">🎉 ' . htmlspecialchars(strtoupper($holiday_name), ENT_QUOTES) . '</td>';
    } else {
        $slot_sessions = [];
        foreach (array_keys($time_slots) as $slot_key) {
            $slot_sessions[$slot_key] = [];
        }
        
        if (isset($sessions_by_day[$i])) {
            foreach ($sessions_by_day[$i] as $sess) {
                $hour = date('H', $sess->starttime);
                
                if (isset($slot_sessions[$hour])) {
                    $slot_sessions[$hour][] = $sess;
                } else {
                    // Try to find the closest slot backwards or forwards
                    $first_key = array_key_first($time_slots);
                    $last_key = array_key_last($time_slots);
                    
                    if (strcmp($hour, $first_key) < 0) {
                        $slot_sessions[$first_key][] = $sess;
                    } elseif (strcmp($hour, $last_key) > 0) {
                        $slot_sessions[$last_key][] = $sess;
                    } else {
                        // Fallback, just stick it in the first key
                        $slot_sessions[$first_key][] = $sess;
                    }
                }
                
                // Handle duplicate behavior if required
                if ($spanbehavior === 'duplicate') {
                    $end_hour = date('H', $sess->endtime);
                    // Extremely basic span implementation for "duplicate": if it spans more than one configured hour slot
                    foreach (array_keys($time_slots) as $slot_key) {
                        if ($slot_key > $hour && $slot_key < $end_hour) {
                            $slot_sessions[$slot_key][] = $sess;
                        }
                    }
                }
            }
        }
        
        foreach ($time_slots as $slot_key => $slot_label) {
            if (strtolower(trim($slot_label)) === 'lb' || strpos(strtolower($slot_label), 'lunch') !== false) {
                $kanban_html .= '<td class="col-lb"></td>';
                continue;
            }
            
            $sessions_in_slot = $slot_sessions[$slot_key];
            if (empty($sessions_in_slot)) {
                $start_t = $slot_key . ':00';
                $end_t = str_pad((int)$slot_key + 1, 2, '0', STR_PAD_LEFT) . ':00';
                $kanban_html .= '<td class="td-empty" onclick="triggerAddSession(\'' . $col_date_str . '\', \'' . $start_t . '\', \'' . $end_t . '\')"></td>';
            } else {
                $kanban_html .= '<td>';
                foreach ($sessions_in_slot as $sess) {
                    $sess_name_full = !empty($sess->name) ? htmlspecialchars($sess->name, ENT_QUOTES) : 'Session';
                    
                    $lec_name = $sess_name_full; // Raw session name
                    $fac_name = ''; // Removed split logic
                    
                    $lecturetype = $sess->lecturetype ?? '';
                    $color_class = 'bg-blue'; // fallback
                    if ($lecturetype && isset($lecture_types[$lecturetype])) {
                        $color_class = $lecture_types[$lecturetype];
                    }
                    
                    $start_time_val = date('H:i', $sess->starttime);
                    $end_time_val = date('H:i', $sess->endtime);
                    
                    // Passing lecturetype to triggerEditSession
                    $esc_type = addslashes($lecturetype);
                    $url_qr = new moodle_url('/mod/smartattend/teacher_display.php', ['id' => $cm_id, 'sessionid' => $sess->id]);
                    $url_del = new moodle_url('/mod/smartattend/view.php', ['id' => $cm_id, 'action' => 'deletesession', 'sessionid' => $sess->id, 'sesskey' => sesskey(), 'month' => $m_req, 'year' => $y_req]);
                    
                    $kanban_html .= '
                    <div class="t-card ' . $color_class . '">
                        <span class="t-card-name">' . $lec_name . '</span>
                        ' . ($fac_name ? '<span class="t-card-faculty">' . $fac_name . '</span>' : '') . '
                        <div class="t-actions">
                            <a href="javascript:void(0);" onclick="triggerQRModal(\'' . $url_qr . '\')" class="t-btn t-btn-qr" style="display:none;" title="QR">QR</a>
                            <a href="javascript:void(0);" onclick="triggerFaceModal(' . $sess->id . ')" class="t-btn t-btn-face" title="Face ID">Face</a>
                            <a href="javascript:void(0);" onclick="markAllPresent(' . $sess->id . ', this)" class="t-btn t-btn-all" title="Mark all enrolled students as present">✓ All</a>
                            <a href="javascript:void(0);" onclick="markAllAbsent(' . $sess->id . ', this)" class="t-btn t-btn-abs" title="Mark all remaining students as absent">✗ Abs</a>
                            <a href="javascript:void(0);" onclick="triggerEditSession(' . $sess->id . ', \'' . addslashes($sess_name_full) . '\', \'' . $col_date_str . '\', \'' . $start_time_val . '\', \'' . $end_time_val . '\', \'' . $esc_type . '\')" class="t-btn t-btn-edit" title="Edit">Edit</a>
                            <a href="javascript:void(0);" onclick="triggerCopySession(' . $sess->id . ', \'' . addslashes($sess_name_full) . '\', \'' . $col_date_str . '\', \'' . $start_time_val . '\', \'' . $end_time_val . '\', \'' . $esc_type . '\')" class="t-btn t-btn-edit" title="Copy">Copy</a>
                            <a href="' . $url_del . '" class="t-btn t-btn-del" onclick="return confirm(\'Delete Session?\');" title="Delete">Del</a>
                        </div>
                    </div>';
                }
                $kanban_html .= '</td>';
            }
        }
    }
    $kanban_html .= '</tr>';
}

$lecture_type_options = '<option value="">-- Select Type --</option>';
foreach ($lecture_types as $type_name => $css_class) {
    $lecture_type_options .= '<option value="' . htmlspecialchars($type_name, ENT_QUOTES) . '">' . htmlspecialchars($type_name, ENT_QUOTES) . '</option>';
}

$timeslot_options_html = '<option value="">-- Select Time Session --</option>';
foreach ($time_slots as $k => $v) {
    if (strtolower(trim($v)) === 'lb' || strpos(strtolower($v), 'lunch') !== false) continue;
    $start_t = str_pad((int)$k, 2, '0', STR_PAD_LEFT) . ':00';
    $end_t = str_pad((int)$k + 1, 2, '0', STR_PAD_LEFT) . ':00';
    $val = $start_t . '|' . $end_t;
    $timeslot_options_html .= '<option value="' . $val . '">' . htmlspecialchars($v, ENT_QUOTES) . '</option>';
}

$kanban_html .= '
            </tbody>
        </table>
    </div>
</div>

<!-- Add Session Modal -->
<div id="addSessionModal" class="premium-modal">
    <div class="modal-content-glass">
        <h5 id="modalDateLabel" style="margin-top:0; font-weight:800; color:#1e293b;">Add Session</h5>
        <form method="POST" action="view.php" onsubmit="this.querySelector(\'button[type=submit]\').disabled=true; this.querySelector(\'button[type=submit]\').innerHTML=\'Saving...\';">
            <input type="hidden" name="id" value="' . $cm_id . '">
            <input type="hidden" name="action" value="addsession">
            <input type="hidden" name="sesskey" value="' . sesskey() . '">
            <input type="hidden" name="session_date" id="modalSessionDate">
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Session Name</label>
                <input type="text" name="session_name" class="form-control" placeholder="e.g. Embryology" style="border-radius:10px; padding:12px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Lecture Type (Color)</label>
                <select name="lecturetype" class="form-control" style="border-radius:10px; padding:12px;">
                    ' . $lecture_type_options . '
                </select>
            </div>
            
            <div style="display:flex; gap:15px; margin-bottom: 25px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Start Time</label>
                    <input type="time" name="session_start_time" class="form-control" required style="border-radius:10px; padding:12px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">End Time</label>
                    <input type="time" name="session_end_time" class="form-control" required style="border-radius:10px; padding:12px;">
                </div>
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" style="flex:1; border-radius:12px; padding:12px; font-weight:700;" onclick="document.getElementById(\'addSessionModal\').style.display = \'none\';">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2; border-radius:12px; padding:12px; font-weight:700; background:var(--primary-gradient); border:none; color:white;">Save Session</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Session Modal -->
<div id="editSessionModal" class="premium-modal">
    <div class="modal-content-glass">
        <h5 style="margin-top:0; font-weight:800; color:#1e293b;">Edit Session</h5>
        <form method="POST" action="view.php" onsubmit="this.querySelector(\'button[type=submit]\').disabled=true; this.querySelector(\'button[type=submit]\').innerHTML=\'Saving...\';">
            <input type="hidden" name="id" value="' . $cm_id . '">
            <input type="hidden" name="action" value="editsession">
            <input type="hidden" name="sesskey" value="' . sesskey() . '">
            <input type="hidden" name="sessionid_edit" id="editSessionId">
            <input type="hidden" name="session_date" id="editSessionDate">
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Session Name</label>
                <input type="text" name="session_name" id="editSessionName" class="form-control" style="border-radius:10px; padding:12px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Lecture Type (Color)</label>
                <select name="lecturetype" id="editSessionType" class="form-control" style="border-radius:10px; padding:12px;">
                    ' . $lecture_type_options . '
                </select>
            </div>
            
            <div style="display:flex; gap:15px; margin-bottom: 25px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Start Time</label>
                    <input type="time" name="session_start_time" id="editSessionStart" class="form-control" required style="border-radius:10px; padding:12px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">End Time</label>
                    <input type="time" name="session_end_time" id="editSessionEnd" class="form-control" required style="border-radius:10px; padding:12px;">
                </div>
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" style="flex:1; border-radius:12px; padding:12px; font-weight:700;" onclick="document.getElementById(\'editSessionModal\').style.display = \'none\';">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2; border-radius:12px; padding:12px; font-weight:700; background:var(--primary-gradient); border:none; color:white;">Update Session</button>
            </div>
        </form>
    </div>
</div>

<!-- Mark Holiday Modal -->
<div id="markHolidayModal" class="premium-modal">
    <div class="modal-content-glass">
        <h5 id="modalHolidayLabel" style="margin-top:0; font-weight:800; color:#1e293b;">Mark as Holiday / Event</h5>
        <form method="POST" action="view.php" onsubmit="this.querySelector(\'button[type=submit]\').disabled=true; this.querySelector(\'button[type=submit]\').innerHTML=\'Saving...\';">
            <input type="hidden" name="id" value="' . $cm_id . '">
            <input type="hidden" name="action" value="markholiday">
            <input type="hidden" name="sesskey" value="' . sesskey() . '">
            <input type="hidden" name="session_date" id="holidaySessionDate">
            
            <div style="margin-bottom: 25px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Holiday / Event Name</label>
                <input type="text" name="session_name" class="form-control" required placeholder="e.g. Diwali Vacation" style="border-radius:10px; padding:12px;">
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" style="flex:1; border-radius:12px; padding:12px; font-weight:700;" onclick="document.getElementById(\'markHolidayModal\').style.display = \'none\';">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2; border-radius:12px; padding:12px; font-weight:700; background:var(--accent-gradient); border:none; color:white;">Save Holiday</button>
            </div>
        </form>
    </div>
</div>

<!-- Copy Session Modal -->
<div id="copySessionModal" class="premium-modal">
    <div class="modal-content-glass">
        <h5 id="copyModalDateLabel" style="margin-top:0; font-weight:800; color:#1e293b;">Copy Session</h5>
        <form method="POST" action="view.php" onsubmit="this.querySelector(\'button[type=submit]\').disabled=true; this.querySelector(\'button[type=submit]\').innerHTML=\'Saving...\';">
            <input type="hidden" name="id" value="' . $cm_id . '">
            <input type="hidden" name="action" value="copysession">
            <input type="hidden" name="sesskey" value="' . sesskey() . '">
            <input type="hidden" name="source_sessionid" id="copySourceSessionId">
            <input type="hidden" name="session_date" id="copyModalSessionDate">
            <input type="hidden" name="session_start_time" id="copyModalStartTime">
            <input type="hidden" name="session_end_time" id="copyModalEndTime">
            
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Session Name</label>
                <input type="text" name="session_name" id="copyModalSessionName" class="form-control" placeholder="e.g. Embryology" style="border-radius:10px; padding:12px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Lecture Type (Color)</label>
                <select name="lecturetype" id="copyModalLectureType" class="form-control" style="border-radius:10px; padding:12px;">
                    ' . $lecture_type_options . '
                </select>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Time Session</label>
                <select id="copyModalTimeSession" class="form-control" required style="border-radius:10px; padding:12px;" onchange="var parts = this.value.split(\'|\'); document.getElementById(\'copyModalStartTime\').value = parts[0]; document.getElementById(\'copyModalEndTime\').value = parts[1];">
                    ' . $timeslot_options_html . '
                </select>
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" style="flex:1; border-radius:12px; padding:12px; font-weight:700;" onclick="document.getElementById(\'copySessionModal\').style.display = \'none\';">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2; border-radius:12px; padding:12px; font-weight:700; background:var(--primary-gradient); border:none; color:white;">Save Session</button>
            </div>
        </form>
    </div>
</div>

<!-- QR Modal -->
<div id="qrModal" class="premium-modal">
    <div class="modal-content-glass" style="width:90%; max-width:850px; height:85vh; display:flex; flex-direction:column; padding:20px; box-sizing: border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h5 style="margin:0; font-weight:800; color:#1e293b;">Dynamic QR Display</h5>
            <button onclick="document.getElementById(\'qrModal\').style.display = \'none\';" style="background:#f1f5f9; border:none; width:40px; height:40px; border-radius:12px; font-size:24px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <iframe id="qrIframe" src="" style="width:100%; flex:1; border:none; border-radius:20px; background:#fff;"></iframe>
    </div>
</div>

<!-- Face Scan Modal -->
<div id="faceScanModal" class="premium-modal">
    <div class="modal-content-glass" style="width:90%; max-width:480px; max-height:90vh; overflow-y:auto; box-sizing: border-box;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
            <div>
                <h5 style="margin:0; font-weight:800; color:#1e293b; font-size:18px;">Face ID Kiosk</h5>
                <div id="modeBadge" style="display:inline-block; font-size:10px; padding:4px 10px; border-radius:8px; color:white; font-weight:800; margin-top:6px; background:var(--accent-gradient);">AUTO SCAN MODE</div>
            </div>
            <div>
                <button id="switchCameraBtn" onclick="switchCamera()" style="display:none; background:#e2e8f0; color:#1e293b; border:none; padding:10px 16px; border-radius:12px; font-size:12px; font-weight:700; cursor:pointer; margin-right:8px;">Flip Cam</button>
                <button onclick="toggleFaceMode()" style="background:#f1f5f9; border:none; padding:10px 16px; border-radius:12px; font-size:12px; font-weight:700; cursor:pointer;">Switch Mode</button>
            </div>
        </div>
        
        <div class="kiosk-video-container">
            <video id="faceVideo" autoplay playsinline style="width:100%; height:100%; object-fit: cover;"></video>
            <div id="scanStatusOverlay" style="position:absolute; bottom:0; left:0; width:100%; background:rgba(255,255,255,0.95); padding:15px; text-align:center;">
                <span id="scanStatusLabel" style="font-weight:800; color:#64748b; font-size:14px;">Waiting...</span>
            </div>
        </div>

        <div id="autoFaceControls">
            <button class="btn btn-primary" onclick="autoScanLoop()" style="padding:18px; background:var(--accent-gradient); font-size:16px; border-radius:18px; width:100%; color:white; border:none; font-weight:800;">Scan &amp; Identify Face</button>
        </div>
        
        <div id="manualFaceControls" style="display:none;">
            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">Who is this?</label>
                <select id="studentSelect" class="form-control" style="border-radius:14px; padding:12px;"><option value="">-- Choose Student --</option></select>
            </div>
            <div style="display:flex; gap:12px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeFaceModal()">Close</button>
                <button type="button" id="captureBtn" class="btn btn-primary" style="flex:2; background:var(--primary-gradient); border:none; color:white; font-weight:800;" onclick="captureAndVerify()">Train AI & Mark Present</button>
            </div>
            <input type="file" id="teacherFallbackInput" accept="image/*" capture="environment" style="display:none; margin-top:15px; width:100%; padding:15px; border-radius:12px; background:#f1f5f9; font-weight:700;" onchange="teacherFallbackVerify(event)">
        </div>
        
        <div style="margin-top:25px; text-align:center;">
            <a href="javascript:void(0)" onclick="closeFaceModal()" style="font-size:14px; color:#94a3b8; font-weight:700;">Exit Kiosk</a>
        </div>
    </div>
</div>

<script>
function triggerAddSession(dateStr, start, end) {
    document.getElementById("modalSessionDate").value = dateStr;
    document.getElementById("modalDateLabel").innerHTML = "Add Session: " + dateStr;
    if (start) document.querySelector("#addSessionModal input[name=\\"session_start_time\\"]").value = start;
    if (end) document.querySelector("#addSessionModal input[name=\\"session_end_time\\"]").value = end;
    document.querySelector("#addSessionModal input[name=\\"session_name\\"]").value = "";
    document.querySelector("#addSessionModal select[name=\\"lecturetype\\"]").value = "";
    document.getElementById("addSessionModal").style.display = "flex";
}

function triggerCopySession(id, name, dateStr, start, end, type) {
    document.getElementById("copySourceSessionId").value = id;
    document.getElementById("copyModalSessionDate").value = dateStr;
    document.getElementById("copyModalDateLabel").innerHTML = "Copy Session: " + dateStr;
    document.getElementById("copyModalSessionName").value = name && name !== "Session" ? name : "";
    document.getElementById("copyModalStartTime").value = start;
    document.getElementById("copyModalEndTime").value = end;
    
    let typeSelect = document.getElementById("copyModalLectureType");
    if (typeSelect) {
        typeSelect.value = type || "";
    }
    
    let timeSelect = document.getElementById("copyModalTimeSession");
    timeSelect.value = start + "|" + end;
    
    document.getElementById("copySessionModal").style.display = "flex";
}

function triggerEditSession(id, name, dateStr, start, end, type) {
    document.getElementById("editSessionId").value = id;
    document.getElementById("editSessionDate").value = dateStr;
    document.getElementById("editSessionName").value = name && name !== "Session" ? name : "";
    
    let typeSelect = document.getElementById("editSessionType");
    if (typeSelect) {
        typeSelect.value = type || "";
    }
    
    document.getElementById("editSessionStart").value = start;
    document.getElementById("editSessionEnd").value = end;
    document.getElementById("editSessionModal").style.display = "flex";
}

// \u2500\u2500 Mark All Present \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
function showToast(msg, isError) {
    let toast = document.getElementById(\'sa-toast\');
    if (!toast) {
        toast = document.createElement(\'div\');
        toast.id = \'sa-toast\';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.background = isError ? \'#dc2626\' : \'#1e293b\';
    toast.classList.add(\'show\');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(function() { toast.classList.remove(\'show\'); }, 3500);
}

function markAllPresent(sessionId, btn) {
    if (btn.classList.contains(\'loading\')) return;
    btn.classList.add(\'loading\');
    const originalText = btn.innerHTML;
    btn.innerHTML = \'...\';

    const formData = new FormData();
    formData.append(\'action\', \'mark_all_present\');
    formData.append(\'sessionid\', sessionId);
    formData.append(\'sesskey\', M.cfg.sesskey);

    fetch(\'ajax.php\', { method: \'POST\', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.classList.remove(\'loading\');
            if (data.success) {
                btn.innerHTML = \'\u2713 \' + data.total;
                btn.style.background = \'#16a34a\';
                btn.style.color = \'#fff\';
                showToast(\'\u2713 \' + data.message, false);
            } else {
                btn.innerHTML = originalText;
                showToast(\'Error: \' + (data.error || \'Unknown error\'), true);
            }
        })
        .catch(function(err) {
            btn.classList.remove(\'loading\');
            btn.innerHTML = originalText;
            showToast(\'Network error: \' + err.message, true);
        });
}

function markAllAbsent(sessionId, btn) {
    if (btn.classList.contains(\'loading\')) return;
    btn.classList.add(\'loading\');
    const originalText = btn.innerHTML;
    btn.innerHTML = \'...\';

    const formData = new FormData();
    formData.append(\'action\', \'mark_all_absent\');
    formData.append(\'sessionid\', sessionId);
    formData.append(\'sesskey\', M.cfg.sesskey);

    fetch(\'ajax.php\', { method: \'POST\', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.classList.remove(\'loading\');
            if (data.success) {
                btn.innerHTML = \'\u2717 \' + data.total;
                btn.style.background = \'#e11d48\';
                btn.style.color = \'#fff\';
                showToast(\'\u2717 \' + data.message, false);
            } else {
                btn.innerHTML = originalText;
                showToast(\'Error: \' + (data.error || \'Unknown error\'), true);
            }
        })
        .catch(function(err) {
            btn.classList.remove(\'loading\');
            btn.innerHTML = originalText;
            showToast(\'Network error: \' + err.message, true);
        });
}
// ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

function triggerMarkHoliday(dateStr) {
    document.getElementById("holidaySessionDate").value = dateStr;
    document.getElementById("modalHolidayLabel").innerHTML = "Mark Holiday / Event: " + dateStr;
    document.getElementById("markHolidayModal").style.display = "flex";
}

function triggerQRModal(url) {
    document.getElementById("qrIframe").src = url;
    document.getElementById("qrModal").style.display = "flex";
}

let faceStream = null;
let currentFaceSessionId = null;
let isAutoScan = true;
let autoScanTimer = null;
let currentFacingMode = "user";

function startCamera(facingMode) {
    if (faceStream) { faceStream.getTracks().forEach(t => t.stop()); }
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: facingMode } })
        .then(stream => {
            faceStream = stream;
            document.getElementById("faceVideo").srcObject = stream;
            if (isAutoScan) startAutoScan();
            
            if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                navigator.mediaDevices.enumerateDevices().then(devices => {
                    const videoInputs = devices.filter(d => d.kind === "videoinput");
                    if (videoInputs.length <= 1) {
                        document.getElementById("switchCameraBtn").style.display = "none";
                    } else {
                        document.getElementById("switchCameraBtn").style.display = "inline-block";
                    }
                });
            }
        })
        .catch(err => {
            alert("WebRTC restricted by mobile app. Switched to Manual Entry with Native Camera Fallback.");
            isAutoScan = false;
            updateScanModeUI();
            document.getElementById("captureBtn").style.display = "none";
            document.getElementById("teacherFallbackInput").style.display = "block";
        });
}

function switchCamera() {
    currentFacingMode = (currentFacingMode === "user") ? "environment" : "user";
    startCamera(currentFacingMode);
}

const studentData = ' . $student_json . ';
const todayDay = ' . $today_day . ';

function triggerFaceModal(sessionId) {
    currentFaceSessionId = sessionId;
    const modal = document.getElementById("faceScanModal");
    const select = document.getElementById("studentSelect");
    if (select.options.length <= 1) {
        studentData.forEach(s => {
            let opt = document.createElement("option");
            opt.value = s.id; opt.text = s.name;
            select.appendChild(opt);
        });
    }
    modal.style.display = "flex";
    isAutoScan = true;
    updateScanModeUI();
    currentFacingMode = "user";
    startCamera(currentFacingMode);
}

function updateScanModeUI() {
    const manual = document.getElementById("manualFaceControls");
    const auto = document.getElementById("autoFaceControls");
    const badge = document.getElementById("modeBadge");
    if (isAutoScan) {
        manual.style.display = "none"; auto.style.display = "block";
        badge.innerHTML = "AI AUTO SCAN MODE"; badge.style.background = "var(--accent-gradient)";
    } else {
        manual.style.display = "block"; auto.style.display = "none";
        badge.innerHTML = "MANUAL ENTRY MODE"; badge.style.background = "var(--primary-gradient)";
    }
}

function toggleFaceMode() {
    isAutoScan = !isAutoScan; updateScanModeUI();
    if (isAutoScan) startAutoScan(); else stopAutoScan();
}

function closeFaceModal() {
    stopAutoScan();
    if (faceStream) { faceStream.getTracks().forEach(t => t.stop()); faceStream = null; }
    document.getElementById("faceScanModal").style.display = "none";
}

function startAutoScan() { stopAutoScan(); autoScanTimer = setInterval(autoScanLoop, 3000); }
function stopAutoScan() { if (autoScanTimer) clearInterval(autoScanTimer); }

function captureFrame() {
    const video = document.getElementById("faceVideo");
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL("image/jpeg", 0.8);
}

function autoScanLoop() {
    if (!isAutoScan || !faceStream) return;
    const status = document.getElementById("scanStatusLabel");
    status.innerHTML = "Scanning...";
    let fd = new FormData();
    fd.append("action", "auto_scan_face"); fd.append("sessionid", currentFaceSessionId);
    fd.append("image", captureFrame()); fd.append("sesskey", M.cfg.sesskey);
    fetch("ajax.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                stopAutoScan(); status.innerHTML = "CHECKED IN: " + d.name;
                status.style.color = "#10b981"; status.parentElement.style.background = "#dcfce7";
                setTimeout(() => {
                    status.innerHTML = "Ready for next student"; status.style.color = "#64748b";
                    status.parentElement.style.background = "rgba(255,255,255,0.95)";
                    if (isAutoScan && faceStream) startAutoScan();
                }, 2000);
            } else if (d.api_down) {
                status.innerHTML = "⚠️ Face recognition service is unavailable — server may be down. Please try again later.";
                status.style.color = "#f59e0b";
            } else { status.innerHTML = "No match found — use Manual mode to register"; status.style.color = "#ef4444"; }
        });
}

function captureAndVerify() {
    const sid = document.getElementById("studentSelect").value;
    if (!sid) return alert("Select a student");
    const btn = document.getElementById("captureBtn");
    btn.disabled = true; btn.innerHTML = "Training...";
    let fd = new FormData();
    fd.append("action", "train_and_log_face"); fd.append("sessionid", currentFaceSessionId);
    fd.append("studentid", sid); fd.append("image", captureFrame()); fd.append("sesskey", M.cfg.sesskey);
    fetch("ajax.php", { method: "POST", body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.innerHTML = "Register &amp; Mark Present";
            if (d.api_down) {
                alert("⚠️ Face recognition service is unavailable (server may be down).\nPlease try again later or contact your administrator.");
            } else {
                alert(d.message || d.error);
            }
            if (d.success) { isAutoScan = true; updateScanModeUI(); startAutoScan(); }
        });
}

function teacherFallbackVerify(event) {
    const file = event.target.files[0];
    if (!file) return;

    const sid = document.getElementById("studentSelect").value;
    if (!sid) {
        alert("Select a student first");
        event.target.value = "";
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        let fd = new FormData();
        fd.append("action", "train_and_log_face");
        fd.append("sessionid", currentFaceSessionId);
        fd.append("studentid", sid);
        fd.append("image", base64);
        fd.append("sesskey", M.cfg.sesskey);
        
        fetch("ajax.php", { method: "POST", body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.api_down) {
                    alert("⚠️ Face recognition service is unavailable (server may be down).\nPlease try again later or contact your administrator.");
                } else {
                    alert(d.message || d.error);
                }
                event.target.value = "";
                if (d.success) { closeFaceModal(); }
            });
    };
    reader.readAsDataURL(file);
}

// Auto-scroll to todays column when the board is rendered
document.addEventListener("DOMContentLoaded", function() {
    if (todayDay > 0) {
        var todayCol = document.getElementById("kanban-day-" + todayDay);
        if (todayCol) {
            var board = todayCol.closest(".kanban-board");
            if (board) {
                // Centre todays column in the scrollable board
                board.scrollLeft = todayCol.offsetLeft
                                 - (board.offsetWidth  / 2)
                                 + (todayCol.offsetWidth / 2);
            }
        }
    }
});
</script>
';
