<?php
namespace mod_smartattend\output;

defined('MOODLE_INTERNAL') || die();

class mobile {
    public static function mobile_course_view($args) {
        global $CFG;

        $cmid = $args['cmid'];
        $url = $CFG->wwwroot . '/mod/smartattend/view.php?id=' . $cmid;

        $html = '<div class="ion-padding" style="text-align: center; padding: 20px;">
            <div style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); width: 80px; height: 80px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: 800; margin-bottom: 20px;">QR</div>
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 10px;">Face ID Kiosk</h3>
            <p style="color: #64748b; font-size: 15px; margin-bottom: 30px;">This activity uses advanced camera and AI features. Please open it in your browser to continue.</p>
            <a core-link auto-login="yes" capture="false" target="_blank" href="' . $url . '" class="button button-block" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px; border-radius: 12px; font-weight: 800; text-decoration: none; display: block; box-shadow: 0 8px 15px rgba(16, 185, 129, 0.2);">Launch in Browser</a>
        </div>';

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $html
                )
            ),
            'javascript' => '',
            'otherdata' => '',
            'files' => array()
        );
    }
}
