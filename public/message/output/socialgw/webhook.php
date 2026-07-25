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
 * Telegram Webhook for Social Network Gateway plugin.
 *
 * This file receives incoming messages from Telegram and handles bot commands.
 * Supported commands:
 *   /myid  - Replies with the user's Telegram Chat ID
 *   /start - Welcome message with instructions
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Bootstrap Moodle (needed for get_config, curl, etc.)
define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/enrollib.php');
function mlog($message, $file = 'system.log', $level = null, $forceLog = false) {
        $logDir = "/home/mmmmediwiki/public_html/logs/";
        $logFile = $logDir . basename($file); // prevent directory traversal

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        // Convert array/object to readable string
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        // Format message
        $timestamp = date('Y-m-d H:i:s');
        $levelText = $level ? strtoupper($level) : 'INFO';
        $formattedMessage = "[$timestamp] [$levelText] $message" . PHP_EOL;

        // Write with file locking
        @file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
    }

// Only accept POST requests from Telegram.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mlog("Method Not Allowed", 'telegram.log', 'ERROR');  
    http_response_code(405);
    die('Method Not Allowed');
}

// Read the incoming JSON payload from Telegram.
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (empty($update)) {
    mlog("Invalid payload", 'telegram.log', 'ERROR');  
    http_response_code(400);
    die('Invalid payload');
}

// Get the bot token from Moodle config.
$token = get_config('message_socialgw', 'telegrambottoken');
if (empty($token)) {
    mlog("Bot token not configured", 'telegram.log', 'ERROR');  
    http_response_code(500);
    die('Bot token not configured');
}

function send_reply($token, $chat_id, $text) {
    if (empty($text)) {
        mlog("ERROR: Empty message text!", 'telegram.log', 'ERROR');
        return;
    }

    mlog("Sending reply to {$chat_id}", 'telegram.log', 'INFO');

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // ✅ FIXED
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_error($ch)) {
        mlog("cURL ERROR: " . curl_error($ch), 'telegram.log', 'ERROR');
    } else {
        mlog("Telegram API Response: " . $response, 'telegram.log', 'INFO');
    }

    curl_close($ch);
}

// Extract the message from the Telegram update.
$message = $update['message'] ?? $update['edited_message'] ?? null;

if (empty($message)) {
    mlog("No message found in update", 'telegram.log', 'ERROR');  
    // Not a message update (could be inline query etc.) — ignore silently.
    http_response_code(200);
    die('OK');
}

$chat_id   = $message['chat']['id']         ?? null;
$from      = $message['from']               ?? [];
$text      = trim($message['text']          ?? '');
$firstname = $from['first_name']            ?? 'Student';

if (empty($chat_id)) {
    http_response_code(200);
    die('OK');
}

// Identify linked Moodle user
global $DB;
$moodle_user = null;
if (!empty($chat_id)) {
    $pref = $DB->get_record('user_preferences', ['name' => 'message_processor_socialgw_telegramchatid', 'value' => $chat_id]);
    if ($pref) {
        $moodle_user = $DB->get_record('user', ['id' => $pref->userid, 'deleted' => 0]);
    }
}

// Get the Moodle site name for friendlier messages.
$sitename = get_site()->fullname ?? 'Moodle';
$commands  = "📚 <b>Available Commands</b>\n\n";
$commands .= "/start — Welcome message &amp; setup guide\n";
$commands .= "/linkaccount — Link this chat to Moodle\n";
$commands .= "\n📢 <b>Admin Actions</b>\n";
$commands .= "/broadcast — Send a message to all users\n";
$commands .= "\n🎓 <b>Course Actions</b>\n";
$commands .= "/mycourses — List all your enrolled courses\n";
$commands .= "/assignments — View your upcoming assignments\n";
$commands .= "\n🤖 <b>AI Assistant</b>\n";
$commands .= "/ask — Ask the AI a question\n";
$commands .= "\n⚙️ <b>Other</b>\n";
$commands .= "/myid  — Get your Telegram Chat ID\n";
$commands .= "/myip  — Get your IP address\n";
$commands .= "/help  — Available Commands";

// ── Command handler ──────────────────────────────────────────────────────────

function send_typing_action($token, $chat_id) {
    $url = "https://api.telegram.org/bot{$token}/sendChatAction";
    $data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
// /start command — welcome message with instructions.
if ($text === '/start' || str_starts_with($text, '/start ')) {
    mlog("Command /start received from {$firstname} (Chat ID: {$chat_id})", 'telegram.log', 'INFO');    
    $reply  = "👋 <b>Welcome to {$sitename} Notifications, {$firstname}!</b>\n\n";
    $reply .= "This bot will send you Moodle notifications directly to Telegram.\n\n";
    $reply .= "To get started, you need to link your account.\n";
    $reply .= "Send /linkaccount [EMAIL_ADDRESS] to link your account.\n\n";
    send_reply($token, $chat_id, $reply);

// /linkaccount command — Generates a pairing token.
} else if (str_starts_with($text, '/linkaccount')) {
    mlog("Command /linkaccount received from {$firstname} (Chat ID: {$chat_id})", 'telegram.log', 'INFO');
    
    $parts = explode(' ', trim($text), 2);
    if (count($parts) < 2 || empty(trim($parts[1]))) {
        $reply  = "🔗 <b>Link Your Account</b>\n\n";
        $reply .= "Please provide your Moodle email address to link your account.\n\n";
        $reply .= "<b>Example:</b>\n";
        $reply .= "<code>/linkaccount your.email@example.com</code>";
        send_reply($token, $chat_id, $reply);
    } else {
        $email = trim($parts[1]);
        global $DB;
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            send_reply($token, $chat_id, "❌ <b>Invalid Email</b>\n\nPlease provide a valid email address.");
        } else {
            // Find user in Moodle
            $moodle_user_find = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            
            if ($moodle_user_find) {
                // Link account
                set_user_preference('message_processor_socialgw_telegramchatid', $chat_id, $moodle_user_find->id);
                
                $reply  = "✅ <b>Success!</b>\n\n";
                $reply .= "Your Telegram account has been successfully linked to the Moodle user: <b>" . fullname($moodle_user_find) . "</b>.\n\n";
                $reply .= "You will now receive Moodle notifications here.";
                send_reply($token, $chat_id, $reply);
            } else {
                $reply  = "❌ <b>Account Not Found</b>\n\n";
                $reply .= "We could not find a Moodle account with the email <b>{$email}</b>.\n";
                $reply .= "Please check the email address and try again.";
                send_reply($token, $chat_id, $reply);
            }
        }
    }

// /broadcast command — admin only
} else if (str_starts_with($text, '/broadcast')) {
    mlog("Command /broadcast received from {$firstname} (Chat ID: {$chat_id})", 'telegram.log', 'INFO');
    
    if (!$moodle_user || !is_siteadmin($moodle_user)) {
        send_reply($token, $chat_id, "❌ <b>Permission Denied</b>\n\nYou must be a linked Moodle Administrator to use this command.");
    } else {
        $parts = explode(' ', trim($text), 2);
        if (count($parts) < 2 || empty(trim($parts[1]))) {
            send_reply($token, $chat_id, "📢 <b>Broadcast Message</b>\n\nPlease provide a message to send. Example:\n<code>/broadcast Hello everyone!</code>");
        } else {
            $message_broadcast = trim($parts[1]);
            $prefs = $DB->get_records('user_preferences', ['name' => 'message_processor_socialgw_telegramchatid']);
            $count = 0;
            
            $broadcast_msg = "📢 <b>Broadcast from Admin " . fullname($moodle_user) . "</b>\n\n" . $message_broadcast;
            
            foreach ($prefs as $pref) {
                if (!empty($pref->value)) {
                    send_reply($token, $pref->value, $broadcast_msg);
                    $count++;
                }
            }
            send_reply($token, $chat_id, "✅ <b>Broadcast Complete</b>\n\nMessage delivered to {$count} Telegram users.");
        }
    }

// /mycourses command
} else if ($text === '/mycourses') {
    if (!$moodle_user) {
        send_reply($token, $chat_id, "❌ <b>Account Not Linked</b>\n\nPlease link your Moodle account first by sending: <code>/linkaccount your.email@example.com</code>");
    } else {
        $courses = enrol_get_users_courses($moodle_user->id, true);
        if (empty($courses)) {
            send_reply($token, $chat_id, "🎓 <b>My Courses</b>\n\nYou are not currently enrolled in any courses.");
        } else {
            $reply = "🎓 <b>Your Enrolled Courses</b>\n\n";
            foreach ($courses as $course) {
                $reply .= "• " . format_string($course->fullname) . "\n";
            }
            send_reply($token, $chat_id, $reply);
        }
    }

// /assignments command
} else if ($text === '/assignments') {
    if (!$moodle_user) {
        send_reply($token, $chat_id, "❌ <b>Account Not Linked</b>\n\nPlease link your Moodle account first by sending: <code>/linkaccount your.email@example.com</code>");
    } else {
        $courses = enrol_get_users_courses($moodle_user->id, true);
        if (empty($courses)) {
            send_reply($token, $chat_id, "📝 <b>Upcoming Assignments</b>\n\nYou are not enrolled in any courses.");
        } else {
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($courses));
            $sql = "SELECT a.id, a.name, a.duedate, c.fullname AS coursename 
                    FROM {assign} a 
                    JOIN {course} c ON a.course = c.id 
                    WHERE a.course $insql AND a.duedate > ? 
                    ORDER BY a.duedate ASC LIMIT 10";
            $inparams[] = time();
            $assignments = $DB->get_records_sql($sql, $inparams);
            
            if (empty($assignments)) {
                send_reply($token, $chat_id, "📝 <b>Upcoming Assignments</b>\n\nYou have no upcoming assignments due! 🎉");
            } else {
                $reply = "📝 <b>Upcoming Assignments</b>\n\n";
                foreach ($assignments as $assign) {
                    $date = userdate($assign->duedate, get_string('strftimedatetime', 'langconfig'));
                    $reply .= "📌 <b>" . format_string($assign->name) . "</b>\n";
                    $reply .= "📘 " . format_string($assign->coursename) . "\n";
                    $reply .= "⏰ Due: " . $date . "\n\n";
                }
                send_reply($token, $chat_id, $reply);
            }
        }
    }

// /ask command
} else if (str_starts_with($text, '/openaiask')) {

    mlog("Command /ask received from {$firstname} (Chat ID: {$chat_id})", 'telegram.log', 'INFO');

    // ✅ Extract prompt (FIXED)
    $prompt = trim(substr($text, 4));

    if (empty($prompt)) {
        send_reply($token, $chat_id,
            "🤖 <b>AI Assistant</b>\n\nPlease ask a question.\n\n<code>/ask What is polymorphism?</code>"
        );
        return;
    }

    $openaikey = get_config('message_socialgw', 'openaikey');

    if (empty($openaikey)) {
        send_reply($token, $chat_id,
            "❌ <b>AI Not Configured</b>\n\nAPI key missing."
        );
        return;
    }

    $url = 'https://api.openai.com/v1/responses';

    $data = [
        'model' => 'gpt-4.1-mini',
        'input' => $prompt,
        'max_output_tokens' => 500
    ];

    $headers = [
        'Authorization: Bearer ' . $openaikey,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_error($ch)) {
        mlog("cURL ERROR: " . curl_error($ch), 'telegram.log', 'ERROR');
    }

    curl_close($ch);

    mlog("HTTP Code: " . $httpcode, 'telegram.log', 'INFO');
    mlog("Response: " . $response, 'telegram.log', 'INFO');

    if ($httpcode == 200 && !empty($response)) {
        $result = json_decode($response, true);

        $answer = $result['output_text'] ?? null;

        if ($answer) {
            send_reply($token, $chat_id,
                "🤖 <b>AI Assistant:</b>\n\n" . htmlspecialchars($answer)
            );
        } else {
            send_reply($token, $chat_id,
                "🤖 <b>AI Assistant</b>\n\nUnexpected response format."
            );
        }
    } else {
        send_reply($token, $chat_id,
            "❌ <b>AI Error</b>\n\nFailed to contact AI service."
        );
    }
// /myid command — reply with the user's Chat ID.
} else if ($text === '/myid') {
    $reply  = "🆔 <b>Your Telegram Chat ID is:</b>\n\n";
    $reply .= "<code>{$chat_id}</code>\n\n";
    // $reply .= "👆 Tap the number above to copy it.\n\n";
    // $reply .= "<b>How to activate Moodle notifications:</b>\n";
    // $reply .= "1. Log in to {$sitename}\n";
    // $reply .= "2. Go to <i>Profile → Preferences → Notification Preferences</i>\n";
    // $reply .= "3. Find <b>Social Network Gateway</b>\n";
    // $reply .= "4. Paste <code>{$chat_id}</code> in the Telegram Chat ID field\n";
    // $reply .= "5. Save — done! ✅";
    send_reply($token, $chat_id, $reply);


} else if (str_starts_with($text, '/ask')) {

    mlog("Command /ask received from {$firstname} (Chat ID: {$chat_id})", 'telegram.log', 'INFO');

    $prompt = trim(substr($text, 4));

    if (empty($prompt)) {
        send_reply($token, $chat_id,
            "🤖 <b>AI Assistant</b>\n\nPlease ask a question.\n\n<code>/ask What is polymorphism?</code>"
        );
        return;
    }

    // Send "typing" action before processing
    send_typing_action($token, $chat_id);

    $apikey = get_config('message_socialgw', 'openaikey');

    if (empty($apikey)) {
        send_reply($token, $chat_id,
            "❌ <b>AI Not Configured</b>\n\nGemini API key missing."
        );
        return;
    }

    // Gemini endpoint
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apikey;
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $headers = [
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_error($ch)) {
        mlog("cURL ERROR: " . curl_error($ch), 'telegram.log', 'ERROR');
    }

    curl_close($ch);

    mlog("HTTP Code: " . $httpcode, 'telegram.log', 'INFO');
    mlog("Response: " . $response, 'telegram.log', 'INFO');

    if ($httpcode == 200 && !empty($response)) {
        $result = json_decode($response, true);

        $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($answer) {
            send_reply($token, $chat_id,
                "🤖 <b>AI Assistant:</b>\n\n" . htmlspecialchars($answer)
            );
        } else {
            send_reply($token, $chat_id,
                "🤖 <b>AI Assistant</b>\n\nUnexpected response format."
            );
        }
    } else {
        send_reply($token, $chat_id,
            "❌ <b>AI Error</b>\n\nFailed to contact Gemini service."
        );
    }
} else if ($text === '/help') {
    $reply .= $commands;
    send_reply($token, $chat_id, $reply);

// /myip command.
} else if ($text === '/myip') {
    $reply  = "Your IP address is: " . $_SERVER['REMOTE_ADDR'];
    send_reply($token, $chat_id, $reply);

// Unknown command or plain text.
} else if (str_starts_with($text, '/')) {
    $reply = "❓ Unknown command. Send /help to see available commands.";
    send_reply($token, $chat_id, $reply);

}else{
    $reply = "❓ Unknown command. Send /help to see available commands.\n\n";
    $reply .= $commands;
    send_reply($token, $chat_id, $reply);
}

// Always respond 200 OK to Telegram (required — otherwise Telegram retries).
http_response_code(200);

echo 'OK';
