<?php
/**
 * Asynchronous Stream URL Validator with Comprehensive Logging
 */

require_once 'settings.php';
require_once 'logging.php';

session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Log file configuration
$logFile = 'logs/stream_url_check.log';
$maxLogSize = 1 * 1024 * 1024; // 1 MB
$maxLogLines = 5000; // Maximum number of log lines to keep

/**
 * Truncate log file if it exceeds the maximum number of lines
 */
function truncateLog($logFile, $maxLines = 5000)
{
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
    return file_put_contents($logFile, implode('', $lines)) !== false;
}

/**
 * Log a message to the stream URL check log file
 *
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 */
function logStreamCheck($message, $level = 'info')
{
    global $logFile, $maxLogSize, $maxLogLines;

    // Truncate log if it's too large
    truncateLog($logFile, $maxLogLines);

    // Prepare log entry
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";

    // Append to log file, rotate if needed
    if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
        $backupFile = $logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename($logFile, $backupFile);
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Validate Video Stream
 * Uses multiple methods to check stream accessibility
 */
function validateVideoStream($url)
{
    // Log the start of stream validation
    logStreamCheck("Starting stream URL validation for: $url", 'info');

    // Method 1: Quick FFprobe check
    $infoCommand = sprintf(
        "ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1",
        escapeshellarg($url)
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
            // Log successful stream detection via FFprobe
            logStreamCheck("Stream URL is accessible. Video streams detected.", 'info');
            return [
                'active' => true,
                'message' => 'Stream URL is accessible',
                'streams' => $videoStreams
            ];
        } else {
            // Log that no video streams were found
            logStreamCheck("No video streams found via FFprobe.", 'warning');
        }
    } else {
        // Log FFprobe failure
        logStreamCheck("FFprobe command failed. Output: " . implode("\n", $output), 'warning');
    }

    // Method 2: Frame capture test
    $tempFrame = tempnam(sys_get_temp_dir(), 'stream_check_');
    $captureCommand = sprintf(
        "ffmpeg -i %s -vframes 1 -q:v 2 %s 2>&1",
        escapeshellarg($url),
        escapeshellarg($tempFrame)
    );
    exec($captureCommand, $output, $returnVal);

    // Log frame capture command result
    logStreamCheck("FFmpeg frame capture command result: Return Val = $returnVal", 'debug');

    if ($returnVal === 0 && file_exists($tempFrame) && filesize($tempFrame) > 0) {
        unlink($tempFrame);
        // Log successful frame capture
        logStreamCheck("Stream URL is accessible via frame capture method.", 'info');
        return [
            'active' => true,
            'message' => 'Stream URL is accessible via frame capture'
        ];
    } else {
        // Log frame capture failure
        logStreamCheck("Frame capture failed. Output: " . implode("\n", $output), 'warning');
    }

    // Cleanup temp file if it exists
    if (file_exists($tempFrame)) {
        unlink($tempFrame);
    }

    // If all methods fail
    logStreamCheck("Stream URL is not accessible after multiple validation attempts.", 'error');
    return [
        'active' => false,
        'message' => 'Stream URL is not accessible'
    ];
}

// Perform stream check
try {
    // Get the stream URL from settings
    $settingsManager = new SettingsManager();
    $settings = $settingsManager->getSettings();
    $streamUrl = $settings['srt_url'] ?? '';

    // Log the start of the process
    logStreamCheck("Stream URL check initiated by " . ($_SESSION['username'] ?? 'Unknown User'), 'info');

    // Validate input
    if (empty($streamUrl)) {
        logStreamCheck("No stream URL configured in settings.", 'error');
        echo json_encode([
            'success' => false,
            'active' => false,
            'message' => 'No recording URL configured in settings'
        ]);
        exit;
    }

    // Check if a bypass cache parameter is set
    $bypassCache = isset($_GET['bypass_cache']);
    if ($bypassCache) {
        logStreamCheck("Cache bypass requested for stream validation.", 'info');
    }

    // Perform validation
    $result = validateVideoStream($streamUrl);

    // Log the final result
    logStreamCheck("Stream validation completed. Result: " . ($result['active'] ? 'Accessible' : 'Not Accessible'), 'info');

    echo json_encode($result);
} catch (Exception $e) {
    // Log any unexpected errors
    logStreamCheck("Unexpected error during stream check: " . $e->getMessage(), 'error');

    echo json_encode([
        'active' => false,
        'message' => 'Error checking stream: ' . $e->getMessage()
    ]);
}
exit;
?>