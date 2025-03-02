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

// Log file configuration
$logFile = 'logs/stream_url_check.log';
$maxLogSize = 1 * 1024 * 1024; // 1 MB
$maxLogLines = 5000; // Maximum number of log lines to keep

/**
 * Truncate log file if it exceeds the maximum number of lines
 */
function truncateLog($logFile, $maxLines = 5000) {
    if (!file_exists($logFile)) return false;

    $lines = file($logFile);
    $totalLines = count($lines);

    if ($totalLines <= $maxLines) return false;

    // Calculate how many lines to remove (keep the newest entries)
    $linesToKeep = $maxLines;
    $linesToRemove = $totalLines - $linesToKeep;

    // Keep only the most recent lines
    $lines = array_slice($lines, $linesToRemove);

    // Write the truncated content back to the file
    $result = file_put_contents($logFile, implode('', $lines));

    if ($result !== false) {
        $linesRemoved = $totalLines - $linesToKeep;
        debug_log("Truncated log file, removed {$linesRemoved} oldest entries", $logFile, $GLOBALS['maxLogSize'], 'info');
        return true;
    }

    return false;
}

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
 * Clean up old stream check log backup files
 */
function cleanOldStreamCheckLogs($directory = 'logs', $baseLogFile = 'stream_url_check.log', $retentionDays = 7) {
    // Get all backup files matching the pattern
    $files = glob("{$directory}/{$baseLogFile}.*");
    $cutoffTime = time() - ($retentionDays * 86400);

    $removedCount = 0;
    foreach ($files as $file) {
        // Extract timestamp from filename or use file modification time
        $fileTime = filemtime($file);

        if ($fileTime < $cutoffTime) {
            if (unlink($file)) {
                $removedCount++;
            }
        }
    }

    // Log the cleanup operation
    if ($removedCount > 0) {
        debug_log("Cleaned up {$removedCount} old stream check log backup files", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');
    }

    return $removedCount;
}

/**
 * Debug logging function with log rotation and log levels
 * @param string $message Message to log
 * @param string $logFile Log file path
 * @param int $maxLogSize Maximum log file size before rotation
 * @param string $level Log level (info, warning, error)
 */
function debug_log($message, $logFile, $maxLogSize, $level = 'info') {
    // Only log warnings and errors by default
    // Change this condition to control verbosity
    $shouldLog = ($level === 'error' || $level === 'warning');

    // Always log in verbose mode (you can add a config option for this)
    $verboseLogging = true; // Set to false to reduce logging
    if ($verboseLogging) {
        $shouldLog = true;
    }

    if (!$shouldLog) return;

    // Make sure the directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $levelUpper = strtoupper($level);
    file_put_contents($logFile, "[$timestamp] [$levelUpper] $message\n", FILE_APPEND);

    // Also log to PHP error log for critical issues
    if ($level === 'error') {
        error_log("[Stream URL Check] $message");
    }
}

// Truncate log if it has too many lines
truncateLog($logFile, $maxLogLines);

// Rotate log file if it exceeds maximum size
rotateLogFile($logFile, $maxLogSize);

// Clean up old log files
cleanOldStreamCheckLogs('logs', basename($logFile), 7);

/**
 * Validate Video Stream
 */
function validateVideoStream($url) {
    debug_log("Validating video stream for: $url", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');

    // Validation methods
    $validationMethods = [
        // Method 1: Get stream information
        function() use ($url) {
            $infoCommand = sprintf(
                "ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1",
                escapeshellarg($url)
            );

            exec($infoCommand, $output, $returnVal);

            debug_log("Stream Info Command: $infoCommand", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'debug');
            debug_log("Stream Info Return Value: $returnVal", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'debug');

            if ($returnVal === 0) {
                $streamInfo = json_decode(implode("\n", $output), true);

                // Check for video stream
                if (isset($streamInfo['streams'])) {
                    $videoStreams = array_filter($streamInfo['streams'], function($stream) {
                        return $stream['codec_type'] === 'video';
                    });

                    if (!empty($videoStreams)) {
                        debug_log("Method 1: Video stream detected successfully", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');
                        return [
                            'success' => true,
                            'message' => 'Video stream detected',
                            'stream_details' => $streamInfo
                        ];
                    }
                }
            }

            debug_log("Method 1: No video stream detected", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'warning');
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

            debug_log("Frame Capture Command: $captureCommand", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'debug');
            debug_log("Frame Capture Return Value: $returnVal", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'debug');

            // Check if frame was captured successfully
            if ($returnVal === 0 && file_exists($tempFrame) && filesize($tempFrame) > 0) {
                $result = [
                    'success' => true,
                    'message' => 'Video stream frame captured successfully',
                    'frame_size' => filesize($tempFrame)
                ];

                debug_log("Method 2: Video frame captured successfully", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');

                // Clean up temp frame
                unlink($tempFrame);
                return $result;
            }

            debug_log("Method 2: Failed to capture video frame", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'warning');
            return ['success' => false, 'message' => 'Failed to capture video frame'];
        },

        // Method 3: Duration check
        function() use ($url) {
            $durationCommand = sprintf(
                "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1",
                escapeshellarg($url)
            );

            exec($durationCommand, $output, $returnVal);

            debug_log("Duration Check Command: $durationCommand", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'debug');
            debug_log("Duration Check Return Value: $returnVal", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'debug');

            if ($returnVal === 0 && !empty($output)) {
                $duration = floatval(trim($output[0]));

                if ($duration > 0) {
                    debug_log("Method 3: Video duration detected: $duration seconds", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');
                    return [
                        'success' => true,
                        'message' => "Video stream duration detected: $duration seconds",
                        'duration' => $duration
                    ];
                }
            }

            debug_log("Method 3: Unable to detect stream duration", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'warning');
            return ['success' => false, 'message' => 'Unable to detect stream duration'];
        }
    ];

    // Try each validation method
    foreach ($validationMethods as $method) {
        $result = $method();

        if ($result['success']) {
            debug_log("Video stream validation successful: " . $result['message'], $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');
            return $result;
        }
    }

    debug_log("All video stream validation methods failed", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'error');
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
    debug_log("No stream URL configured", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'error');
    echo json_encode([
        'success' => false,
        'active' => false,
        'message' => 'No recording URL configured in settings'
    ]);
    exit;
}

// Actual validation execution
try {
    debug_log("Starting video stream validation", $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'info');
    $streamCheck = validateVideoStream($streamUrl);

    $result = [
        'success' => true,
        'active' => $streamCheck['success'],
        'message' => $streamCheck['message'],
        'url_checked' => $streamUrl
    ];

    echo json_encode($result);
} catch (Exception $e) {
    debug_log("Exception during validation: " . $e->getMessage(), $GLOBALS['logFile'], $GLOBALS['maxLogSize'], 'error');
    echo json_encode([
        'success' => false,
        'active' => false,
        'message' => 'Unexpected error checking stream: ' . $e->getMessage(),
        'url_checked' => $streamUrl
    ]);
}