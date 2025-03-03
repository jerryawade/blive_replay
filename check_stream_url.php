<?php
/**
 * Asynchronous Stream URL Validator with Improved Logging
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
 * Truncate log file if it exceeds maximum lines
 * @param string $logFile Path to log file
 * @param int $maxLines Maximum number of lines to keep
 * @return bool Success status
 */
function truncateLog($logFile, $maxLines = 5000) {
    if (!file_exists($logFile)) return true;

    $lines = file($logFile);
    $totalLines = count($lines);

    if ($totalLines <= $maxLines) return true;

    // Calculate how many lines to remove (keep the newest entries)
    $linesToKeep = $maxLines;
    $linesToRemove = $totalLines - $linesToKeep;

    // Keep only the most recent lines
    $lines = array_slice($lines, $linesToRemove);

    // Write the truncated content back to the file
    return file_put_contents($logFile, implode('', $lines)) !== false;
}

/**
 * Log stream check details
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 * @param string $streamType Stream type (primary/secondary/combined)
 */
function logStreamCheck($message, $level = 'info', $streamType = 'combined') {
    global $logFile, $maxLogSize, $maxLogLines;

    // Truncate log if needed
    truncateLog($logFile, $maxLogLines);

    // Prepare log entry
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] [{$streamType}] {$message}\n";

    // Append to log file, rotate if needed
    if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
        $backupFile = $logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename($logFile, $backupFile);
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Validate Video Stream
 * @param string $url Stream URL to validate
 * @param string $type Stream type (primary/secondary)
 * @return array Validation result
 */
function validateVideoStream($url, $type = 'primary') {
    // Log start of stream validation
    logStreamCheck("Validating {$type} stream", 'info', $type);

    // Check if URL is empty
    if (empty($url)) {
        $result = [
            'active' => false,
            'message' => "No {$type} stream URL configured"
        ];
        logStreamCheck("No stream URL configured", 'error', $type);
        return $result;
    }

    // Method 1: Quick FFprobe check
    $infoCommand = sprintf(
        "ffprobe -v quiet -print_format json -show_entries stream=codec_type -of default=noprint_wrappers=1 %s 2>&1",
        escapeshellarg($url)
    );
    exec($infoCommand, $output, $returnVal);

    // Log basic FFprobe result
    logStreamCheck("FFprobe return value: {$returnVal}", 'debug', $type);

    if ($returnVal === 0) {
        // Count video streams
        $videoStreams = array_filter($output, function($line) {
            return strpos($line, 'codec_type=video') !== false;
        });
        $videoStreamCount = count($videoStreams);

        if ($videoStreamCount > 0) {
            $result = [
                'active' => true,
                'message' => "{$type} stream is accessible",
                'streams' => $videoStreamCount
            ];
            logStreamCheck("Found {$videoStreamCount} video stream(s)", 'info', $type);
            return $result;
        } else {
            $result = [
                'active' => false,
                'message' => "No video streams found in {$type} stream"
            ];
            logStreamCheck("No video streams found", 'warning', $type);
            return $result;
        }
    }

    // Method 2: Frame capture test
    $tempFrame = tempnam(sys_get_temp_dir(), 'stream_check_');
    $captureCommand = sprintf(
        "ffmpeg -i %s -vframes 1 -q:v 2 %s 2>&1",
        escapeshellarg($url),
        escapeshellarg($tempFrame)
    );
    exec($captureCommand, $output, $returnVal);

    // Log frame capture result
    logStreamCheck("FFmpeg frame capture return value: {$returnVal}", 'debug', $type);

    if ($returnVal === 0 && file_exists($tempFrame) && filesize($tempFrame) > 0) {
        unlink($tempFrame);
        $result = [
            'active' => true,
            'message' => "{$type} stream accessible via frame capture"
        ];
        logStreamCheck("Stream accessible via frame capture", 'info', $type);
        return $result;
    }

    // Cleanup temp file if it exists
    if (file_exists($tempFrame)) {
        unlink($tempFrame);
    }

    // If all methods fail
    $result = [
        'active' => false,
        'message' => "{$type} stream is not accessible"
    ];
    logStreamCheck("Stream not accessible after multiple validation attempts", 'error', $type);
    return $result;
}

// Get the target stream type to check (primary or secondary)
$streamType = $_GET['stream'] ?? 'primary';

try {
    // Log start of check
    logStreamCheck("Starting stream status check", 'info', 'combined');
    logStreamCheck("Checking {$streamType} stream", 'info', 'combined');

    // Get stream URL from settings
    $streamUrl = '';
    if ($streamType === 'secondary') {
        $streamUrl = $settings['srt_url_secondary'] ?? '';
    } else {
        $streamUrl = $settings['srt_url'] ?? '';
    }

    // Validate and check the stream
    $result = validateVideoStream($streamUrl, $streamType);

    // Log final result
    logStreamCheck("Stream check completed", 'info', 'combined');
    logStreamCheck("Stream status: " . ($result['active'] ? 'Accessible' : 'Not Accessible'), 'info', 'combined');

    // Return result
    echo json_encode($result);
} catch (Exception $e) {
    // Log any unexpected errors
    logStreamCheck("Unexpected error during stream check: " . $e->getMessage(), 'error', 'combined');

    echo json_encode([
        'active' => false,
        'message' => "Error checking {$streamType} stream: " . $e->getMessage()
    ]);
}
exit;
?>