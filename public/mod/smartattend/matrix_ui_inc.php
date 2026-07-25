<?php
// Extracted to be reused in view.php
$days = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
$slots = ['09:00-10:00', '10:00-11:00', '11:00-13:00', '13:00-14:00'];

$grid_html = '
<style>
    .premium-schedule-container { background: #f8f9fc; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; overflow-x: auto; margin-bottom: 20px; }
    .premium-schedule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .premium-schedule-title { font-size: 24px; font-weight: 700; color: #111; margin: 0; }
    .premium-schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; background: #ffffff; border: 1px solid #eef0f5; border-radius: 12px; overflow: hidden; }
    .premium-schedule-table th, .premium-schedule-table td { border: 1px solid #eef0f5; padding: 0; text-align: center; vertical-align: middle; height: 80px; min-width: 120px; }
    .premium-schedule-table th { background: #ffffff; font-size: 13px; font-weight: 600; color: #555; height: 50px; }
    .day-label { font-weight: 600; color: #333; font-size: 14px; background: #ffffff; width: 80px; }
    .slot-cell { background: #f9fbff; cursor: pointer; transition: all 0.2s ease; position: relative; }
    .slot-cell:hover { background: #f0f4f8; }
    .slot-empty-icon { width: 32px; height: 32px; border-radius: 50%; background: #e8ebf1; color: #9aa5b1; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 18px; transition: all 0.2s ease; }
    .slot-cell:hover .slot-empty-icon { background: #d9e0e8; color: #666; }
    .slot-active-card { background: linear-gradient(135deg, #4285f4 0%, #2b6cb0 100%); border-radius: 8px; padding: 10px; margin: 6px; color: white; text-align: left; box-shadow: 0 4px 10px rgba(66, 133, 244, 0.3); display: flex; flex-direction: column; justify-content: space-between; height: calc(100% - 12px); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .slot-active-card.theme-purple { background: linear-gradient(135deg, #a48de5 0%, #805ad5 100%); box-shadow: 0 4px 10px rgba(164, 141, 229, 0.3); }
    .slot-header { font-size: 12px; font-weight: 600; margin-bottom: 8px; }
    .slot-footer { display: flex; justify-content: space-between; align-items: center; }
    .slot-icon { background: rgba(255,255,255,0.2); border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; }
    .slot-edit { font-size: 11px; opacity: 0.9; }
    @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
</style>
<div class="premium-schedule-container">
    <div class="premium-schedule-header">
        <h2 class="premium-schedule-title">Weekly Schedule</h2>
    </div>
    <table class="premium-schedule-table">
        <thead>
            <tr>
                <th></th>
                <th>9:00 - 10:00 AM</th>
                <th>10:00 - 11:00 AM</th>
                <th>11:00 - 1:00 PM</th>
                <th>1:00 - 2:00 PM</th>
            </tr>
        </thead>
        <tbody id="schedule_matrix_body">';

foreach ($days as $short => $long) {
    $grid_html .= '<tr><td class="day-label">' . $long . '</td>';
    foreach ($slots as $slot) {
        $theme = ($short == 'Sat' || $short == 'Sun') ? 'theme-purple' : '';
        $grid_html .= '<td class="slot-cell" data-day="'.$short.'" data-slot="'.$slot.'" data-theme="'.$theme.'">';
        $grid_html .= '<div class="slot-empty-icon">&#43;</div></td>';
    }
    $grid_html .= '</tr>';
}

$grid_html .= '</tbody></table></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var hiddenInput = document.querySelector("input[name=schedule]");
        if (!hiddenInput) return;
        
        var cells = document.querySelectorAll(".slot-cell");
        var sessionCounter = 1;
        var activeSchedule = {};
        
        function renderCell(cell, isActive) {
            var theme = cell.getAttribute("data-theme");
            if (isActive) {
                cell.innerHTML = `
                    <div class="slot-active-card ` + theme + `">
                        <div class="slot-header">Session ` + sessionCounter++ + `</div>
                        <div class="slot-footer">
                            <div class="slot-icon">&#128100;</div>
                            <div class="slot-edit">Edit &#9998;</div>
                        </div>
                    </div>`;
            } else {
                cell.innerHTML = `<div class="slot-empty-icon">&#43;</div>`;
            }
        }
        
        if (hiddenInput.value) {
            try { activeSchedule = JSON.parse(hiddenInput.value); } catch(e) { activeSchedule = {}; }
        }
        
        cells.forEach(function(cell) {
            var day = cell.getAttribute("data-day");
            var slot = cell.getAttribute("data-slot");
            var isActive = (activeSchedule[day] && activeSchedule[day].includes(slot));
            if (isActive) renderCell(cell, true);
        });
        
        cells.forEach(function(cell) {
            cell.addEventListener("click", function() {
                var day = cell.getAttribute("data-day");
                var slot = cell.getAttribute("data-slot");
                
                if (!activeSchedule[day]) activeSchedule[day] = [];
                var slotIndex = activeSchedule[day].indexOf(slot);
                if (slotIndex > -1) {
                    activeSchedule[day].splice(slotIndex, 1);
                    if (activeSchedule[day].length === 0) delete activeSchedule[day];
                } else {
                    activeSchedule[day].push(slot);
                }
                
                sessionCounter = 1;
                cells.forEach(function(c) {
                    var d = c.getAttribute("data-day");
                    var s = c.getAttribute("data-slot");
                    var iActive = (activeSchedule[d] && activeSchedule[d].includes(s));
                    renderCell(c, iActive);
                });
                
                hiddenInput.value = Object.keys(activeSchedule).length > 0 ? JSON.stringify(activeSchedule) : "";
                
                // If there is a form around this with id "schedule-form", we might Auto-Save or wait for a Save button.
            });
        });
    });
</script>';
