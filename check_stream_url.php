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
// Lock file to prevent concurrent checks
$lockFile = 'json/stream_check.lock';
// Log file path
$logFile = 'logs/stream_url_check.log';

// Ensure directories exist
if (!is_dir('json')) {
    mkdir('json', 0777, true);
}
if (!is_dir('logs')) {
    mkdir('logs', 0777, true);
}

// Helper function to log messages
function logStreamCheck($message, $level = 'info') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Check if we need to clear the status after a settings change
if (isset($_SESSION['srt_url_changed']) && $_SESSION['srt_url_changed'] === true) {
    // Clear status file to force a fresh check
    if (file_exists($statusFile)) {
        unlink($statusFile);
        logStreamCheck("Cleared status file due to SRT URL change");
    }
    // Reset the flag
    $_SESSION['srt_url_changed'] = false;
}

// Check if the user wants to force a new check
$forceCheck = isset($_GET['force_check']) && $_GET['force_check'] == '1';

try {
    // Check if a check is already in progress
    $checkInProgress = false;
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        $now = time();
        
        // If lock file is older than 60 seconds, consider it stale
        if ($now - $lockTime > 60) {
            // Stale lock, remove it
            unlink($lockFile);
            logStreamCheck("Removed stale lock file (age: " . ($now - $lockTime) . "s)");
        } else {
            // Valid lock, check is in progress
            $checkInProgress = true;
        }
    }
    
    // If a check is in progress, return status with checking flag
    if ($checkInProgress) {
        // If we have a status file, include its data
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true) ?: [];
            $status['checking'] = true;
            echo json_encode($status);
        } else {
            // No status file, return default checking status
            echo json_encode([
                'active' => false,
                'message' => 'Checking recording URL...',
                'checking' => true,
                'last_check' => time()
            ]);
        }
        exit;
    }
    
    // If force check requested, initiate a new check
    if ($forceCheck) {
        // Rate limit force checks (max once every 5 seconds)
        $lastForceCheck = isset($_SESSION['last_force_check']) ? $_SESSION['last_force_check'] : 0;
        $now = time();
        
        if ($now - $lastForceCheck < 5) {
            logStreamCheck("Force check requested too soon", 'warning');
            echo json_encode([
                'active' => false,
                'message' => 'Please wait a moment before checking again',
                'checking' => true,
                'last_check' => time()
            ]);
            exit;
        }
        
        // Update session with last check time
        $_SESSION['last_force_check'] = $now;
        
        // Create lock file
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        
        // Log the force check request
        logStreamCheck("Force check requested by user: " . $_SESSION['username']);
        
        // Start the stream check in background
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B php -f stream_monitor_service.php', 'r'));
        } else {
            exec('php -f stream_monitor_service.php > /dev/null 2>&1 &');
        }
        
        // Return status indicating check is in progress
        echo json_encode([
            'active' => false,
            'message' => 'Checking recording URL...',
            'checking' => true,
            'last_check' => time()
        ]);
        exit;
    }
    
    // Normal flow - check if we have a status file
    if (file_exists($statusFile)) {
        // Clear stat cache to get fresh file info
        clearstatcache(true, $statusFile);
        
        // Read status file
        $status = json_decode(file_get_contents($statusFile), true) ?: [];
        
        // Add age metadata
        $status['file_age'] = time() - filemtime($statusFile);
        
        // Check if status is too old (over 5 minutes) and start a refresh
        if (!isset($status['last_check']) || time() - $status['last_check'] > 300) {
            if (!file_exists($lockFile)) {
                // Create lock file
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
                
                // Log the auto-refresh
                logStreamCheck("Status is too old, auto-refreshing");
                
                // Start background check
                if (PHP_OS_FAMILY === 'Windows') {
                    pclose(popen('start /B php -f stream_monitor_service.php', 'r'));
                } else {
                    exec('php -f stream_monitor_service.php > /dev/null 2>&1 &');
                }
                
                // Return status with checking flag
                $status['checking'] = true;
                $status['message'] = 'Status is outdated, refreshing...';
                echo json_encode($status);
                exit;
            }
        }
        
        // Return current status
        echo json_encode($status);
        exit;
    }
    
    // No status file exists, create one and trigger a check
    logStreamCheck("No status file exists, creating default and triggering check");
    
    // Create lock file
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
    
    // Create default status
    $defaultStatus = [
        'active' => false,
        'message' => 'Checking recording URL for the first time...',
        'checking' => true,
        'last_check' => time()
    ];
    
    // Write to status file
    file_put_contents($statusFile, json_encode($defaultStatus, JSON_PRETTY_PRINT));
    
    // Start background check
    if (PHP_OS_FAMILY === 'Windows') {
        pclose(popen('start /B php -f stream_monitor_service.php', 'r'));
    } else {
        exec('php -f stream_monitor_service.php > /dev/null 2>&1 &');
    }
    
    // Return the initial status
    echo json_encode($defaultStatus);
    
} catch (Exception $e) {
    logStreamCheck("Error in check_stream_url.php: " . $e->getMessage(), 'error');
    echo json_encode([
        'active' => false,
        'message' => 'Error checking stream: ' . $e->getMessage(),
        'error' => true,
        'timestamp' => time()
    ]);
}
