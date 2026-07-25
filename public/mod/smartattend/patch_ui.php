<?php
$file = '/home/lms_medi_wiki/public_html/public/mod/smartattend/monthly_kanban_ui.php';
$content = file_get_contents($file);

// Find the start of $kanban_html = '
$start_pos = strpos($content, '$kanban_html = \'');
// Find the end of the kanban_html generation block.
// It ends right before <!-- Add Session Modal -->
$end_pos = strpos($content, '<!-- Add Session Modal -->');

$new_logic = <<<EOD
function smartattend_get_lecture_color_class(\$name) {
    \$n = strtolower(\$name);
    if (strpos(\$n, 'practical') !== false) return 'bg-green';
    if (strpos(\$n, 'sdl') !== false) return 'bg-purple';
    if (strpos(\$n, 'ece') !== false) return 'bg-orange';
    if (strpos(\$n, 'drawing') !== false) return 'bg-teal';
    if (strpos(\$n, 'demo') !== false) return 'bg-red';
    if (strpos(\$n, 'dissection') !== false) return 'bg-darkgrey';
    if (strpos(\$n, 'integrated') !== false) return 'bg-indigo';
    return 'bg-blue';
}

function smartattend_is_holiday_or_event(\$name) {
    \$keywords = ['holiday', 'test', 'vacation', 'event', 'function', 'festival', 'exam'];
    foreach (\$keywords as \$kw) {
        if (stripos(\$name, \$kw) !== false) return true;
    }
    return false;
}

\$kanban_html = '
<style>
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

.tr-today td { background: #e0e7ff !important; }

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

.bg-blue { border-left-color: #3b82f6; }
.bg-green { border-left-color: #10b981; }
.bg-purple { border-left-color: #8b5cf6; }
.bg-orange { border-left-color: #f97316; }
.bg-teal { border-left-color: #14b8a6; }
.bg-red { border-left-color: #ef4444; }
.bg-darkgrey { border-left-color: #475569; }
.bg-indigo { border-left-color: #6366f1; }

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
        <h4 class="m-0">' . \$month_name . ' ' . \$y_str . '</h4>
        <div class="month-selector">
            <form method="GET" action="view.php" id="monthForm">
                <input type="hidden" name="id" value="' . \$cm_id . '">
                <select name="month" onchange="document.getElementById(\'monthForm\').submit()">';

for (\$m = 1; \$m <= 12; \$m++) {
    \$sel = (\$m == \$m_req) ? 'selected' : '';
    \$m_n = date('F', mktime(0, 0, 0, \$m, 10));
    \$kanban_html .= '<option value="' . \$m . '" ' . \$sel . '>' . \$m_n . '</option>';
}

\$kanban_html .= '
                </select>
                <select name="year" onchange="document.getElementById(\'monthForm\').submit()">';

\$cur_y = date('Y');
for (\$y = \$cur_y - 1; \$y <= \$cur_y + 2; \$y++) {
    \$sel = (\$y == \$y_req) ? 'selected' : '';
    \$kanban_html .= '<option value="' . \$y . '" ' . \$sel . '>' . \$y . '</option>';
}

\$kanban_html .= '
                </select>
            </form>
        </div>
    </div>

    <div class="timetable-container">
        <table class="timetable">
            <thead>
                <tr>
                    <th class="col-date">Date</th>
                    <th class="col-day">Day</th>
                    <th>9 to 10</th>
                    <th>10 to 11</th>
                    <th>11 to 12</th>
                    <th>12 to 1</th>
                    <th class="col-lb">LB</th>
                    <th>2 to 3</th>
                    <th>3 to 4</th>
                    <th>4 to 5</th>
                </tr>
            </thead>
            <tbody>';

\$time_slots = [
    '09' => '9 to 10',
    '10' => '10 to 11',
    '11' => '11 to 12',
    '12' => '12 to 1',
    '13' => 'LB',
    '14' => '2 to 3',
    '15' => '3 to 4',
    '16' => '4 to 5'
];

for (\$i = 1; \$i <= \$days_in_month; \$i++) {
    \$col_date_str = "\$y_str-\$m_str-" . str_pad(\$i, 2, '0', STR_PAD_LEFT);
    \$date_obj = mktime(0, 0, 0, \$m_req, \$i, \$y_req);
    \$display_date = date('d-m-y', \$date_obj);
    \$display_day = date('l', \$date_obj);
    
    \$is_today = (\$today_day === \$i);
    \$tr_class = \$is_today ? ' class="tr-today"' : '';
    
    \$kanban_html .= '<tr' . \$tr_class . ' id="kanban-day-' . \$i . '">';
    \$kanban_html .= '<td class="col-date">' . \$display_date . (\$is_today ? '<br><span class="today-badge" style="background:var(--primary-gradient);color:white;padding:2px 6px;border-radius:4px;font-size:10px;">Today</span>' : '') . '</td>';
    \$kanban_html .= '<td class="col-day">' . \$display_day . '</td>';
    
    \$holiday_name = null;
    if (isset(\$sessions_by_day[\$i])) {
        foreach (\$sessions_by_day[\$i] as \$sess) {
            if (smartattend_is_holiday_or_event(\$sess->name)) {
                \$holiday_name = \$sess->name;
                break;
            }
        }
    }
    
    if (\$holiday_name) {
        \$kanban_html .= '<td colspan="8" class="td-holiday">🎉 ' . htmlspecialchars(strtoupper(\$holiday_name), ENT_QUOTES) . '</td>';
    } else {
        \$slot_sessions = [];
        foreach (array_keys(\$time_slots) as \$slot_key) {
            \$slot_sessions[\$slot_key] = [];
        }
        
        if (isset(\$sessions_by_day[\$i])) {
            foreach (\$sessions_by_day[\$i] as \$sess) {
                \$hour = date('H', \$sess->starttime);
                if (isset(\$slot_sessions[\$hour])) {
                    \$slot_sessions[\$hour][] = \$sess;
                } else {
                    \$hour_int = (int)\$hour;
                    if (\$hour_int < 9) \$slot_sessions['09'][] = \$sess;
                    elseif (\$hour_int > 16) \$slot_sessions['16'][] = \$sess;
                    else \$slot_sessions['09'][] = \$sess;
                }
            }
        }
        
        foreach (\$time_slots as \$slot_key => \$slot_label) {
            if (\$slot_key === '13') {
                \$kanban_html .= '<td class="col-lb"></td>';
                continue;
            }
            
            \$sessions_in_slot = \$slot_sessions[\$slot_key];
            if (empty(\$sessions_in_slot)) {
                \$start_t = \$slot_key . ':00';
                \$end_t = str_pad((int)\$slot_key + 1, 2, '0', STR_PAD_LEFT) . ':00';
                \$kanban_html .= '<td class="td-empty" onclick="triggerAddSession(\'' . \$col_date_str . '\', \'' . \$start_t . '\', \'' . \$end_t . '\')"></td>';
            } else {
                \$kanban_html .= '<td>';
                foreach (\$sessions_in_slot as \$sess) {
                    \$sess_name_full = !empty(\$sess->name) ? htmlspecialchars(\$sess->name, ENT_QUOTES) : 'Session';
                    
                    \$parts = explode('-', \$sess_name_full, 2);
                    \$lec_name = trim(\$parts[0]);
                    \$fac_name = isset(\$parts[1]) ? trim(\$parts[1]) : '';
                    
                    \$color_class = smartattend_get_lecture_color_class(\$sess_name_full);
                    
                    \$start_time_val = date('H:i', \$sess->starttime);
                    \$end_time_val = date('H:i', \$sess->endtime);
                    \$url_qr = new moodle_url('/mod/smartattend/teacher_display.php', ['id' => \$cm_id, 'sessionid' => \$sess->id]);
                    \$url_del = new moodle_url('/mod/smartattend/view.php', ['id' => \$cm_id, 'action' => 'deletesession', 'sessionid' => \$sess->id, 'sesskey' => sesskey(), 'month' => \$m_req, 'year' => \$y_req]);
                    
                    \$kanban_html .= '
                    <div class="t-card ' . \$color_class . '">
                        <span class="t-card-name">' . \$lec_name . '</span>
                        ' . (\$fac_name ? '<span class="t-card-faculty">' . \$fac_name . '</span>' : '') . '
                        <div class="t-actions">
                            <a href="javascript:void(0);" onclick="triggerQRModal(\'' . \$url_qr . '\')" class="t-btn t-btn-qr" title="QR">QR</a>
                            <a href="javascript:void(0);" onclick="triggerFaceModal(' . \$sess->id . ')" class="t-btn t-btn-face" title="Face ID">Face</a>
                            <a href="javascript:void(0);" onclick="triggerEditSession(' . \$sess->id . ', \'' . addslashes(\$sess_name_full) . '\', \'' . \$col_date_str . '\', \'' . \$start_time_val . '\', \'' . \$end_time_val . '\')" class="t-btn t-btn-edit" title="Edit">Edit</a>
                            <a href="' . \$url_del . '" class="t-btn t-btn-del" onclick="return confirm(\'Delete Session?\');" title="Delete">Del</a>
                        </div>
                    </div>';
                }
                \$kanban_html .= '</td>';
            }
        }
    }
    \$kanban_html .= '</tr>';
}

\$kanban_html .= '
            </tbody>
        </table>
    </div>
</div>

';
EOD;

$final_content = substr($content, 0, $start_pos) . $new_logic . substr($content, $end_pos);
file_put_contents($file, $final_content);
echo "Replaced content in UI.\n";
