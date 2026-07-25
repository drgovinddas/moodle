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
 * Message output class for Social Network Gateway (WhatsApp/Telegram).
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/message/output/lib.php');
require_once($CFG->dirroot.'/message/output/socialgw/index.php'); // Include the global mlog function
require_once($CFG->dirroot.'/message/output/socialgw/classes/api.php');

/**
 * The socialgw message processor
 *
 * @package   message_socialgw
 * @copyright 2024 Developer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_output_socialgw extends message_output {
    /**
     * Processes the message (sends via Telegram or WhatsApp).
     * @param object $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
     */
    function send_message($eventdata) {
        mlog("send_message:43", 'whatsapp.log', 'INFO');
        global $CFG, $DB;

        // Ensure recipient is a completely loaded user object (fixes fatal errors for incomplete events)
        $recipient = $eventdata->userto;
        if (is_numeric($recipient)) {
            $recipient = $DB->get_record('user', ['id' => $recipient]);
        } else if (is_object($recipient) && !isset($recipient->auth) && isset($recipient->id)) {
            $recipient = $DB->get_record('user', ['id' => $recipient->id]);
        }
        
        if (empty($recipient)) {
             mlog("Recipient object is empty or invalid. Discarding message.", 'whatsapp.log', 'ERROR');
             return true;
        }

        mlog("send_message triggered for fully loaded user: " . $recipient->id . " - Notification: " . ($eventdata->eventtype ?? 'unknown'), 'whatsapp.log', 'INFO');

        // Skip any messaging suspended and deleted users
        if ((!empty($recipient->auth) && $recipient->auth === 'nologin') || !empty($recipient->suspended) || !empty($recipient->deleted)) {
            mlog("User suspended, deleted, or nologin. Discarding message.", 'whatsapp.log', 'INFO');
            return true;
        }

        // Fetch user preferences for Telegram and WhatsApp
        $telegramchatid = get_user_preferences('message_processor_socialgw_telegramchatid', null, $recipient->id);
        $whatsappnumber = get_user_preferences('message_processor_socialgw_whatsappnumber', null, $recipient->id);

        mlog("Fetched pref tgchatid: '$telegramchatid', wappnumber: '$whatsappnumber'", 'whatsapp.log', 'DEBUG');

        // Fallback to Moodle profile's mobile phone or regular phone if preference is empty
        if (empty($whatsappnumber)) {
            if (!empty($recipient->phone2)) {
                $whatsappnumber = $recipient->phone2;
                mlog("WhatsApp preference empty. Falling back to profile Mobile Phone (phone2): $whatsappnumber", 'whatsapp.log', 'INFO');
            } else if (!empty($recipient->phone1)) {
                $whatsappnumber = $recipient->phone1;
                mlog("WhatsApp preference empty. Falling back to profile Phone (phone1): $whatsappnumber", 'whatsapp.log', 'INFO');
            }
        }

        $telegram_enabled = get_config('message_socialgw', 'enabletelegram');
        $whatsapp_enabled = get_config('message_socialgw', 'enablewhatsapp');

        mlog("Global Settings - Telegram Enabled: " . ($telegram_enabled ? 'YES' : 'NO') . ", WhatsApp Enabled: " . ($whatsapp_enabled ? 'YES' : 'NO'), 'whatsapp.log', 'DEBUG');

        if (!$telegram_enabled && !$whatsapp_enabled) {
            mlog("Both Telegram and WhatsApp are disabled globally.", 'whatsapp.log', 'INFO');
            return true;
        }

        if (empty($telegramchatid) && empty($whatsappnumber)) {
            mlog("User has no Telegram ID or WhatsApp number configured. (Fallback failed/not found too)", 'whatsapp.log', 'INFO');
            return true;
        }

        $result_telegram = true;
        $result_whatsapp = true;

        $api = new \message_socialgw\api();

        $message = strip_tags($eventdata->fullmessagehtml ?? $eventdata->fullmessage);

        // Send via Telegram
        if ($telegram_enabled && !empty($telegramchatid)) {
            mlog("Attempting to send Telegram message to $telegramchatid ...", 'whatsapp.log', 'INFO');
            $token = get_config('message_socialgw', 'telegrambottoken');
            if (!empty($token)) {
                $result_telegram = $api->send_telegram_message($token, $telegramchatid, $message);
            } else {
                mlog("Telegram token missing from config.", 'whatsapp.log', 'ERROR');
            }
        } else {
            mlog("Telegram skipped. Enabled: " . ($telegram_enabled ? 'YES' : 'NO') . ", ChatID: '$telegramchatid'", 'whatsapp.log', 'DEBUG');
        }

        // Send via WhatsApp
        if ($whatsapp_enabled && !empty($whatsappnumber)) {
            $url = get_config('message_socialgw', 'whatsappapiurl');
            $token = get_config('message_socialgw', 'whatsapptoken');
            if (!empty($url) && !empty($token)) {
                mlog("Attempting to send WhatsApp message to $whatsappnumber ...", 'whatsapp.log', 'INFO');
                $result_whatsapp = $api->send_whatsapp_message($url, $token, $whatsappnumber, $message);
            } else {
                mlog("WhatsApp URL or Token not configured globally.", 'whatsapp.log', 'ERROR');
            }
        } else {
            mlog("WhatsApp skipped. Enabled: " . ($whatsapp_enabled ? 'YES' : 'NO') . ", Number: '$whatsappnumber'", 'whatsapp.log', 'DEBUG');
        }

        mlog("Reached end of sender logic. Returning telegram: " . ($result_telegram?'true':'false') . ", whatsapp: " . ($result_whatsapp?'true':'false'), 'whatsapp.log', 'DEBUG');

        return $result_telegram && $result_whatsapp;
    }

    /**
     * Creates necessary fields in the messaging config form.
     *
     * @param array $preferences An array of user preferences
     */
    function config_form($preferences){
        global $USER, $OUTPUT;
        $string = '';

        $telegram_enabled = get_config('message_socialgw', 'enabletelegram');
        $whatsapp_enabled = get_config('message_socialgw', 'enablewhatsapp');

        if ($telegram_enabled) {
            $telegramval = $preferences->telegramchatid ?? '';
            $inputattributes = array('size' => '30', 'name' => 'telegramchatid', 'value' => $telegramval, 'id' => 'telegramchatid');
            $string .= html_writer::label(get_string('telegramchatid', 'message_socialgw'), 'telegramchatid');
            $string .= $OUTPUT->container(html_writer::empty_tag('input', $inputattributes));
            $string .= $OUTPUT->container(get_string('telegramchatid_desc', 'message_socialgw'), 'text-muted mb-3');
        }

        if ($whatsapp_enabled) {
            $whatsappval = $preferences->whatsappnumber ?? '';
            $inputattributes = array('size' => '30', 'name' => 'whatsappnumber', 'value' => $whatsappval, 'id' => 'whatsappnumber');
            $string .= html_writer::label(get_string('whatsappnumber', 'message_socialgw'), 'whatsappnumber');
            $string .= $OUTPUT->container(html_writer::empty_tag('input', $inputattributes));
            $string .= $OUTPUT->container(get_string('whatsappnumber_desc', 'message_socialgw'), 'text-muted mb-3');
        }

        return $string;
    }

    /**
     * Parses the submitted form data and saves it into preferences array.
     *
     * @param stdClass $form preferences form class
     * @param array $preferences preferences array
     */
    function process_form($form, &$preferences){
        if (isset($form->telegramchatid)) {
            $preferences['message_processor_socialgw_telegramchatid'] = clean_param($form->telegramchatid, PARAM_TEXT);
        }
        if (isset($form->whatsappnumber)) {
            $preferences['message_processor_socialgw_whatsappnumber'] = clean_param($form->whatsappnumber, PARAM_TEXT);
        }
    }

    /**
     * Returns the default message output settings for this output
     *
     * @return int The default settings
     */
    public function get_default_messaging_settings() {
        return MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED;
    }

    /**
     * Loads the config data from database to put on the form during initial form display
     *
     * @param array $preferences preferences array
     * @param int $userid the user id
     */
    function load_data(&$preferences, $userid){
        $preferences->telegramchatid = get_user_preferences('message_processor_socialgw_telegramchatid', '', $userid);
        $preferences->whatsappnumber = get_user_preferences('message_processor_socialgw_whatsappnumber', '', $userid);
    }

    /**
     * Returns false, as we don't send system messages to these external platforms unless configured per user.
     *
     * @return bool
     */
    public function can_send_to_any_users() {
        return false;
    }
}
