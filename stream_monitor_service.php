<?php
/**
 * Direct Stream Monitor Service
 * High-priority background service for checking stream URL status
 * With immediate status notification
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the correct working directory
chdir(dirname(__FILE__));

// Load required components
require_once 'settings.php';

// Initialize settings
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Log file paths
$logFile = 'logs/stream_url_check.log';
$statusFile = 'json/stream_status.json';

// Make sure log directory exists
if (!is_dir('logs')) {
    mkdir('logs', 0777, true);
}

// Make sure json directory exists
if (!is_dir('json')) {
    mkdir('json', 0777, true);
}

// Create a lock file to prevent multiple instances
$lockFile = 'json/stream_check.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $now = time();
    
    // If lock is older than 2 minutes, it's stale
    if ($now - $lockTime > 120) {
        unlink($lockFile);
    } else {
        // Another instance is running
        exit;
    }
}

// Create lock file
file_put_contents($lockFile, '1');

// Ensure lock file is removed on script completion
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

// Log function
function logStreamCheck($message, $level = 'info') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Get the SRT URL from settings
$streamUrl = $settings['srt_url'] ?? '';

if (empty($streamUrl)) {
    logStreamCheck("No stream URL configured in settings", 'error');
    $status = [
        'active' => false,
        'message' => 'No stream URL configured in settings',
        'last_check' => time(),
        'next_check' => time() + 300 // Check again in 5 minutes
    ];
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
    exit;
}

// Log the start of the process
logStreamCheck("Starting direct stream URL check: $streamUrl", 'info');

$streamStatus = [
    'active' => false,
    'message' => 'Stream URL is not accessible',
    'last_check' => time(),
    'next_check' => time() + 300, // Check again in 5 minutes
    'check_duration' => 0
];

// Get start time for performance tracking
$startTime = microtime(true);

try {
    // Method 1: Quick FFprobe check
    logStreamCheck("Running FFprobe check", 'debug');
    $infoCommand = sprintf(
        "ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1",
        escapeshellarg($streamUrl)
    );
    exec($infoCommand, $output, $returnVal);

    // Log FFprobe command result
    logStreamCheck("FFprobe command result: Return Val = $returnVal", 'debug');

    if ($returnVal === 0) {
        $streamInfo = json_decode(implode("\n", $output), true);

        // Check for video streams
        $videoStreams = array_filter(
            $streamInfo['streams'] ?? [],
            function ($stream) {
                return ($stream['codec_type'] ?? '') === 'video';
            }
        );

        if (!empty($videoStreams)) {
            // Stream is accessible - update status immediately
            logStreamCheck("Stream URL is accessible! Video streams detected.", 'info');
            $streamStatus['active'] = true;
            $streamStatus['message'] = 'Stream URL is accessible';
            $streamStatus['streams'] = $videoStreams;
            
            // Calculate check duration
            $endTime = microtime(true);
            $streamStatus['check_duration'] = round($endTime - $startTime, 2);
            
            // Write status to file immediately
            file_put_contents($statusFile, json_encode($streamStatus, JSON_PRETTY_PRINT));
            logStreamCheck("Status updated: Stream is ACCESSIBLE (status written)", 'info');
            
            // Exit early - stream is accessible
            exit;
        } else {
            // No video streams found
            logStreamCheck("No video streams found via FFprobe, trying frame capture...", 'warning');
        }
    } else {
        // FFprobe check failed
        logStreamCheck("FFprobe check failed, trying frame capture...", 'warning');
    }

    // Method 2: Frame capture test
    logStreamCheck("Running frame capture test", 'debug');
    $tempFrame = tempnam(sys_get_temp_dir(), 'stream_check_');
    $captureCommand = sprintf(
        "ffmpeg -i %s -vframes 1 -q:v 2 %s 2>&1",
        escapeshellarg($streamUrl),
        escapeshellarg($tempFrame)
    );
    
    exec($captureCommand, $captureOutput, $captureReturnVal);
    
    if ($captureReturnVal === 0 && file_exists($tempFrame) && filesize($tempFrame) > 0) {
        unlink($tempFrame);
        logStreamCheck("Stream URL is accessible via frame capture method!", 'info');
        $streamStatus['active'] = true;
        $streamStatus['message'] = 'Stream URL is accessible (frame capture)';
    } else {
        logStreamCheck("Stream URL is not accessible after multiple validation attempts.", 'error');
        $streamStatus['active'] = false;
        $streamStatus['message'] = 'Stream URL is not accessible (both methods failed)';
    }
    
    // Cleanup temp file if it exists
    if (file_exists($tempFrame)) {
        unlink($tempFrame);
    }
} catch (Exception $e) {
    logStreamCheck("Error during stream check: " . $e->getMessage(), 'error');
    $streamStatus['message'] = 'Error checking stream: ' . $e->getMessage();
}

// Calculate check duration
$endTime = microtime(true);
$streamStatus['check_duration'] = round($endTime - $startTime, 2);

// Create temporary status file then atomically rename it
$tempStatusFile = $statusFile . '.tmp';
file_put_contents($tempStatusFile, json_encode($streamStatus, JSON_PRETTY_PRINT));
rename($tempStatusFile, $statusFile);

// Log the completion of the process
logStreamCheck("Stream URL check completed in {$streamStatus['check_duration']} seconds. Result: " . 
    ($streamStatus['active'] ? 'ACCESSIBLE' : 'NOT ACCESSIBLE'), 'info');

// Exit the script
exit;
