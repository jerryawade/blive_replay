<?php
/**
 * Simple Stream URL Validator Focusing on Video Stream Detection
 */

// Session and authentication
session_start();
header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'settings.php';
require_once 'logging.php';

// Get settings for timezone
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Log file configuration
$logFile = 'stream_url_check.log';
$maxLogSize = 5 * 1024 * 1024; // 5 MB

/**
 * Rotate log file if it exceeds maximum size
 */
function rotateLogFile($logFile, $maxLogSize) {
    if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
        $backupLogFile = $logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename($logFile, $backupLogFile);
    }
}

/**
 * Debug logging function with log rotation
 */
function debug_log($message, $logFile, $maxLogSize) {
    // Rotate log file before writing
    rotateLogFile($logFile, $maxLogSize);

    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    error_log("[Stream URL Check] $message");
}

/**
 * Validate Video Stream
 */
function validateVideoStream($url) {
    debug_log("Validating video stream for: $url", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);

    // Validation methods
    $validationMethods = [
        // Method 1: Get stream information
        function() use ($url) {
            $infoCommand = sprintf(
                "ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1",
                escapeshellarg($url)
            );

            exec($infoCommand, $output, $returnVal);

            debug_log("Stream Info Command: $infoCommand", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            debug_log("Stream Info Output: " . implode("\n", $output), $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            debug_log("Stream Info Return Value: $returnVal", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);

            if ($returnVal === 0) {
                $streamInfo = json_decode(implode("\n", $output), true);

                // Check for video stream
                if (isset($streamInfo['streams'])) {
                    $videoStreams = array_filter($streamInfo['streams'], function($stream) {
                        return $stream['codec_type'] === 'video';
                    });

                    if (!empty($videoStreams)) {
                        return [
                            'success' => true,
                            'message' => 'Video stream detected',
                            'stream_details' => $streamInfo
                        ];
                    }
                }
            }

            return ['success' => false, 'message' => 'No video stream detected in stream info'];
        },

        // Method 2: Attempt to capture a frame
        function() use ($url) {
            // Temporary directory for frame capture
            $tempDir = sys_get_temp_dir() . '/stream_check_' . md5($url);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempFrame = $tempDir . '/stream_frame.jpg';

            $captureCommand = sprintf(
                "ffmpeg -i %s -vframes 1 -q:v 2 %s 2>&1",
                escapeshellarg($url),
                escapeshellarg($tempFrame)
            );

            exec($captureCommand, $output, $returnVal);

            debug_log("Frame Capture Command: $captureCommand", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            debug_log("Frame Capture Output: " . implode("\n", $output), $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            debug_log("Frame Capture Return Value: $returnVal", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);

            // Check if frame was captured successfully
            if ($returnVal === 0 && file_exists($tempFrame) && filesize($tempFrame) > 0) {
                $result = [
                    'success' => true,
                    'message' => 'Video stream frame captured successfully',
                    'frame_size' => filesize($tempFrame)
                ];

                // Clean up temp frame
                unlink($tempFrame);
                return $result;
            }

            return ['success' => false, 'message' => 'Failed to capture video frame'];
        },

        // Method 3: Duration check
        function() use ($url) {
            $durationCommand = sprintf(
                "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1",
                escapeshellarg($url)
            );

            exec($durationCommand, $output, $returnVal);

            debug_log("Duration Check Command: $durationCommand", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            debug_log("Duration Check Output: " . implode("\n", $output), $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            debug_log("Duration Check Return Value: $returnVal", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);

            if ($returnVal === 0 && !empty($output)) {
                $duration = floatval(trim($output[0]));

                if ($duration > 0) {
                    return [
                        'success' => true,
                        'message' => "Video stream duration detected: $duration seconds",
                        'duration' => $duration
                    ];
                }
            }

            return ['success' => false, 'message' => 'Unable to detect stream duration'];
        }
    ];

    // Try each validation method
    foreach ($validationMethods as $method) {
        $result = $method();

        if ($result['success']) {
            debug_log("Video stream validation successful: " . $result['message'], $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
            return $result;
        }
    }

    debug_log("All video stream validation methods failed", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
    return [
        'success' => false,
        'message' => 'No active video stream detected'
    ];
}

// Get the stream URL from settings
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();
$streamUrl = $settings['srt_url'] ?? '';

// Validate input
if (empty($streamUrl)) {
    debug_log("No stream URL configured", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
    echo json_encode([
        'success' => false,
        'active' => false,
        'message' => 'No recording URL configured in settings'
    ]);
    exit;
}

// Actual validation execution
try {
    debug_log("Starting video stream validation", $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
    $streamCheck = validateVideoStream($streamUrl);

    $result = [
        'success' => true,
        'active' => $streamCheck['success'],
        'message' => $streamCheck['message'],
        'url_checked' => $streamUrl
    ];

    echo json_encode($result);
} catch (Exception $e) {
    debug_log("Exception during validation: " . $e->getMessage(), $GLOBALS['logFile'], $GLOBALS['maxLogSize']);
    echo json_encode([
        'success' => false,
        'active' => false,
        'message' => 'Unexpected error checking stream: ' . $e->getMessage(),
        'url_checked' => $streamUrl
    ]);
}