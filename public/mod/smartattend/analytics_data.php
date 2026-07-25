<?php
/**
 * AJAX endpoint for Attendance Analytics Dashboard data.
 */
define('AJAX_SCRIPT', true);
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('smartattend', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/smartattend:manage', $context); // Only teachers/admins

// Filters
$start_ts = optional_param('start', 0, PARAM_INT);
$end_ts   = optional_param('end', 0, PARAM_INT);
$f_degree = optional_param('degree', '', PARAM_TEXT);
$f_collegename = optional_param('collegename', '', PARAM_TEXT);
$f_degreetype  = optional_param('degreetype', '', PARAM_TEXT);
$f_admissionyear = optional_param('admissionyear', '', PARAM_TEXT);

// Default to current month if no dates provided
if (!$start_ts || !$end_ts) {
    $start_ts = strtotime(date('Y-m-01 00:00:00'));
    $end_ts   = strtotime(date('Y-m-t 23:59:59'));
}

// 1. Enrolled Users & Profiles
$enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname', 'u.lastname ASC');
if (empty($enrolled_users)) {
    echo json_encode(['error' => 'No enrolled users found.']);
    exit;
}

$profile_data = [];
[$in_sql, $in_params] = $DB->get_in_or_equal(array_keys($enrolled_users));
$profiles = $DB->get_records_select('local_studentprofile_data', "userid {$in_sql} AND draft = 0", $in_params, '', 'userid, fullname, rollno, degree, degreetype, collegename, admissionyear');
foreach ($profiles as $p) {
    $profile_data[$p->userid] = $p;
}

// Apply Profile Filters
$filtered_uids = [];
foreach ($enrolled_users as $uid => $u) {
    $p = $profile_data[$uid] ?? null;
    if ($f_degree !== '' && (!$p || $p->degree !== $f_degree)) continue;
    if ($f_collegename !== '' && (!$p || $p->collegename !== $f_collegename)) continue;
    if ($f_degreetype !== '' && (!$p || $p->degreetype !== $f_degreetype)) continue;
    if ($f_admissionyear !== '' && (!$p || (string)$p->admissionyear !== $f_admissionyear)) continue;
    
    $filtered_uids[] = $uid;
}

if (empty($filtered_uids)) {
    echo json_encode(['error' => 'No users match the selected filters.']);
    exit;
}

// 2. Sessions (exclude holidays)
$sessions = $DB->get_records_select(
    'smartattend_sessions',
    'smartattendid = ? AND starttime >= ? AND starttime <= ? AND (isholiday IS NULL OR isholiday = 0)',
    [$cm->instance, $start_ts, $end_ts], 'starttime ASC'
);

if (empty($sessions)) {
    echo json_encode(['error' => 'No sessions found for this date range.']);
    exit;
}

// 3. Logs
[$s_in_sql, $s_in_params] = $DB->get_in_or_equal(array_keys($sessions));
[$u_in_sql, $u_in_params] = $DB->get_in_or_equal($filtered_uids);
$logs = $DB->get_records_select(
    'smartattend_logs', 
    "sessionid {$s_in_sql} AND userid {$u_in_sql}", 
    array_merge($s_in_params, $u_in_params), 
    '', 'id, sessionid, userid, status'
);

$log_matrix = [];
foreach ($logs as $log) {
    $log_matrix[$log->userid][$log->sessionid] = $log->status;
}

// ── Metrics Computation ───────────────────────────────────────────────────

$total_students = count($filtered_uids);
$total_sessions = count($sessions);
$total_possible = $total_students * $total_sessions;
$total_present = 0;
$total_absent = 0;

$student_stats = []; // [uid => ['p' => x, 'a' => y, 'tot' => z]]
foreach ($filtered_uids as $uid) {
    $student_stats[$uid] = ['p' => 0, 'a' => 0, 'tot' => $total_sessions, 'name' => '', 'roll' => '', 'degree' => ''];
    $pd = $profile_data[$uid] ?? null;
    $student_stats[$uid]['name'] = $pd && !empty($pd->fullname) ? $pd->fullname : fullname($enrolled_users[$uid]);
    $student_stats[$uid]['roll'] = $pd ? $pd->rollno : '';
    $student_stats[$uid]['degree'] = $pd ? $pd->degree : 'Unknown';
}

$daily_trend = []; // [date => ['p' => x, 'a' => y]]
$heatmap = [];     // [date => percent]
$time_slots = [];  // [Hour => ['p' => x, 'tot' => y]]
$degree_stats = []; // [Degree => ['p' => x, 'tot' => y]]

// Today stats
$today_start = strtotime('today');
$today_end = strtotime('tomorrow') - 1;
$present_today = 0;
$absent_today = 0;
$has_session_today = false;

foreach ($sessions as $sid => $sess) {
    $date_str = date('Y-m-d', $sess->starttime);
    $hour_str = date('g A', $sess->starttime);
    
    if (!isset($daily_trend[$date_str])) $daily_trend[$date_str] = ['p' => 0, 'a' => 0];
    if (!isset($time_slots[$hour_str])) $time_slots[$hour_str] = ['p' => 0, 'tot' => 0];
    
    $is_today = ($sess->starttime >= $today_start && $sess->starttime <= $today_end);
    if ($is_today) $has_session_today = true;
    
    foreach ($filtered_uids as $uid) {
        $status = $log_matrix[$uid][$sid] ?? 'absent';
        
        $deg = $student_stats[$uid]['degree'];
        if (!isset($degree_stats[$deg])) $degree_stats[$deg] = ['p' => 0, 'tot' => 0];
        
        $degree_stats[$deg]['tot']++;
        $time_slots[$hour_str]['tot']++;
        
        if ($status === 'present') {
            $total_present++;
            $student_stats[$uid]['p']++;
            $daily_trend[$date_str]['p']++;
            $time_slots[$hour_str]['p']++;
            $degree_stats[$deg]['p']++;
            if ($is_today) $present_today++;
        } else {
            $total_absent++;
            $student_stats[$uid]['a']++;
            $daily_trend[$date_str]['a']++;
            if ($is_today) $absent_today++;
        }
    }
}

$overall_pct = $total_possible > 0 ? round(($total_present / $total_possible) * 100, 1) : 0;

// Rankings
$ranking_array = [];
$below_threshold = [];
$threshold = 75.0;

foreach ($student_stats as $uid => $st) {
    $pct = $st['tot'] > 0 ? round(($st['p'] / $st['tot']) * 100, 1) : 0;
    $item = [
        'uid' => $uid,
        'name' => $st['name'],
        'roll' => $st['roll'],
        'p' => $st['p'],
        'a' => $st['a'],
        'pct' => $pct
    ];
    $ranking_array[] = $item;
    
    if ($pct < $threshold) {
        $below_threshold[] = $item;
    }
}

// Sort by pct desc
usort($ranking_array, fn($a, $b) => $b['pct'] <=> $a['pct']);
$top_10 = array_slice($ranking_array, 0, 10);
$bottom_10 = array_slice(array_reverse($ranking_array), 0, 10);

// Prepare Chart Data
$chart_trend = ['labels' => [], 'data' => []];
ksort($daily_trend);
foreach ($daily_trend as $d => $v) {
    $chart_trend['labels'][] = date('d M', strtotime($d));
    $chart_trend['data'][] = ($v['p'] + $v['a']) > 0 ? round(($v['p'] / ($v['p'] + $v['a'])) * 100) : 0;
}

$chart_time = ['labels' => [], 'data' => []];
uksort($time_slots, function($a, $b) { return strtotime($a) - strtotime($b); });
foreach ($time_slots as $t => $v) {
    $chart_time['labels'][] = $t;
    $chart_time['data'][] = $v['tot'] > 0 ? round(($v['p'] / $v['tot']) * 100) : 0;
}

$chart_degree = ['labels' => [], 'data' => []];
foreach ($degree_stats as $d => $v) {
    $chart_degree['labels'][] = $d ?: 'Other';
    $chart_degree['data'][] = $v['tot'] > 0 ? round(($v['p'] / $v['tot']) * 100) : 0;
}

echo json_encode([
    'success' => true,
    'overview' => [
        'total_students' => $total_students,
        'total_sessions' => $total_sessions,
        'present_today' => $has_session_today ? $present_today : '-',
        'absent_today' => $has_session_today ? $absent_today : '-',
        'attendance_pct' => $overall_pct,
        'below_threshold_count' => count($below_threshold)
    ],
    'distribution' => [
        'present' => $total_present,
        'absent' => $total_absent
    ],
    'trend' => $chart_trend,
    'time_slots' => $chart_time,
    'degrees' => $chart_degree,
    'top_10' => $top_10,
    'bottom_10' => $bottom_10,
    'below_threshold' => $below_threshold,
    'all_students' => $ranking_array
]);
