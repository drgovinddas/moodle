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
 * Social Network Gateway (WhatsApp/Telegram) language file.
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Social Notification Gateway (WhatsApp & Telegram)';
$string['privacy:metadata'] = 'The Social Notification Gateway plugin sends notifications to external APIs (WhatsApp and Telegram). User identifiers (like phone numbers and chat IDs) are sent to these services.';

// Admin settings for Telegram
$string['telegramsetting'] = 'Telegram Settings';
$string['enabletelegram'] = 'Enable Telegram';
$string['enabletelegram_desc'] = 'Allow users to receive notifications via Telegram.';
$string['telegrambottoken'] = 'Telegram Bot Token';
$string['telegrambottoken_desc'] = 'The API token for your Telegram Bot (provided by BotFather).';

// Admin settings for WhatsApp
$string['whatsappsetting'] = 'WhatsApp Settings';
$string['enablewhatsapp'] = 'Enable WhatsApp';
$string['enablewhatsapp_desc'] = 'Allow users to receive notifications via WhatsApp.';
$string['whatsappapiurl'] = 'WhatsApp API URL';
$string['whatsappapiurl_desc'] = 'The base endpoint for your WhatsApp Graph API.';
$string['whatsapptoken'] = 'WhatsApp Bearer Token';
$string['whatsapptoken_desc'] = 'The authentication token for your WhatsApp business app.';

// AI Assistant settings
$string['aisetting'] = 'AI Assistant Settings';
$string['openaikey'] = 'Gemini API Key';
$string['openaikey_desc'] = 'Required for the /ask bot command. Enter your Gemini API Key to allow the bot to answer student questions.';

// User preferences
$string['telegramchatid'] = 'Telegram Chat ID';
$string['telegramchatid_desc'] = 'Enter your Telegram Chat ID to receive Moodle notifications via Telegram.';
$string['whatsappnumber'] = 'WhatsApp Number';
$string['whatsappnumber_desc'] = 'Enter your WhatsApp mobile number (with country code, e.g., +1234567890) to receive notifications.';
$string['invalidtelegramchatid'] = 'Invalid Telegram Chat ID format. Usually numeric.';
$string['invalidwhatsappnumber'] = 'Invalid WhatsApp number format. Include your country code and only use digits.';

// Telegram test page strings.
$string['testtelegram']               = 'Test Telegram Notification';
$string['testtelegram_btn']           = 'Send Test Message';
$string['testtelegram_setting']       = 'Test Telegram';
$string['testtelegram_setting_desc']  = 'Send a test message to verify your bot token and chat ID are working correctly.';
$string['testtelegram_info']          = 'Enter your Telegram Chat ID below and click "Send Test Message". If the bot token stored in settings is correct, you will receive a test message in your Telegram app.';
$string['testtelegram_howtofind']     = 'How to find your Chat ID:';
$string['testtelegram_chatid_help']   = 'Your Telegram Chat ID (a numeric value). Start a chat with your bot, then visit the getUpdates URL to find it.';
$string['testtelegram_message']       = 'Test message text';
$string['testtelegram_defaultmsg']    = '✅ This is a test notification from your Moodle site. Telegram integration is working correctly!';
$string['testtelegram_send']          = 'Send Test Message';
$string['testtelegram_success']       = 'Test message sent successfully! Check your Telegram app.';
$string['testtelegram_savedpref']     = 'Your Telegram Chat ID has also been saved to your Moodle profile preferences.';
$string['testtelegram_fail']          = 'Telegram API error (HTTP {$a->code}): {$a->desc}';
$string['testtelegram_notoken']       = 'No Telegram Bot Token is configured. Please save the token in admin settings first.';
$string['testtelegram_nochatid']      = 'Please enter a Telegram Chat ID to send a test message.';

// Webhook strings.
$string['webhooksetting']             = 'Telegram Webhook (/myid command)';
$string['webhook_url']                = 'Webhook URL';
$string['webhook_url_desc']           = 'This is your webhook URL. Register it with Telegram so students can use /myid:';
$string['webhook_url_label']          = 'Webhook URL:';
$string['webhook_register']           = 'Register Telegram Webhook';
$string['webhook_register_btn']       = 'Register Webhook with Telegram';
$string['webhook_register_desc']      = 'Click the button below to automatically register the webhook with Telegram. This enables the /myid and /start commands.';
$string['webhook_register_info']      = 'Clicking "Register Webhook" will tell Telegram to send all bot messages to your Moodle site. Students can then send /myid to your bot and it will instantly reply with their Chat ID.';
$string['webhook_registered_ok']      = 'Webhook registered successfully!';
$string['webhook_registered_fail']    = 'Failed to register webhook: {$a}';
$string['webhook_current']            = 'Current Webhook Status';
$string['webhook_active']             = 'Active webhook:';
$string['webhook_different']          = 'A different webhook URL is registered.';
$string['webhook_current_label']      = 'Registered:';
$string['webhook_expected_label']     = 'Expected:';
$string['webhook_notset']             = 'No webhook is currently registered. Click Register to activate /myid.';


