<?php
// This file is part of Moodle - http://moodle.org/
// Global helper functions for the Social Network Gateway plugin.

defined('MOODLE_INTERNAL') || die();

if (!function_exists('mlog')) {

    function mlog($message, $file = 'system.log', $level = null, $forceLog = false) {
        // $logDir = "/home/mmmmediwiki/public_html/logs/";
        // $logFile = $logDir . basename($file); // prevent directory traversal

        // // Ensure log directory exists
        // if (!is_dir($logDir)) {
        //     @mkdir($logDir, 0777, true);
        // }

        // // Convert array/object to readable string
        // if (is_array($message) || is_object($message)) {
        //     $message = print_r($message, true);
        // }

        // // Format message
        // $timestamp = date('Y-m-d H:i:s');
        // $levelText = $level ? strtoupper($level) : 'INFO';
        // $formattedMessage = "[$timestamp] [$levelText] $message" . PHP_EOL;

        // Write with file locking
        // @file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
    }
}
