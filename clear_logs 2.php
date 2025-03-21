<?php
/**
 * clear_logs.php
 * Clears the specified system log while preserving user activity logs
 */

// Start session and include required files
session_start();
require_once 'settings.php';

header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get settings for timezone
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Log files that can be cleared (NOT including user activity logs)
$allowedLogs = [
    'ffmpeg' => 'logs/ffmpeg.log',
    'scheduler' => 'logs/scheduler.log',
    'schedulerFile' => 'logs/scheduler_log.txt',
    'stream' => 'logs/stream_url_check.log',
    'email' => 'logs/email.log',
    'debug' => 'logs/debug.log'
];

// Get which log to clear (must match one of the allowed logs)
$targetLog = $_POST['log'] ?? '';

if (!isset($allowedLogs[$targetLog])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid log type specified'
    ]);
    exit;
}

// Get the file path for the specified log
$logFile = $allowedLogs[$targetLog];

// Results object
$result = [
    'success' => true,
    'message' => ucfirst($targetLog) . ' log cleared successfully',
    'logType' => $targetLog
];

try {
    // Clear the log file
    $success = clearLogFile($logFile);

    if (!$success) {
        $result['success'] = false;
        $result['message'] = 'Failed to clear ' . ucfirst($targetLog) . ' log';
    }
} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = 'Error: ' . $e->getMessage();
}

// Return the results
echo json_encode($result);
exit;

/**
 * Clear a log file while preserving its existence
 *
 * @param string $file Path to the log file
 * @return bool Success status
 */
function clearLogFile($file) {
    // Check if file exists
    if (!file_exists($file)) {
        return true; // Consider non-existent file as success
    }

    // Empty the file but keep it
    $success = file_put_contents($file, '') !== false;

    // If we successfully cleared the file, add a timestamp entry
    if ($success) {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[{$timestamp}] [info] Log file cleared by " . $_SESSION['username'] . "\n";
        file_put_contents($file, $message, FILE_APPEND);
    }

    return $success;
}