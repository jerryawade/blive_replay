<?php
/**
 * Stream Monitor Service
 * Background service for checking stream URL status
 * 
 * Enhanced version with improved reliability and retry mechanism
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

// File paths
$logFile = 'logs/stream_url_check.log';
$statusFile = 'json/stream_status.json';
$lockFile = 'json/stream_check.lock';

// Make sure directories exist
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}
if (!is_dir(dirname($statusFile))) {
    mkdir(dirname($statusFile), 0777, true);
}

// Log function
function logMessage($message, $level = 'info') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Clean up the stream URL check log file
 * Keeps only the latest 500 lines and removes entries older than specified days
 *
 * @param string $logFile Path to the log file
 * @param int $days Number of days to keep logs for
 * @param int $maxLines Maximum number of lines to keep
 * @return bool Success status
 */
function cleanStreamLog($logFile, $days = 14, $maxLines = 500) {
    if (!file_exists($logFile)) {
        return true;
    }

    // Read the entire log file content
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        logMessage("Unable to read stream log file for cleanup", 'error');
        return false;
    }

    // Calculate timestamp for cutoff date
    $cutoffDate = time() - ($days * 86400); // days * seconds per day

    // Filter lines, keeping only those newer than the cutoff date or without timestamps
    $keptLines = array_filter($lines, function ($line) use ($cutoffDate) {
        // Keep empty lines
        if (empty(trim($line))) {
            return true;
        }

        // Extract timestamp from line (expected format: [YYYY-MM-DD HH:MM:SS])
        if (preg_match('/^\[([\d-]+ [\d:]+)\]/', $line, $matches)) {
            $lineDate = strtotime($matches[1]);
            return $lineDate >= $cutoffDate;
        }

        // Keep lines without timestamps (e.g., continuation lines)
        return true;
    });

    // If we still have too many lines after filtering by date, just keep the newest ones
    if (count($keptLines) > $maxLines) {
        $keptLines = array_slice($keptLines, -$maxLines);
    }

    // Join filtered lines back into a single string
    $newContent = implode("\n", $keptLines);

    // Write the cleaned content back to the log file
    if (file_put_contents($logFile, $newContent) === false) {
        logMessage("Failed to write updated stream log file", 'error');
        return false;
    }

    $originalSize = count($lines);
    $newSize = count($keptLines);
    logMessage(
        "Log cleanup complete. Reduced from {$originalSize} to {$newSize} lines " .
        "(removed " . ($originalSize - $newSize) . " lines)",
        'info'
    );

    return true;
}

// CHECK IF WE NEED TO RUN BASED ON INTERVAL SETTING
if (file_exists($statusFile) && !file_exists($lockFile)) {
    // Get current status
    $status = json_decode(file_get_contents($statusFile), true) ?: [];

    // Get check interval from settings
    $checkIntervalMinutes = isset($settings['stream_check_interval']) ? (int)$settings['stream_check_interval'] : 5;
    $checkIntervalSeconds = $checkIntervalMinutes * 60;

    // Calculate time since last check
    $timeSinceLastCheck = isset($status['last_check']) ? time() - $status['last_check'] : PHP_INT_MAX;

    // Log interval information
    logMessage("Stream monitor service: check interval={$checkIntervalMinutes}m, time since last check={$timeSinceLastCheck}s");

    // Check if interval has elapsed since last check
    if (isset($status['last_check']) && $timeSinceLastCheck < $checkIntervalSeconds) {
        logMessage("Interval not reached yet, exiting without check. Next check in " .
            ($checkIntervalSeconds - $timeSinceLastCheck) . " seconds.");
        exit; // Exit without performing a check
    }
}

// Run log cleanup once per hour based on minutes value
$currentMinute = (int)date('i');
if ($currentMinute < 5) {
    logMessage("Performing log file maintenance");
    cleanStreamLog($logFile);
}

// Create lock file to indicate check is in progress
file_put_contents($lockFile, date('Y-m-d H:i:s'));

// Ensure lock file is removed when script finishes
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
        logMessage("Removed lock file at shutdown");
    }
});

// Get the SRT URL from settings
$streamUrl = $settings['srt_url'] ?? '';

if (empty($streamUrl)) {
    logMessage("No recording URL configured in settings", 'error');
    $status = [
        'active' => false,
        'message' => 'No recording URL configured in settings',
        'last_check' => time()
    ];
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
    exit;
}

// Log the start of the process
logMessage("Starting recording URL check: $streamUrl");

// Initialize default status
$streamStatus = [
    'active' => false,
    'message' => 'Recording URL is not accessible',
    'last_check' => time(),
    'check_duration' => 0,
    'retries' => 0,
    'last_success' => null
];

// Check if we have an existing status file to include last success info
if (file_exists($statusFile)) {
    $existingStatus = json_decode(file_get_contents($statusFile), true) ?: [];
    if (isset($existingStatus['last_success'])) {
        $streamStatus['last_success'] = $existingStatus['last_success'];
    }
    
    // If it was previously active, maintain that info in log
    if (isset($existingStatus['active']) && $existingStatus['active']) {
        logMessage("Previous check indicated stream was ACCESSIBLE");
    }
}

// Get start time for performance tracking
$startTime = microtime(true);

// Number of retries for checks
$maxRetries = 2; // Retry up to 2 times
$currentTry = 0;
$success = false;

// Run the checks with retries
while ($currentTry <= $maxRetries && !$success) {
    if ($currentTry > 0) {
        logMessage("Retry attempt {$currentTry} of {$maxRetries}");
        // Add a short delay between retries
        sleep(2);
    }
    
    $currentTry++;
    
    try {
        // Method 1: FFprobe quick check with timeout
        logMessage("Running FFprobe check (attempt {$currentTry})");

        $timeout = 8; // seconds - slightly reduced from 10

        // Add a timeout to FFprobe command
        $infoCommand = "timeout $timeout ffprobe -v quiet -print_format json -show_format -show_streams " .
            escapeshellarg($streamUrl) . " 2>&1";

        logMessage("Executing command: $infoCommand", 'debug');

        exec($infoCommand, $output, $returnVal);

        logMessage("FFprobe return code: $returnVal", 'debug');

        if ($returnVal === 0) {
            $outputStr = implode("\n", $output);

            $streamInfo = json_decode($outputStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                logMessage("Error parsing FFprobe JSON: " . json_last_error_msg(), 'error');
            }

            // Check for video streams
            $videoStreams = 0;

            if (isset($streamInfo['streams']) && is_array($streamInfo['streams'])) {
                foreach ($streamInfo['streams'] as $stream) {
                    if (isset($stream['codec_type']) && $stream['codec_type'] === 'video') {
                        $videoStreams++;
                    }
                }
            }

            if ($videoStreams > 0) {
                logMessage("FFprobe found $videoStreams video streams");
                $streamStatus['active'] = true;
                $streamStatus['message'] = "Recording URL is accessible ($videoStreams video streams)";
                $streamStatus['video_streams'] = $videoStreams;
                $streamStatus['last_success'] = time();
                $success = true;
                break; // Exit the retry loop on success
            } else {
                logMessage("No video streams found in FFprobe output", 'warning');
                // Continue to frame capture check
            }
        } else {
            // FFprobe returned an error
            logMessage("FFprobe check failed with return code $returnVal", 'warning');
            // Continue to frame capture check
        }

        // Method 2: Frame capture test with timeout
        logMessage("Running frame capture test (attempt {$currentTry})");
        $tempFrame = tempnam(sys_get_temp_dir(), 'stream_check_');

        // Try to capture a frame with a timeout
        $captureCommand = "timeout $timeout ffmpeg -i " . escapeshellarg($streamUrl) .
            " -vframes 1 -q:v 2 " . escapeshellarg($tempFrame) . " 2>&1";

        logMessage("Executing command: $captureCommand", 'debug');

        exec($captureCommand, $captureOutput, $captureReturnVal);

        if ($captureReturnVal === 0 && file_exists($tempFrame) && filesize($tempFrame) > 0) {
            $frameSize = filesize($tempFrame);
            logMessage("Successfully captured frame ($frameSize bytes)");
            unlink($tempFrame);

            $streamStatus['active'] = true;
            $streamStatus['message'] = 'Recording URL is accessible (frame capture successful)';
            $streamStatus['last_success'] = time();
            $success = true;
            break; // Exit the retry loop on success
        } else {
            logMessage("Frame capture failed with return code $captureReturnVal", 'error');

            if (file_exists($tempFrame)) {
                $frameSize = filesize($tempFrame);
                logMessage("Frame exists but might be empty or invalid ($frameSize bytes)");
                unlink($tempFrame);
            }

            // Only set as not accessible if this is the last retry
            if ($currentTry >= $maxRetries) {
                $streamStatus['active'] = false;
                $streamStatus['message'] = 'Record URL is not accessible (frame capture failed)';
            }
        }

        // Method 3: Simple connection test as last resort
        if (!$success) {
            logMessage("Running basic connection test (attempt {$currentTry})");

            // Construct a command to just test connection
            $connectionCommand = "timeout 5 curl -s -I " . escapeshellarg($streamUrl) . " 2>&1";
            exec($connectionCommand, $connectionOutput, $connectionReturnVal);

            logMessage("Connection test return code: $connectionReturnVal", 'debug');

            if ($connectionReturnVal === 0) {
                logMessage("Basic connection test succeeded, but media checks failed");
                $streamStatus['message'] = 'Recording URL is reachable but not streaming properly';
                
                // If we're on the last retry and the connection succeeds but the stream checks fail,
                // this might be a temporary issue. If we had success in the last hour, maintain active status.
                if ($currentTry >= $maxRetries && isset($streamStatus['last_success'])) {
                    $oneHourAgo = time() - 3600; // One hour ago
                    if ($streamStatus['last_success'] > $oneHourAgo) {
                        logMessage("Maintaining active status due to recent previous success");
                        $streamStatus['active'] = true;
                        $streamStatus['message'] .= ' (Maintained active status due to recent success)';
                    }
                }
            } else {
                logMessage("Basic connection test failed");
            }
        }

    } catch (Exception $e) {
        logMessage("Error during stream check: " . $e->getMessage(), 'error');
        $streamStatus['message'] = 'Error checking stream: ' . $e->getMessage();
    }
}

// Calculate check duration
$endTime = microtime(true);
$streamStatus['check_duration'] = round($endTime - $startTime, 2);
$streamStatus['retries'] = $currentTry - 1;

// Special case: If this is a second consecutive failure but we had a recent success
if (!$streamStatus['active'] && isset($streamStatus['last_success'])) {
    // Check if the last success was within the last 10 minutes
    $tenMinutesAgo = time() - 600; // 10 minutes ago
    
    if ($streamStatus['last_success'] > $tenMinutesAgo) {
        // Get previous status to see if this is a consecutive failure
        $prevStatus = null;
        if (file_exists($statusFile)) {
            $prevStatus = json_decode(file_get_contents($statusFile), true);
        }
        
        // If previous check was also a failure, but we had a recent success,
        // we'll override to show as active to prevent flickering
        if ($prevStatus && isset($prevStatus['active']) && $prevStatus['active'] === false) {
            logMessage("Overriding to active status due to recent success within last 10 minutes");
            $streamStatus['active'] = true;
            $streamStatus['message'] = 'Recording URL is likely accessible (recent success within 10 minutes)';
        }
    }
}

// Write status to file
file_put_contents($statusFile, json_encode($streamStatus, JSON_PRETTY_PRINT));

// Log completion
logMessage("Record URL check completed in {$streamStatus['check_duration']}s with {$streamStatus['retries']} retries. Result: " .
    ($streamStatus['active'] ? 'ACCESSIBLE' : 'NOT ACCESSIBLE'));

// Clean up lock file
if (file_exists($lockFile)) {
    unlink($lockFile);
    logMessage("Removed lock file");
}

// Exit the script
exit;
