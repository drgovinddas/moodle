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
 * Test page for Social Network Gateway - Telegram
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/message/output/socialgw/classes/api.php');

// Only admins may access this page.
admin_externalpage_setup('message_socialgw_test');

$PAGE->set_url(new moodle_url('/message/output/socialgw/test.php'));
$PAGE->set_title(get_string('testtelegram', 'message_socialgw'));
$PAGE->set_heading(get_string('testtelegram', 'message_socialgw'));

$result   = null;
$response = '';

if (data_submitted() && confirm_sesskey()) {
    $chatid  = required_param('chatid', PARAM_TEXT);
    $token   = get_config('message_socialgw', 'telegrambottoken');
    $message = optional_param('message', get_string('testtelegram_defaultmsg', 'message_socialgw'), PARAM_TEXT);

    if (empty($token)) {
        $result   = false;
        $response = get_string('testtelegram_notoken', 'message_socialgw');
    } else if (empty($chatid)) {
        $result   = false;
        $response = get_string('testtelegram_nochatid', 'message_socialgw');
    } else {
        // Use the API class to send the message.
        $api = new \message_socialgw\api();

        // We call the Telegram API directly to also capture the raw response.
        $url  = "https://api.telegram.org/bot" . $token . "/sendMessage";
        $data = [
            'chat_id'    => $chatid,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ];

        $curl     = new curl();
        $rawresp  = $curl->post($url, $data);
        $info     = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode == 200) {
            $result   = true;
            $response = get_string('testtelegram_success', 'message_socialgw');

            // Save this chat ID to the current admin user's preferences as a convenience.
            set_user_preference('message_processor_socialgw_telegramchatid', $chatid, $USER->id);

        } else {
            $result   = false;
            $decoded  = json_decode($rawresp, true);
            $errdesc  = $decoded['description'] ?? $rawresp;
            $response = get_string('testtelegram_fail', 'message_socialgw',
                        ['code' => $httpcode, 'desc' => $errdesc]);
        }
    }
}

// Build the back-link URL to the settings page.
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'messagesettingsocialgw']);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testtelegram', 'message_socialgw'));

// Show result notification if a test was submitted.
if ($result === true) {
    echo $OUTPUT->notification(
        '<strong>✅ ' . get_string('testtelegram_success', 'message_socialgw') . '</strong><br>'
        . get_string('testtelegram_savedpref', 'message_socialgw'),
        'notifysuccess'
    );
} else if ($result === false) {
    echo $OUTPUT->notification('<strong>❌ ' . htmlspecialchars($response) . '</strong>', 'notifyproblem');
}

// Info box.
echo $OUTPUT->box(
    html_writer::tag('p', get_string('testtelegram_info', 'message_socialgw')) .
    html_writer::tag('p',
        html_writer::tag('strong', get_string('testtelegram_howtofind', 'message_socialgw')) . ' ' .
        html_writer::tag('code', 'https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates')
    ),
    'generalbox mb-3'
);

// Test form.
$formurl = new moodle_url('/message/output/socialgw/test.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('telegramchatid', 'message_socialgw'), 'chatid', true, ['class' => 'form-label']);
// Pre-fill with current user's saved chat ID if available.
$savedchatid = get_user_preferences('message_processor_socialgw_telegramchatid', '', $USER->id);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'id'          => 'chatid',
    'name'        => 'chatid',
    'class'       => 'form-control w-50',
    'value'       => htmlspecialchars($savedchatid),
    'placeholder' => '123456789',
    'required'    => 'required',
]);
echo html_writer::tag('small', get_string('testtelegram_chatid_help', 'message_socialgw'), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('testtelegram_message', 'message_socialgw'), 'message', true, ['class' => 'form-label']);
echo html_writer::tag('textarea',
    htmlspecialchars(get_string('testtelegram_defaultmsg', 'message_socialgw')),
    ['id' => 'message', 'name' => 'message', 'class' => 'form-control w-50', 'rows' => 3]
);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('testtelegram_send', 'message_socialgw'),
    'class' => 'btn btn-primary',
]);

echo ' ';
echo html_writer::link($settingsurl, get_string('back'), ['class' => 'btn btn-secondary']);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
