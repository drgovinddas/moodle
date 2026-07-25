<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Register webhook page for Social Network Gateway plugin.
 * Registers the webhook.php URL with the Telegram Bot API with one click.
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('message_socialgw_registerwebhook');

$PAGE->set_url(new moodle_url('/message/output/socialgw/registerwebhook.php'));
$PAGE->set_title(get_string('webhook_register', 'message_socialgw'));
$PAGE->set_heading(get_string('webhook_register', 'message_socialgw'));

$result     = null;
$resultmsg  = '';

if (data_submitted() && confirm_sesskey()) {
    $token = get_config('message_socialgw', 'telegrambottoken');

    if (empty($token)) {
        $result    = false;
        $resultmsg = get_string('testtelegram_notoken', 'message_socialgw');
    } else {
        $webhookurl = (string) new moodle_url('/message/output/socialgw/webhook.php');

        // Call Telegram setWebhook API.
        $apiurl = "https://api.telegram.org/bot{$token}/setWebhook";
        $data   = ['url' => $webhookurl];

        $curl     = new curl();
        $rawresp  = $curl->post($apiurl, $data);
        $info     = $curl->get_info();
        $decoded  = json_decode($rawresp, true);

        if (!empty($decoded['ok']) && $decoded['ok'] === true) {
            $result    = true;
            $resultmsg = $decoded['description'] ?? get_string('webhook_registered_ok', 'message_socialgw');
        } else {
            $result    = false;
            $errdesc   = $decoded['description'] ?? $rawresp;
            $resultmsg = get_string('webhook_registered_fail', 'message_socialgw', $errdesc);
        }
    }
}

// Check current webhook info.
$currentwebhook = '';
$token = get_config('message_socialgw', 'telegrambottoken');
if (!empty($token)) {
    $infourl  = "https://api.telegram.org/bot{$token}/getWebhookInfo";
    $curl     = new curl();
    $rawresp  = $curl->get($infourl);
    $decoded  = json_decode($rawresp, true);
    $currentwebhook = $decoded['result']['url'] ?? '';
}

$webhookurl  = (string) new moodle_url('/message/output/socialgw/webhook.php');
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'messagesettingsocialgw']);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('webhook_register', 'message_socialgw'));

// Show result notification.
if ($result === true) {
    echo $OUTPUT->notification(
        '✅ <strong>' . get_string('webhook_registered_ok', 'message_socialgw') . '</strong><br>' .
        htmlspecialchars($resultmsg),
        'notifysuccess'
    );
} else if ($result === false) {
    echo $OUTPUT->notification('❌ <strong>' . htmlspecialchars($resultmsg) . '</strong>', 'notifyproblem');
}

// Current webhook status box.
echo $OUTPUT->box_start('generalbox mb-3');
echo html_writer::tag('h5', get_string('webhook_current', 'message_socialgw'));
if (!empty($currentwebhook)) {
    if ($currentwebhook === $webhookurl) {
        echo html_writer::tag('p',
            '✅ ' . get_string('webhook_active', 'message_socialgw') . ' ' .
            html_writer::tag('code', $currentwebhook)
        );
    } else {
        echo html_writer::tag('p',
            '⚠️ ' . get_string('webhook_different', 'message_socialgw') . '<br>' .
            get_string('webhook_current_label', 'message_socialgw') . ' ' .
            html_writer::tag('code', $currentwebhook) . '<br>' .
            get_string('webhook_expected_label', 'message_socialgw') . ' ' .
            html_writer::tag('code', $webhookurl)
        );
    }
} else {
    echo html_writer::tag('p', '⚠️ ' . get_string('webhook_notset', 'message_socialgw'));
}
echo $OUTPUT->box_end();

// Info box.
echo $OUTPUT->box_start('generalbox mb-3');
echo html_writer::tag('p', get_string('webhook_register_info', 'message_socialgw'));
echo html_writer::tag('p',
    get_string('webhook_url_label', 'message_socialgw') . '<br>' .
    html_writer::tag('code', $webhookurl, ['class' => 'p-1 bg-light border rounded d-inline-block'])
);
echo $OUTPUT->box_end();

// Registration form.
$formurl = new moodle_url('/message/output/socialgw/registerwebhook.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => '🔗 ' . get_string('webhook_register_btn', 'message_socialgw'),
    'class' => 'btn btn-success mr-2',
]);
echo ' ';
echo html_writer::link($settingsurl, get_string('back'), ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
