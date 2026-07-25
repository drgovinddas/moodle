<?php
/**
 * Teacher QR Display view. Placed on projector to show QR code.
 *
 * @package    mod_smartattend
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);

$cm = get_coursemodule_from_id('smartattend', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/smartattend:manage', $context);

$PAGE->set_url('/mod/smartattend/teacher_display.php', array('id' => $cm->id, 'sessionid' => $sessionid));
$PAGE->set_title('QR Display');
$PAGE->set_heading('Live QR Display');

// We use plain HTML for the display to make it fullscreen and clean
echo $OUTPUT->header();

echo '<div style="text-align:center; padding-top: 50px;">
        <h2>Scan to record Attendance</h2>
        <div id="qr-code-container" style="margin: 30px auto; width: 300px; height: 300px; border: 1px solid #ccc; background:#fff">
           <!-- QR Code goes here -->
           <h3 id="qr-token-text" style="padding-top:120px">Loading...</h3>
        </div>
        <p>Token expires in <span id="timer">120</span>s</p>
      </div>';

      
// Include a script to actually fetch and rotate the token.
// Also require some generic QR JS library in reality
echo '<script>
        let sesskey = "'.sesskey().'";
        let sessionid = '.$sessionid.';
        
        function rotateQR() {
            let formData = new FormData();
            formData.append("action", "generate_qr");
            formData.append("sessionid", sessionid);
            formData.append("sesskey", sesskey);
            
            fetch("ajax.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById("qr-token-text").innerText = data.token;
                    startTimer(120);
                }
            });
        }
        
        let interval;
        function startTimer(duration) {
            let timer = duration;
            clearInterval(interval);
            interval = setInterval(function () {
                document.getElementById("timer").innerText = timer;
                if (--timer < 0) {
                    rotateQR();
                }
            }, 1000);
        }
        
        // Initial fetch
        rotateQR();
      </script>';

echo $OUTPUT->footer();
