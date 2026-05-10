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
 * Settings for the Social Network Gateway message processor.
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Register the Telegram test page so it appears in admin navigation.
$ADMIN->add('messaging', new admin_externalpage(
    'message_socialgw_test',
    get_string('testtelegram', 'message_socialgw'),
    new moodle_url('/message/output/socialgw/test.php'),
    'moodle/site:config',
    true  // hidden from navigation (accessible via button link only)
));

// Register the webhook registration page.
$ADMIN->add('messaging', new admin_externalpage(
    'message_socialgw_registerwebhook',
    get_string('webhook_register', 'message_socialgw'),
    new moodle_url('/message/output/socialgw/registerwebhook.php'),
    'moodle/site:config',
    true  // hidden from navigation (accessible via button link only)
));


if ($ADMIN->fulltree) {

    // Telegram Configurations
    $settings->add(new admin_setting_heading('message_socialgw_telegram_heading',
        get_string('telegramsetting', 'message_socialgw'), ''));

    $settings->add(new admin_setting_configcheckbox('message_socialgw/enabletelegram',
        get_string('enabletelegram', 'message_socialgw'),
        get_string('enabletelegram_desc', 'message_socialgw'), 0));

    $settings->add(new admin_setting_configpasswordunmask('message_socialgw/telegrambottoken',
        get_string('telegrambottoken', 'message_socialgw'),
        get_string('telegrambottoken_desc', 'message_socialgw'), ''));

    // Test Telegram button link.
    $testurl = new moodle_url('/message/output/socialgw/test.php');
    $testlink = html_writer::link($testurl,
        '🚀 ' . get_string('testtelegram_btn', 'message_socialgw'),
        ['class' => 'btn btn-info btn-sm', 'target' => '_self']
    );
    $settings->add(new admin_setting_description(
        'message_socialgw/telegramtest',
        get_string('testtelegram_setting', 'message_socialgw'),
        get_string('testtelegram_setting_desc', 'message_socialgw') . '<br><br>' . $testlink
    ));

    // AI Assistant Configurations
    $settings->add(new admin_setting_heading('message_socialgw_ai_heading',
        get_string('aisetting', 'message_socialgw'), ''));

    $settings->add(new admin_setting_configpasswordunmask('message_socialgw/openaikey',
        get_string('openaikey', 'message_socialgw'),
        get_string('openaikey_desc', 'message_socialgw'), ''));

    // Webhook section.
    $settings->add(new admin_setting_heading('message_socialgw_webhook_heading',
        get_string('webhooksetting', 'message_socialgw'), ''));

    $webhookurl = (string) new moodle_url('/message/output/socialgw/webhook.php');
    $registerurl = new moodle_url('/message/output/socialgw/registerwebhook.php');
    $registerlink = html_writer::link($registerurl,
        '🔗 ' . get_string('webhook_register_btn', 'message_socialgw'),
        ['class' => 'btn btn-success btn-sm mt-1', 'target' => '_self']
    );
    $settings->add(new admin_setting_description(
        'message_socialgw/webhookinfo',
        get_string('webhook_url', 'message_socialgw'),
        get_string('webhook_url_desc', 'message_socialgw') . '<br>' .
        html_writer::tag('code', $webhookurl, ['class' => 'p-1 bg-light border rounded d-inline-block my-1']) .
        '<br><br>' . get_string('webhook_register_desc', 'message_socialgw') . '<br><br>' . $registerlink
    ));

    // WhatsApp Configurations
    $settings->add(new admin_setting_heading('message_socialgw_whatsapp_heading',
        get_string('whatsappsetting', 'message_socialgw'), ''));

    $settings->add(new admin_setting_configcheckbox('message_socialgw/enablewhatsapp',
        get_string('enablewhatsapp', 'message_socialgw'),
        get_string('enablewhatsapp_desc', 'message_socialgw'), 0));

    $settings->add(new admin_setting_configtext('message_socialgw/whatsappapiurl',
        get_string('whatsappapiurl', 'message_socialgw'),
        get_string('whatsappapiurl_desc', 'message_socialgw'), 'https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages', PARAM_URL));

    $settings->add(new admin_setting_configpasswordunmask('message_socialgw/whatsapptoken',
        get_string('whatsapptoken', 'message_socialgw'),
        get_string('whatsapptoken_desc', 'message_socialgw'), ''));

}
