<?php
/**
 * Direct Stream Status API
 * Provides immediate status information with no caching
 */

require_once 'settings.php';
require_once 'logging.php';

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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

// Status file path
$statusFile = 'json/stream_status.json';

// Check if the user wants to force a new check
$forceCheck = isset($_GET['force_check']) && $_GET['force_check'] == '1';

try {
    // If force check is requested, trigger the background service
    if ($forceCheck) {
        // Only allow force check every 2 seconds
        $lastForceCheck = isset($_SESSION['last_force_check']) ? $_SESSION['last_force_check'] : 0;
        $now = time();
        
        if ($now - $lastForceCheck < 2) {
            // Return current status with a message
            $status = getStreamStatus();
            $status['message'] .= ' (Too many force checks, please wait)';
            echo json_encode($status);
            exit;
        }
        
        // Update the session
        $_SESSION['last_force_check'] = $now;
        
        // First check if the service is already running
        $isRunning = false;
        if (PHP_OS_FAMILY !== 'Windows') {
            exec("ps aux | grep stream_monitor_service.php | grep -v grep", $output);
            $isRunning = !empty($output);
        }
        
        if (!$isRunning) {
            // Start the background process with highest priority
            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen('start /B php stream_monitor_service.php', 'r'));
            } else {
                // Run with nice to give higher priority
                exec('nice -n -10 php stream_monitor_service.php > /dev/null 2>&1 &');
            }
            
            // Log that a check was forced
            $logFile = 'logs/stream_url_check.log';
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] [info] Force check requested by user: {$_SESSION['username']}\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
        
        // Return temporary status while check is in progress
        echo json_encode([
            'active' => false,
            'message' => 'Stream check triggered, refreshing...',
            'checking' => true,
            'last_check' => time(),
            'next_check' => time() + 2
        ]);
        exit;
    }
    
    // Always return a fresh read of the status file
    $status = getStreamStatus(true);
    
    // Automatically trigger a new check if status is too old (over 10 minutes)
    if (time() - $status['last_check'] > 600) {
        // First check if the service is already running
        $isRunning = false;
        if (PHP_OS_FAMILY !== 'Windows') {
            exec("ps aux | grep stream_monitor_service.php | grep -v grep", $output);
            $isRunning = !empty($output);
        }
        
        if (!$isRunning) {
            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen('start /B php stream_monitor_service.php', 'r'));
            } else {
                exec('php stream_monitor_service.php > /dev/null 2>&1 &');
            }
        }
        
        $status['message'] .= ' (Check expired, refreshing in background)';
    }
    
    echo json_encode($status);
    
} catch (Exception $e) {
    echo json_encode([
        'active' => false,
        'message' => 'Error checking stream: ' . $e->getMessage(),
        'error' => true
    ]);
}
exit;

/**
 * Get the current stream status from the status file with no caching
 * 
 * @param bool $forceFresh Force a fresh read from the file
 * @return array Status data
 */
function getStreamStatus($forceFresh = false) {
    global $statusFile, $settings;
    static $cachedStatus = null;
    static $lastReadTime = 0;
    
    // If we have a cached status and we don't need a fresh read, return it
    if (!$forceFresh && $cachedStatus !== null && (time() - $lastReadTime) < 1) {
        return $cachedStatus;
    }
    
    // If status file doesn't exist, create a default status
    if (!file_exists($statusFile)) {
        $streamUrl = $settings['srt_url'] ?? '';
        
        $defaultStatus = [
            'active' => false,
            'message' => 'Stream status unknown (never checked)',
            'last_check' => 0,
            'next_check' => time(),
            'check_duration' => 0
        ];
        
        file_put_contents($statusFile, json_encode($defaultStatus, JSON_PRETTY_PRINT));
        $cachedStatus = $defaultStatus;
        $lastReadTime = time();
        return $defaultStatus;
    }
    
    // Clear stat cache to ensure we get fresh file info
    clearstatcache(true, $statusFile);
    
    // Read the status file with shared lock for thread safety
    $statusJson = file_get_contents($statusFile);
    $status = json_decode($statusJson, true) ?: [];
    
    // Add age information
    $status['age'] = time() - ($status['last_check'] ?? 0);
    
    // Update cache
    $cachedStatus = $status;
    $lastReadTime = time();
    
    return $status;
}
