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

namespace message_socialgw;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/../index.php'); // Include the global mlog function

/**
 * API handler class for Social Network Gateway.
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Send message via Telegram API
     *
     * @param string $token Telegram Bot Token
     * @param string $chatid Telegram Chat ID
     * @param string $message Text to send
     * @return bool True on success
     */
    public function send_telegram_message($token, $chatid, $message) {
        $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
        
        $data = [
            'chat_id' => $chatid,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $curl = new \curl();
        $response = $curl->post($url, $data);
        
        $info = $curl->get_info();
        if ($info['http_code'] == 200) {
            return true;
        }

        debugging("Telegram API Error: HTTP Code " . $info['http_code'] . " - Response: " . $response, DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Send message via WhatsApp Graph API
     *
     * @param string $url WhatsApp Graph API URL
     * @param string $token WhatsApp Bearer Token
     * @param string $number Destination phone number
     * @param string $message Text to send
     * @return bool True on success
     */
    public function send_whatsapp_message($url, $token, $number, $message) {
        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $number,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        $curl = new \curl();
        $response = $curl->post($url, json_encode($data), ['CURLOPT_HTTPHEADER' => $headers]);

        $info = $curl->get_info();
        if ($info['http_code'] == 200 || $info['http_code'] == 201) {
            mlog("WhatsApp message sent successfully to {$number}", 'whatsapp.log', 'INFO');
            return true;
        }

        debugging("WhatsApp API Error: HTTP Code " . $info['http_code'] . " - Response: " . $response, DEBUG_DEVELOPER);
        mlog("WhatsApp failed to send to {$number}. HTTP Code: " . $info['http_code'] . " Response: " . $response, 'whatsapp.log', 'ERROR');
        return false;
    }
}
