<?php
// Begin or resume session to maintain user authentication state
session_start();
require_once 'settings.php';

// Check if user is authenticated and has admin privileges
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Get settings for timezone
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Scheduler log file path
$schedulerLogFile = 'logs/scheduler_log.txt';

// Check if file exists
if (!file_exists($schedulerLogFile)) {
    echo "No scheduler log file found.";
    exit;
}

// Return the log content, optionally with reverse order (newest first)
$reverse = isset($_GET['reverse']) ? (bool)$_GET['reverse'] : true;

if ($reverse) {
    // Read file into an array of lines
    $lines = file($schedulerLogFile, FILE_IGNORE_NEW_LINES);
    
    // Reverse the array to show newest entries first
    $lines = array_reverse($lines);
    
    // Join the array back into a string and output
    echo implode("\n", $lines);
} else {
    // Just output the file as is
    readfile($schedulerLogFile);
}
