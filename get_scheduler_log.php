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

// Path to scheduler log file
$logFile = 'logs/scheduler.log';

// Check if log file exists
if (!file_exists($logFile)) {
    echo "No scheduler log entries found.";
    exit;
}

// Get max lines parameter or use default
$maxLines = isset($_GET['lines']) ? intval($_GET['lines']) : 1000;

// Read log file
$logContent = file_get_contents($logFile);

// Optionally reverse the order to show newest first
$reverseOrder = isset($_GET['reverse']) ? (bool)$_GET['reverse'] : true;

if ($reverseOrder) {
    // Split content into lines
    $lines = explode("\n", $logContent);
    
    // Filter out empty lines
    $lines = array_filter($lines, function($line) {
        return !empty(trim($line));
    });
    
    // Limit the number of lines if needed
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, -$maxLines);
    }
    
    // Reverse lines to show newest first
    $lines = array_reverse($lines);
    
    // Join lines back together
    $logContent = implode("\n", $lines);
}

// Output the log content
echo $logContent;
