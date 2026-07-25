<?php
/**
 * Enhanced Attendance Report for smartattend.
 * Teacher view: editable matrix, search, sort, mobile-first.
 * Student view: personal attendance with per-type hours and percentage.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id          = required_param('id', PARAM_INT);
$cm          = get_coursemodule_from_id('smartattend', $id, 0, false, MUST_EXIST);
$course      = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$smartattend = $DB->get_record('smartattend', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context    = context_module::instance($cm->id);
require_capability('mod/smartattend:view', $context);

$is_teacher = has_capability('mod/smartattend:manage', $context);

// ── URL params ──────────────────────────────────────────────────────────────
$m_req    = optional_param('month', (int)date('n'), PARAM_INT);
$y_req    = optional_param('year',  (int)date('Y'), PARAM_INT);
$sort_req = optional_param('sort', 'name', PARAM_ALPHAEXT);
$dir_req  = optional_param('dir',  'asc',  PARAM_ALPHA);
if (!in_array($dir_req, ['asc', 'desc'])) { $dir_req = 'asc'; }

$PAGE->set_url('/mod/smartattend/report.php', ['id' => $cm->id, 'month' => $m_req, 'year' => $y_req]);
$PAGE->set_title('Attendance Report');
$PAGE->set_heading(format_string($course->fullname));

// ── Date range ──────────────────────────────────────────────────────────────
$m_str         = str_pad($m_req, 2, '0', STR_PAD_LEFT);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $m_req, $y_req);
$month_start   = strtotime("{$y_req}-{$m_str}-01 00:00:00");
$month_end     = strtotime("{$y_req}-{$m_str}-{$days_in_month} 23:59:59");
$month_label   = date('F Y', mktime(0, 0, 0, $m_req, 1, $y_req));

// ── Helpers ──────────────────────────────────────────────────────────────────
function sa_name($user, $pd) {
    if (isset($pd[$user->id]) && !empty($pd[$user->id]->fullname)) {
        return htmlspecialchars(trim($pd[$user->id]->fullname), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars(fullname($user), ENT_QUOTES, 'UTF-8');
}
function sa_roll($uid, $pd) {
    return isset($pd[$uid]) ? htmlspecialchars($pd[$uid]->rollno ?? '', ENT_QUOTES, 'UTF-8') : '';
}
function sa_pct_color($pct) {
    if ($pct >= 75) return '#16a34a';
    if ($pct >= 60) return '#d97706';
    return '#dc2626';
}

// ═══════════════════════════════════════════════════════════════════════════
//  STUDENT VIEW — personal attendance only
// ═══════════════════════════════════════════════════════════════════════════
if (!$is_teacher) {
    $uid     = $USER->id;
    $profile = $DB->get_record('local_studentprofile_data', ['userid' => $uid, 'draft' => 0]);
    $my_name = ($profile && !empty($profile->fullname))
        ? htmlspecialchars(trim($profile->fullname), ENT_QUOTES, 'UTF-8')
        : htmlspecialchars(fullname($USER), ENT_QUOTES, 'UTF-8');
    $my_roll = htmlspecialchars($profile->rollno ?? '', ENT_QUOTES, 'UTF-8');

    // All non-holiday sessions (all time) for summary cards
    $all_sessions = $DB->get_records_select(
        'smartattend_sessions',
        'smartattendid = ? AND (isholiday IS NULL OR isholiday = 0)',
        [$cm->instance], 'starttime ASC'
    );

    // My logs
    $my_log_map = [];
    if (!empty($all_sessions)) {
        [$in_sql, $in_params] = $DB->get_in_or_equal(array_keys($all_sessions));
        $my_logs = $DB->get_records_select(
            'smartattend_logs',
            "userid = ? AND sessionid {$in_sql}",
            array_merge([$uid], $in_params),
            '', 'sessionid, status'
        );
        foreach ($my_logs as $ml) { $my_log_map[$ml->sessionid] = $ml->status; }
    }

    // Build per-type stats
    $type_stats = [];
    foreach ($all_sessions as $sess) {
        $type = !empty($sess->lecturetype) ? $sess->lecturetype : 'General';
        $dur  = max(0, round(($sess->endtime - $sess->starttime) / 3600, 1));
        if (!isset($type_stats[$type])) {
            $type_stats[$type] = ['total' => 0, 'present' => 0, 'total_h' => 0.0, 'present_h' => 0.0];
        }
        $type_stats[$type]['total']++;
        $type_stats[$type]['total_h'] += $dur;
        if (($my_log_map[$sess->id] ?? '') === 'present') {
            $type_stats[$type]['present']++;
            $type_stats[$type]['present_h'] += $dur;
        }
    }

    $ov_total = $ov_present = 0;
    $ov_h = $ov_ph = 0.0;
    foreach ($type_stats as $ts) {
        $ov_total   += $ts['total'];
        $ov_present += $ts['present'];
        $ov_h       += $ts['total_h'];
        $ov_ph      += $ts['present_h'];
    }
    $ov_pct = $ov_total > 0 ? round(($ov_present / $ov_total) * 100, 1) : 0;

    // Monthly sessions for detail
    $month_sessions = $DB->get_records_select(
        'smartattend_sessions',
        'smartattendid = ? AND starttime >= ? AND starttime <= ? AND (isholiday IS NULL OR isholiday = 0)',
        [$cm->instance, $month_start, $month_end], 'starttime ASC'
    );

    echo $OUTPUT->header();
?>
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap");
*,*::before,*::after{box-sizing:border-box}
.sv-wrap{font-family:"Inter",sans-serif;max-width:900px;margin:0 auto;padding:20px 14px 60px}
.sv-header{background:linear-gradient(135deg,#6366f1 0%,#4338ca 100%);border-radius:20px;padding:26px 22px;color:white;margin-bottom:24px;box-shadow:0 8px 30px rgba(99,102,241,.3)}
.sv-header h2{margin:0 0 5px;font-size:20px;font-weight:800}
.sv-header p{margin:0;opacity:.82;font-size:13px}
.sv-roll{display:inline-block;background:rgba(255,255,255,.2);padding:2px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-left:8px;vertical-align:middle}
.sv-grid{display:grid;grid-template-columns:1fr;gap:14px;margin-bottom:24px}
@media(min-width:540px){.sv-grid{grid-template-columns:1fr 1fr}}
@media(min-width:820px){.sv-grid{grid-template-columns:repeat(3,1fr)}}
.sv-card{background:white;border-radius:16px;padding:20px;box-shadow:0 2px 16px rgba(0,0,0,.06);border:1px solid #f1f5f9}
.sv-card-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.sv-card-pct{font-size:38px;font-weight:900;line-height:1.1;margin-bottom:5px}
.sv-card-detail{font-size:12px;color:#64748b;font-weight:600}
.sv-card-bar{margin-top:14px;background:#f1f5f9;border-radius:99px;height:6px;overflow:hidden}
.sv-card-fill{height:100%;border-radius:99px;transition:width .6s ease}
.sv-month{background:white;border-radius:16px;padding:20px;box-shadow:0 2px 16px rgba(0,0,0,.06);border:1px solid #f1f5f9}
.sv-month-hdr{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px}
.sv-month-title{font-size:15px;font-weight:800;color:#1e293b}
.sv-sel-row{display:flex;gap:8px}
.sv-sel{padding:8px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;background:white}
.sv-sel:focus{border-color:#6366f1;outline:none}
.sv-table{width:100%;border-collapse:collapse}
.sv-table th{background:#f8fafc;padding:9px 12px;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;text-align:left;border-bottom:2px solid #e2e8f0;white-space:nowrap}
.sv-table td{padding:9px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;color:#334155}
.sv-table tr:last-child td{border-bottom:none}
.sv-p{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;background:#dcfce7;color:#15803d}
.sv-a{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;background:#fee2e2;color:#dc2626}
.sv-type{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700;background:#f1f5f9;color:#475569}
.sv-empty{text-align:center;padding:40px 20px;color:#94a3b8;font-size:14px}
.sv-back{display:inline-flex;align-items:center;gap:6px;margin-top:20px;color:#6366f1;font-weight:700;text-decoration:none;font-size:13px}
.sv-back:hover{text-decoration:underline}
</style>

<div class="sv-wrap">
  <div class="sv-header">
    <h2>
      <?php echo $my_name; ?>
      <?php if ($my_roll): ?><span class="sv-roll">Roll <?php echo $my_roll; ?></span><?php endif; ?>
    </h2>
    <p><?php echo htmlspecialchars(format_string($smartattend->name), ENT_QUOTES, 'UTF-8'); ?> &bull; Attendance Record</p>
  </div>

  <div class="sv-grid">
    <?php $ov_col = sa_pct_color($ov_pct); ?>
    <div class="sv-card">
      <div class="sv-card-label">Overall Attendance</div>
      <div class="sv-card-pct" style="color:<?php echo $ov_col; ?>"><?php echo $ov_pct; ?>%</div>
      <div class="sv-card-detail"><?php echo $ov_present; ?> / <?php echo $ov_total; ?> sessions<?php if ($ov_h > 0): ?> &bull; <?php echo round($ov_ph,1); ?> / <?php echo round($ov_h,1); ?> hrs<?php endif; ?></div>
      <div class="sv-card-bar"><div class="sv-card-fill" style="width:<?php echo $ov_pct; ?>%;background:<?php echo $ov_col; ?>;"></div></div>
    </div>
    <?php foreach ($type_stats as $tn => $ts):
        $t_pct = $ts['total'] > 0 ? round(($ts['present']/$ts['total'])*100,1) : 0;
        $t_col = sa_pct_color($t_pct);
    ?>
    <div class="sv-card">
      <div class="sv-card-label"><?php echo htmlspecialchars($tn, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="sv-card-pct" style="color:<?php echo $t_col; ?>"><?php echo $t_pct; ?>%</div>
      <div class="sv-card-detail"><?php echo $ts['present']; ?> / <?php echo $ts['total']; ?> sessions<?php if ($ts['total_h']>0): ?> &bull; <?php echo round($ts['present_h'],1); ?> / <?php echo round($ts['total_h'],1); ?> hrs<?php endif; ?></div>
      <div class="sv-card-bar"><div class="sv-card-fill" style="width:<?php echo $t_pct; ?>%;background:<?php echo $t_col; ?>;"></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="sv-month">
    <div class="sv-month-hdr">
      <div class="sv-month-title">Monthly Detail &mdash; <?php echo htmlspecialchars($month_label, ENT_QUOTES, 'UTF-8'); ?></div>
      <form method="GET" action="report.php" class="sv-sel-row">
        <input type="hidden" name="id" value="<?php echo $cm->id; ?>">
        <select name="month" class="sv-sel" onchange="this.form.submit()">
          <?php for ($m=1;$m<=12;$m++): ?><option value="<?php echo $m; ?>" <?php echo ($m==$m_req)?'selected':''; ?>><?php echo date('M',mktime(0,0,0,$m,10)); ?></option><?php endfor; ?>
        </select>
        <select name="year" class="sv-sel" onchange="this.form.submit()">
          <?php for ($y=(int)date('Y')-2;$y<=(int)date('Y')+1;$y++): ?><option value="<?php echo $y; ?>" <?php echo ($y==$y_req)?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?>
        </select>
      </form>
    </div>
    <?php if (empty($month_sessions)): ?>
      <div class="sv-empty">No sessions in <?php echo htmlspecialchars($month_label,ENT_QUOTES,'UTF-8'); ?></div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="sv-table">
          <thead><tr><th>Date</th><th>Time</th><th>Session</th><th>Type</th><th>Duration</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($month_sessions as $sess):
              $status  = $my_log_map[$sess->id] ?? 'absent';
              $s_html  = $status==='present' ? '<span class="sv-p">Present</span>' : '<span class="sv-a">Absent</span>';
              $tlbl    = !empty($sess->lecturetype) ? $sess->lecturetype : 'General';
              $sname   = !empty($sess->name) ? htmlspecialchars($sess->name,ENT_QUOTES,'UTF-8') : '&mdash;';
              $dur     = max(0, round(($sess->endtime-$sess->starttime)/3600,1));
          ?>
          <tr>
            <td><strong><?php echo date('d M',$sess->starttime); ?></strong></td>
            <td style="white-space:nowrap"><?php echo date('g:i A',$sess->starttime); ?>&ndash;<?php echo date('g:i A',$sess->endtime); ?></td>
            <td><?php echo $sname; ?></td>
            <td><span class="sv-type"><?php echo htmlspecialchars($tlbl,ENT_QUOTES,'UTF-8'); ?></span></td>
            <td><?php echo $dur; ?> hr<?php echo $dur!=1?'s':''; ?></td>
            <td><?php echo $s_html; ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <a href="<?php echo (new moodle_url('/mod/smartattend/view.php',['id'=>$cm->id]))->out(); ?>" class="sv-back">&#8592; Back to Activity</a>
</div>
<?php
    echo $OUTPUT->footer();
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
//  TEACHER VIEW — full attendance matrix
// ═══════════════════════════════════════════════════════════════════════════

$enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname', 'u.lastname ASC');

$profile_data = [];
if (!empty($enrolled_users)) {
    [$in_sql, $in_params] = $DB->get_in_or_equal(array_keys($enrolled_users));
    $profiles = $DB->get_records_select('local_studentprofile_data', "userid {$in_sql} AND draft = 0", $in_params, '', 'userid, fullname, rollno');
    foreach ($profiles as $p) { $profile_data[$p->userid] = $p; }
}

$sessions = $DB->get_records_select(
    'smartattend_sessions',
    'smartattendid = ? AND starttime >= ? AND starttime <= ? AND (isholiday IS NULL OR isholiday = 0)',
    [$cm->instance, $month_start, $month_end], 'starttime ASC'
);

$attendance_matrix = [];
if (!empty($sessions)) {
    [$in_sql, $in_params] = $DB->get_in_or_equal(array_keys($sessions));
    $logs = $DB->get_records_select('smartattend_logs', "sessionid {$in_sql}", $in_params, '', 'id, sessionid, userid, status');
    foreach ($logs as $log) {
        $attendance_matrix[$log->userid][$log->sessionid] = ['status' => $log->status, 'logid' => $log->id];
    }
}

$display_users = [];
foreach ($enrolled_users as $uid => $user) {
    $display_users[] = ['uid' => $uid, 'user' => $user, 'name' => sa_name($user, $profile_data), 'roll' => sa_roll($uid, $profile_data)];
}
usort($display_users, function($a, $b) use ($sort_req, $dir_req) {
    $cmp = ($sort_req === 'rollno') ? strnatcasecmp($a['roll'], $b['roll']) : strcasecmp($a['name'], $b['name']);
    return $dir_req === 'desc' ? -$cmp : $cmp;
});

$total_users = count($display_users);
$flip_dir    = $dir_req === 'asc' ? 'desc' : 'asc';
$sort_base   = new moodle_url('/mod/smartattend/report.php', ['id' => $cm->id, 'month' => $m_req, 'year' => $y_req]);
$ajax_url    = (new moodle_url('/mod/smartattend/ajax.php'))->out(false);
$sesskey_val = sesskey();

$sess_present = [];
foreach ($sessions as $sid => $s) {
    $cnt = 0;
    foreach ($display_users as $du) {
        if (($attendance_matrix[$du['uid']][$sid]['status'] ?? '') === 'present') $cnt++;
    }
    $sess_present[$sid] = $cnt;
}

echo $OUTPUT->header();
?>
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
*,*::before,*::after{box-sizing:border-box}
.tv-wrap{font-family:"Inter",sans-serif;padding:20px 14px 60px}
.tv-topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:20px}
.tv-title{font-size:20px;font-weight:900;color:#1e293b;margin:0}
.tv-controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.tv-sel{padding:9px 13px;border:2px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:600;background:white;cursor:pointer;color:#1e293b}
.tv-sel:focus{border-color:#6366f1;outline:none}
.tv-sw{position:relative}
.tv-sw svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);pointer-events:none}
.tv-search{padding:9px 13px 9px 36px;border:2px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:500;background:white;width:200px;transition:width .2s,border-color .2s}
.tv-search:focus{width:250px;border-color:#6366f1;outline:none}
@media(max-width:540px){.tv-search,.tv-search:focus{width:100%}}
.tv-count{font-size:12px;color:#64748b;font-weight:600;margin-bottom:10px}
.tv-scroll{overflow-x:auto;background:white;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.07);border:1px solid #f1f5f9}
.tv-table{border-collapse:collapse;width:100%}
.tv-table th,.tv-table td{border:1px solid #f0f4f8}
.th-name{text-align:left;padding:10px 14px;position:sticky;left:0;z-index:12;background:#f8fafc;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;border-right:2px solid #e2e8f0;min-width:190px;white-space:nowrap}
.th-sess{text-align:center;padding:7px 5px;font-size:10px;font-weight:700;color:#64748b;min-width:78px;background:#f8fafc}
.th-summ{text-align:center;padding:7px 5px;font-size:10px;font-weight:700;color:#64748b;min-width:68px;background:#eef2ff}
.th-sort{cursor:pointer;user-select:none;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:3px}
.th-sort:hover{color:#6366f1}
.sa-arr{opacity:.35;font-style:normal;font-size:11px}
.sa-arr.on{opacity:1;color:#6366f1}
.td-name{text-align:left;padding:7px 14px;position:sticky;left:0;z-index:6;background:white;border-right:2px solid #e2e8f0;min-width:190px}
.tv-table tr:hover .td-name{background:#fafbff}
.td-nt{font-size:13px;font-weight:600;color:#1e293b;display:block}
.td-rt{font-size:10px;color:#94a3b8;font-weight:600;margin-top:1px}
.td-cell{padding:0;min-width:78px;height:44px;text-align:center}
.ci{display:flex;align-items:center;justify-content:center;height:44px;width:100%;font-size:13px;font-weight:800;cursor:pointer;transition:filter .12s,opacity .12s;user-select:none}
.ci:hover{filter:brightness(.88)}
.c-p{background:#dcfce7;color:#15803d}
.c-a{background:#fee2e2;color:#dc2626}
.ci.loading{opacity:.45;pointer-events:none}
.td-summ{text-align:center;padding:7px 5px;font-size:11px;font-weight:700;background:#f5f7ff}
.tv-foot td{background:#f8fafc!important}
.tv-foot .td-name{font-size:10px;font-weight:700;color:#64748b;text-align:right}
.tv-nodata{text-align:center;padding:50px;color:#94a3b8;font-size:14px}
.tv-back{display:inline-flex;align-items:center;gap:6px;margin-top:20px;color:#6366f1;font-weight:700;text-decoration:none;font-size:13px}
.tv-back:hover{text-decoration:underline}
#sa-toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%) translateY(16px);background:#1e293b;color:white;padding:11px 22px;border-radius:14px;font-family:"Inter",sans-serif;font-size:14px;font-weight:700;z-index:99999;opacity:0;transition:opacity .22s,transform .22s;pointer-events:none;box-shadow:0 8px 30px rgba(0,0,0,.25);white-space:nowrap}
#sa-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
<div id="sa-toast"></div>

<div class="tv-wrap">
  <div class="tv-topbar">
    <div>
      <h2 class="tv-title">Attendance Report &mdash; <?php echo htmlspecialchars($month_label,ENT_QUOTES,'UTF-8'); ?></h2>
      <div style="margin-top:12px;display:flex;gap:10px;">
        <a href="report.php?id=<?php echo $cm->id; ?>" style="padding:6px 16px;background:#6366f1;color:white;border-radius:20px;text-decoration:none;font-size:13px;font-weight:700;box-shadow:0 2px 8px rgba(99,102,241,0.3);">Matrix View</a>
        <a href="analytics.php?id=<?php echo $cm->id; ?>" style="padding:6px 16px;background:#f1f5f9;color:#475569;border-radius:20px;text-decoration:none;font-size:13px;font-weight:700;border:1px solid #e2e8f0;transition:all 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Analytics Dashboard</a>
      </div>
    </div>
    <div class="tv-controls">
      <div class="tv-sw">
        <svg width="15" height="15" fill="#94a3b8" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.656a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z"/></svg>
        <input type="text" id="tv-search" class="tv-search" placeholder="Search name / roll&hellip;" autocomplete="off">
      </div>
      <form method="GET" action="report.php" id="tv-form" style="display:flex;gap:6px">
        <input type="hidden" name="id"   value="<?php echo $cm->id; ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_req,ENT_QUOTES,'UTF-8'); ?>">
        <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($dir_req,ENT_QUOTES,'UTF-8'); ?>">
        <select name="month" class="tv-sel" onchange="document.getElementById('tv-form').submit()">
          <?php for ($m=1;$m<=12;$m++): ?><option value="<?php echo $m; ?>" <?php echo ($m==$m_req)?'selected':''; ?>><?php echo date('F',mktime(0,0,0,$m,10)); ?></option><?php endfor; ?>
        </select>
        <select name="year" class="tv-sel" onchange="document.getElementById('tv-form').submit()">
          <?php for ($y=(int)date('Y')-2;$y<=(int)date('Y')+1;$y++): ?><option value="<?php echo $y; ?>" <?php echo ($y==$y_req)?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?>
        </select>
      </form>
    </div>
  </div>

<?php if (empty($sessions) || empty($display_users)): ?>
  <div class="tv-nodata"><?php echo empty($sessions) ? 'No sessions in '.htmlspecialchars($month_label,ENT_QUOTES,'UTF-8').'.' : 'No participants enrolled.'; ?></div>
<?php else: ?>
  <div class="tv-count"><?php echo $total_users; ?> participant(s) &bull; <?php echo count($sessions); ?> session(s)</div>
  <div class="tv-scroll">
    <table class="tv-table" id="tv-table">
      <thead><tr>
        <?php
        $n_dir = ($sort_req==='name') ? $flip_dir : 'asc';
        $n_icon = ($sort_req==='name' && $dir_req==='desc') ? '&#8595;' : '&#8593;';
        $n_on  = ($sort_req==='name') ? 'on' : '';
        $n_url = (new moodle_url($sort_base,['sort'=>'name','dir'=>$n_dir]))->out(false);
        $r_dir = ($sort_req==='rollno') ? $flip_dir : 'asc';
        $r_icon = ($sort_req==='rollno' && $dir_req==='desc') ? '&#8595;' : '&#8593;';
        $r_on  = ($sort_req==='rollno') ? 'on' : '';
        $r_url = (new moodle_url($sort_base,['sort'=>'rollno','dir'=>$r_dir]))->out(false);
        ?>
        <th class="th-name">
          <a href="<?php echo $n_url; ?>" class="th-sort">Name<em class="sa-arr <?php echo $n_on; ?>"><?php echo $n_icon; ?></em></a>
          &nbsp;
          <a href="<?php echo $r_url; ?>" class="th-sort">Roll<em class="sa-arr <?php echo $r_on; ?>"><?php echo $r_icon; ?></em></a>
        </th>
        <?php foreach ($sessions as $sid => $sess):
            $tlbl  = !empty($sess->lecturetype) ? $sess->lecturetype : '';
            $sname = !empty($sess->name) ? htmlspecialchars($sess->name,ENT_QUOTES,'UTF-8') : $tlbl;
        ?>
        <th class="th-sess">
          <div style="font-size:12px;font-weight:800;color:#1e293b"><?php echo date('d M',$sess->starttime); ?></div>
          <div style="font-size:9px;opacity:.6"><?php echo date('D',$sess->starttime); ?></div>
          <div style="font-size:9px;color:#6366f1;font-weight:700;margin-top:2px"><?php echo date('g A',$sess->starttime); ?>&ndash;<?php echo date('g A',$sess->endtime); ?></div>
          <?php if ($sname): ?><div style="font-size:9px;color:#94a3b8;margin-top:2px"><?php echo $sname; ?></div><?php endif; ?>
        </th>
        <?php endforeach; ?>
        <th class="th-summ">P&nbsp;/&nbsp;Total</th>
      </tr></thead>
      <tbody>
        <?php foreach ($display_users as $du):
            $uid = $du['uid'];
            $row_p   = 0;
            $row_tot = count($sessions);
            $sk      = strtolower($du['name'].' '.$du['roll']);
        ?>
        <tr data-search="<?php echo htmlspecialchars($sk,ENT_QUOTES,'UTF-8'); ?>">
          <td class="td-name">
            <span class="td-nt"><?php echo $du['name']; ?></span>
            <?php if ($du['roll']): ?><span class="td-rt">Roll: <?php echo $du['roll']; ?></span><?php endif; ?>
          </td>
          <?php foreach ($sessions as $sid => $sess):
              $entry  = $attendance_matrix[$uid][$sid] ?? null;
              $status = $entry ? $entry['status'] : null;
              $is_p   = ($status === 'present');
              if ($is_p) $row_p++;
              $cc  = $is_p ? 'c-p' : 'c-a';
              $ct  = $is_p ? 'P' : 'A';
              $nxt = $is_p ? 'absent' : 'present';
          ?>
          <td class="td-cell">
            <div class="ci <?php echo $cc; ?>"
                 data-sid="<?php echo $sid; ?>"
                 data-uid="<?php echo $uid; ?>"
                 data-next="<?php echo $nxt; ?>"
                 title="Click to toggle"
                 onclick="tvToggle(this)"><?php echo $ct; ?></div>
          </td>
          <?php endforeach; ?>
          <?php
          $rp  = $row_tot > 0 ? round(($row_p/$row_tot)*100) : 0;
          $rc  = sa_pct_color($rp);
          ?>
          <td class="td-summ" style="color:<?php echo $rc; ?>"><?php echo $row_p; ?>/<?php echo $row_tot; ?><br><span style="font-size:10px"><?php echo $rp; ?>%</span></td>
        </tr>
        <?php endforeach; ?>

        <tr class="tv-foot">
          <td class="td-name" style="text-align:right;padding-right:14px;font-size:10px;font-weight:700;color:#64748b">Present &#9658;</td>
          <?php foreach ($sessions as $sid => $s):
              $cnt = $sess_present[$sid];
              $pct = $total_users > 0 ? round(($cnt/$total_users)*100) : 0;
              $col = sa_pct_color($pct);
          ?>
          <td class="td-summ" style="color:<?php echo $col; ?>"><?php echo $cnt; ?>/<?php echo $total_users; ?><br><span style="font-size:10px"><?php echo $pct; ?>%</span></td>
          <?php endforeach; ?>
          <td class="td-summ">&mdash;</td>
        </tr>
      </tbody>
    </table>
  </div>
<?php endif; ?>

  <a href="<?php echo (new moodle_url('/mod/smartattend/view.php',['id'=>$cm->id]))->out(); ?>" class="tv-back">&#8592; Back to Activity</a>
</div>

<script>
var SA_AJAX = "<?php echo $ajax_url; ?>";
var SA_KEY  = "<?php echo $sesskey_val; ?>";

function tvToast(msg, ok) {
    var t = document.getElementById("sa-toast");
    t.textContent = msg;
    t.style.background = ok ? "#16a34a" : "#dc2626";
    t.classList.add("show");
    clearTimeout(t._tmr);
    t._tmr = setTimeout(function() { t.classList.remove("show"); }, 2800);
}

function tvToggle(el) {
    if (el.classList.contains("loading")) return;
    el.classList.add("loading");
    var sid  = el.getAttribute("data-sid");
    var uid  = el.getAttribute("data-uid");
    var next = el.getAttribute("data-next");
    el.textContent = "\u2026";

    var fd = new FormData();
    fd.append("action",    "update_attendance");
    fd.append("sessionid", sid);
    fd.append("userid",    uid);
    fd.append("status",    next);
    fd.append("sesskey",   SA_KEY);

    fetch(SA_AJAX, {method: "POST", body: fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            el.classList.remove("loading");
            if (d.success) {
                var p = (d.status === "present");
                el.className = "ci " + (p ? "c-p" : "c-a");
                el.textContent = p ? "P" : "A";
                el.setAttribute("data-next", p ? "absent" : "present");
                tvUpdateRow(el);
                tvUpdateCol(sid);
                tvToast(p ? "\u2713 Marked Present" : "\u2713 Marked Absent", true);
            } else {
                el.textContent = (next === "present") ? "A" : "P";
                tvToast("Error: " + (d.error || "Unknown"), false);
            }
        })
        .catch(function() {
            el.classList.remove("loading");
            el.textContent = (next === "present") ? "A" : "P";
            tvToast("Network error \u2014 retry", false);
        });
}

function tvUpdateRow(el) {
    var row = el.closest("tr");
    var cells = row.querySelectorAll(".ci");
    var p = 0, tot = cells.length;
    cells.forEach(function(c) { if (c.textContent.trim() === "P") p++; });
    var pct = tot > 0 ? Math.round((p/tot)*100) : 0;
    var col = pct >= 75 ? "#16a34a" : (pct >= 60 ? "#d97706" : "#dc2626");
    var s = row.querySelector(".td-summ");
    if (s) { s.style.color = col; s.innerHTML = p+"/"+tot+"<br><span style='font-size:10px'>"+pct+"%</span>"; }
}

function tvUpdateCol(sid) {
    var cells = document.querySelectorAll("[data-sid='"+sid+"']");
    var p = 0, tot = cells.length;
    cells.forEach(function(c) { if (c.textContent.trim() === "P") p++; });
    var pct = tot > 0 ? Math.round((p/tot)*100) : 0;
    var col = pct >= 75 ? "#16a34a" : (pct >= 60 ? "#d97706" : "#dc2626");
    // find column index from first data row
    var first = document.querySelector("#tv-table tbody tr[data-search]");
    if (!first) return;
    var idx = -1;
    first.querySelectorAll("td").forEach(function(td, i) { if (td.querySelector("[data-sid='"+sid+"']")) idx = i; });
    if (idx < 0) return;
    var foot = document.querySelector(".tv-foot");
    if (!foot) return;
    var ftd = foot.querySelectorAll("td")[idx];
    if (ftd) { ftd.style.color = col; ftd.innerHTML = p+"/"+tot+"<br><span style='font-size:10px'>"+pct+"%</span>"; }
}

document.getElementById("tv-search").addEventListener("input", function() {
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll("#tv-table tbody tr[data-search]").forEach(function(r) {
        r.style.display = (!q || r.getAttribute("data-search").indexOf(q) !== -1) ? "" : "none";
    });
});
</script>
<?php
echo $OUTPUT->footer();
