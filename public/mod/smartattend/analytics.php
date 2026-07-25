<?php
/**
 * Attendance Analytics Dashboard for smartattend.
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('smartattend', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/smartattend:manage', $context); // Only teachers/admins

// Filters
$start_date      = optional_param('start', date('Y-m-01'), PARAM_TEXT);
$end_date        = optional_param('end', date('Y-m-t'), PARAM_TEXT);
$f_degree        = optional_param('degree', '', PARAM_TEXT);
$f_collegename   = optional_param('collegename', '', PARAM_TEXT);
$f_degreetype    = optional_param('degreetype', '', PARAM_TEXT);
$f_admissionyear = optional_param('admissionyear', '', PARAM_TEXT);

$PAGE->set_url('/mod/smartattend/analytics.php', ['id' => $cm->id]);
$PAGE->set_title('Attendance Analytics');
$PAGE->set_heading(format_string($course->fullname));

// Fetch distinct values for filters
$degrees = $DB->get_records_sql("SELECT DISTINCT degree FROM {local_studentprofile_data} WHERE degree IS NOT NULL AND degree != ''");
$colleges = $DB->get_records_sql("SELECT DISTINCT collegename FROM {local_studentprofile_data} WHERE collegename IS NOT NULL AND collegename != ''");
$degreetypes = $DB->get_records_sql("SELECT DISTINCT degreetype FROM {local_studentprofile_data} WHERE degreetype IS NOT NULL AND degreetype != ''");
$admissionyears = $DB->get_records_sql("SELECT DISTINCT admissionyear FROM {local_studentprofile_data} WHERE admissionyear IS NOT NULL AND admissionyear != '' ORDER BY admissionyear DESC");

echo $OUTPUT->header();
?>
<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
.av-wrap * { box-sizing: border-box; font-family: "Inter", sans-serif; }
.av-wrap { padding: 20px 14px 60px; min-height: 100vh; }
.av-topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
.av-title { font-size: 20px; font-weight: 900; color: #1e293b; margin: 0; }
.av-nav { margin-top: 12px; display: flex; gap: 10px; }
.av-nav a { padding: 6px 16px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 700; transition: all 0.2s; }
.av-nav .matrix-btn { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.av-nav .matrix-btn:hover { background: #e2e8f0; }
.av-nav .analytics-btn { background: #6366f1; color: white; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
.av-filters { display: flex; gap: 10px; flex-wrap: wrap; background: white; padding: 16px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; align-items: flex-end; }
.av-filter-group { display: flex; flex-direction: column; gap: 4px; }
.av-filter-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.av-filter-input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #1e293b; background: #f8fafc; }
.av-filter-input:focus { outline: none; border-color: #6366f1; }
.av-btn { padding: 9px 16px; background: #1e293b; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.av-btn:hover { background: #334155; }

.av-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
.av-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; }
.av-card-val { font-size: 32px; font-weight: 900; color: #1e293b; line-height: 1.1; margin-bottom: 4px; }
.av-card-lbl { font-size: 12px; font-weight: 600; color: #64748b; }

.av-charts { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px; }
@media(min-width: 900px) { .av-charts { grid-template-columns: 2fr 1fr; } }
.av-chart-box { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; position: relative; }
.av-chart-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 16px; }
.chart-container { position: relative; height: 300px; width: 100%; }

.av-lists { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px; }
@media(min-width: 900px) { .av-lists { grid-template-columns: 1fr 1fr; } }
.av-list { list-style: none; padding: 0; margin: 0; }
.av-list-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.1s; }
.av-list-item:hover { background: #f8fafc; }
.av-list-item:last-child { border-bottom: none; }
.av-li-name { font-size: 13px; font-weight: 600; color: #1e293b; }
.av-li-roll { font-size: 11px; color: #94a3b8; }
.av-li-pct { font-size: 13px; font-weight: 800; padding: 4px 10px; border-radius: 12px; }
.pct-high { background: #dcfce7; color: #15803d; }
.pct-mid { background: #fef3c7; color: #b45309; }
.pct-low { background: #fee2e2; color: #b91c1c; }

.av-table-wrap { overflow-x: auto; background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; padding: 20px; }
.av-table { width: 100%; border-collapse: collapse; }
.av-table th { text-align: left; padding: 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
.av-table td { padding: 12px; font-size: 13px; font-weight: 500; color: #1e293b; border-bottom: 1px solid #f8fafc; }
.av-loading { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #6366f1; z-index: 10; font-size: 14px; border-radius: 16px; display: none; }
</style>

<div class="av-wrap">
  <div class="av-topbar">
    <div>
      <h2 class="av-title">Attendance Analytics Dashboard</h2>
      <div class="av-nav">
        <a href="report.php?id=<?php echo $cm->id; ?>" class="matrix-btn">Matrix View</a>
        <a href="analytics.php?id=<?php echo $cm->id; ?>" class="analytics-btn">Analytics Dashboard</a>
      </div>
    </div>
  </div>

  <div class="av-filters">
    <div class="av-filter-group">
      <span class="av-filter-label">Start Date</span>
      <input type="date" id="f-start" class="av-filter-input" value="<?php echo $start_date; ?>">
    </div>
    <div class="av-filter-group">
      <span class="av-filter-label">End Date</span>
      <input type="date" id="f-end" class="av-filter-input" value="<?php echo $end_date; ?>">
    </div>
    <div class="av-filter-group">
      <span class="av-filter-label">College Name</span>
      <select id="f-collegename" class="av-filter-input">
        <option value="">All Colleges</option>
        <?php foreach($colleges as $c): ?>
          <option value="<?php echo htmlspecialchars($c->collegename); ?>" <?php echo ($f_collegename===$c->collegename)?'selected':''; ?>><?php echo htmlspecialchars($c->collegename); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="av-filter-group">
      <span class="av-filter-label">Degree Type</span>
      <select id="f-degreetype" class="av-filter-input">
        <option value="">All Types</option>
        <?php foreach($degreetypes as $t): ?>
          <option value="<?php echo htmlspecialchars($t->degreetype); ?>" <?php echo ($f_degreetype===$t->degreetype)?'selected':''; ?>><?php echo htmlspecialchars($t->degreetype); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="av-filter-group">
      <span class="av-filter-label">Degree</span>
      <select id="f-degree" class="av-filter-input">
        <option value="">All Degrees</option>
        <?php foreach($degrees as $d): ?>
          <option value="<?php echo htmlspecialchars($d->degree); ?>" <?php echo ($f_degree===$d->degree)?'selected':''; ?>><?php echo htmlspecialchars($d->degree); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="av-filter-group">
      <span class="av-filter-label">Admission Year</span>
      <select id="f-admissionyear" class="av-filter-input">
        <option value="">All Years</option>
        <?php foreach($admissionyears as $y): ?>
          <option value="<?php echo htmlspecialchars($y->admissionyear); ?>" <?php echo ($f_admissionyear===(string)$y->admissionyear)?'selected':''; ?>><?php echo htmlspecialchars($y->admissionyear); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="av-filter-group">
      <button class="av-btn" onclick="loadAnalytics()">Apply Filters</button>
    </div>
  </div>

  <div class="av-grid">
    <div class="av-card"><div class="av-card-val" id="val-students">-</div><div class="av-card-lbl">Total Students</div></div>
    <div class="av-card"><div class="av-card-val" id="val-pct">-</div><div class="av-card-lbl">Attendance %</div></div>
    <div class="av-card"><div class="av-card-val" id="val-sessions">-</div><div class="av-card-lbl">Total Sessions</div></div>
    <div class="av-card"><div class="av-card-val" id="val-ptoday">-</div><div class="av-card-lbl">Present Today</div></div>
    <div class="av-card"><div class="av-card-val" id="val-atoday">-</div><div class="av-card-lbl">Absent Today</div></div>
    <div class="av-card"><div class="av-card-val" id="val-below" style="color:#b91c1c;">-</div><div class="av-card-lbl">Students < 75%</div></div>
  </div>

  <div class="av-charts">
    <div class="av-chart-box">
      <div class="av-loading" id="load-trend">Loading...</div>
      <div class="av-chart-title">Overall Attendance Trend</div>
      <div class="chart-container"><canvas id="chartTrend"></canvas></div>
    </div>
    <div class="av-chart-box">
      <div class="av-loading" id="load-dist">Loading...</div>
      <div class="av-chart-title">Present vs Absent</div>
      <div class="chart-container"><canvas id="chartDist"></canvas></div>
    </div>
  </div>

  <div class="av-charts" style="grid-template-columns: 1fr 1fr;">
    <div class="av-chart-box">
      <div class="av-loading" id="load-time">Loading...</div>
      <div class="av-chart-title">Attendance by Time Slot</div>
      <div class="chart-container"><canvas id="chartTime"></canvas></div>
    </div>
    <div class="av-chart-box">
      <div class="av-loading" id="load-deg">Loading...</div>
      <div class="av-chart-title">Attendance by Degree (Batch)</div>
      <div class="chart-container"><canvas id="chartDegree"></canvas></div>
    </div>
  </div>

  <div class="av-chart-box" style="margin-bottom: 24px;">
    <div class="av-loading" id="load-student">Loading...</div>
    <div class="av-chart-title">Student-wise Attendance</div>
    <div style="overflow-y: auto; max-height: 400px; padding-right: 10px;">
      <div class="chart-container" id="studentChartContainer" style="height: 400px; width: 100%;">
        <canvas id="chartStudent"></canvas>
      </div>
    </div>
  </div>

  <div class="av-lists">
    <div class="av-chart-box">
      <div class="av-chart-title">Top 10 Students</div>
      <ul class="av-list" id="list-top"></ul>
    </div>
    <div class="av-chart-box">
      <div class="av-chart-title">Bottom 10 Students</div>
      <ul class="av-list" id="list-bottom"></ul>
    </div>
  </div>

  <div class="av-table-wrap">
    <div class="av-chart-title">Students Below Threshold (< 75%)</div>
    <table class="av-table">
      <thead><tr><th>Student</th><th>Roll No</th><th>Present</th><th>Absent</th><th>Attendance %</th><th>Status</th></tr></thead>
      <tbody id="table-below"></tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartInstances = {};

function initChart(id, type, data, options) {
    const ctx = document.getElementById(id).getContext('2d');
    if (chartInstances[id]) { chartInstances[id].destroy(); }
    chartInstances[id] = new Chart(ctx, { type: type, data: data, options: Object.assign({ responsive: true, maintainAspectRatio: false }, options) });
}

function dateToTS(dateStr, end) {
    if(!dateStr) return 0;
    const d = new Date(dateStr);
    if(end) d.setHours(23, 59, 59, 999);
    else d.setHours(0, 0, 0, 0);
    return Math.floor(d.getTime()/1000);
}

function getPctClass(pct) {
    if(pct >= 75) return 'pct-high';
    if(pct >= 60) return 'pct-mid';
    return 'pct-low';
}

function loadAnalytics() {
    const start = document.getElementById('f-start').value;
    const end = document.getElementById('f-end').value;
    const deg = document.getElementById('f-degree').value;
    const col = document.getElementById('f-collegename').value;
    const dt  = document.getElementById('f-degreetype').value;
    const ay  = document.getElementById('f-admissionyear').value;
    
    document.querySelectorAll('.av-loading').forEach(e => e.style.display = 'flex');

    const fd = new FormData();
    fd.append('id', <?php echo $cm->id; ?>);
    fd.append('start', dateToTS(start, false));
    fd.append('end', dateToTS(end, true));
    fd.append('degree', deg);
    fd.append('collegename', col);
    fd.append('degreetype', dt);
    fd.append('admissionyear', ay);

    fetch('analytics_data.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
          document.querySelectorAll('.av-loading').forEach(e => e.style.display = 'none');
          if(data.error) { alert(data.error); return; }

          // Overview
          document.getElementById('val-students').textContent = data.overview.total_students;
          document.getElementById('val-pct').textContent = data.overview.attendance_pct + '%';
          document.getElementById('val-sessions').textContent = data.overview.total_sessions;
          document.getElementById('val-ptoday').textContent = data.overview.present_today;
          document.getElementById('val-atoday').textContent = data.overview.absent_today;
          document.getElementById('val-below').textContent = data.overview.below_threshold_count;

          // Charts
          initChart('chartTrend', 'line', {
              labels: data.trend.labels,
              datasets: [{
                  label: 'Attendance %',
                  data: data.trend.data,
                  borderColor: '#6366f1',
                  backgroundColor: 'rgba(99,102,241,0.1)',
                  tension: 0.3, fill: true
              }]
          }, { scales: { y: { min: 0, max: 100 } } });

          initChart('chartDist', 'doughnut', {
              labels: ['Present', 'Absent'],
              datasets: [{
                  data: [data.distribution.present, data.distribution.absent],
                  backgroundColor: ['#16a34a', '#dc2626'],
                  borderWidth: 0
              }]
          }, { cutout: '70%', plugins: { legend: { position: 'bottom' } } });

          initChart('chartTime', 'bar', {
              labels: data.time_slots.labels,
              datasets: [{
                  label: 'Attendance %',
                  data: data.time_slots.data,
                  backgroundColor: '#3b82f6',
                  borderRadius: 6
              }]
          }, { scales: { y: { min: 0, max: 100 } } });

          initChart('chartDegree', 'bar', {
              labels: data.degrees.labels,
              datasets: [{
                  label: 'Attendance %',
                  data: data.degrees.data,
                  backgroundColor: '#8b5cf6',
                  borderRadius: 6
              }]
          }, { scales: { y: { min: 0, max: 100 } } });

          // Student Chart
          const studentLabels = [];
          const studentData = [];
          // Sort alphabetically by name
          const students = [...data.all_students].sort((a,b) => a.name.localeCompare(b.name));
          students.forEach(s => {
              studentLabels.push(s.name);
              studentData.push(s.pct);
          });
          
          // Adjust container height dynamically to fit all students nicely (e.g. 25px per student, min 300px)
          const chartHeight = Math.max(300, students.length * 25);
          document.getElementById('studentChartContainer').style.height = chartHeight + 'px';

          initChart('chartStudent', 'bar', {
              labels: studentLabels,
              datasets: [{
                  label: 'Attendance %',
                  data: studentData,
                  backgroundColor: '#f59e0b',
                  borderRadius: 4
              }]
          }, { 
              indexAxis: 'y', // Horizontal bar chart
              scales: { x: { min: 0, max: 100 } },
              maintainAspectRatio: false
          });

          // Lists
          const renderList = (items, elId) => {
              const el = document.getElementById(elId);
              el.innerHTML = '';
              items.forEach(i => {
                  el.innerHTML += `<li class="av-list-item">
                    <div><div class="av-li-name">${i.name}</div><div class="av-li-roll">${i.roll||'--'}</div></div>
                    <div class="av-li-pct ${getPctClass(i.pct)}">${i.pct}%</div>
                  </li>`;
              });
          };
          renderList(data.top_10, 'list-top');
          renderList(data.bottom_10, 'list-bottom');

          // Table
          const tbody = document.getElementById('table-below');
          tbody.innerHTML = '';
          data.below_threshold.forEach(i => {
              tbody.innerHTML += `<tr>
                <td>${i.name}</td>
                <td>${i.roll||'--'}</td>
                <td style="color:#16a34a;font-weight:700">${i.p}</td>
                <td style="color:#dc2626;font-weight:700">${i.a}</td>
                <td><span class="av-li-pct ${getPctClass(i.pct)}">${i.pct}%</span></td>
                <td style="color:#b91c1c;font-weight:700;font-size:11px;">Needs Attention</td>
              </tr>`;
          });
      })
      .catch(e => {
          console.error(e);
          document.querySelectorAll('.av-loading').forEach(e => e.style.display = 'none');
          alert('Failed to load analytics data.');
      });
}

document.addEventListener('DOMContentLoaded', loadAnalytics);
</script>
<?php
echo $OUTPUT->footer();
