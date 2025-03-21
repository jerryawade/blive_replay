<?php
session_start();
require_once 'logging.php';
require_once 'settings.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

// Get settings for timezone
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

$activityLogger = new ActivityLogger();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set timezone from settings
    date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');
    
    // Get current timestamp in the configured timezone
    $timestamp = date('Y-m-d H:i:s');

    $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
    $activityLogger->logActivity(
        $_SESSION['username'],
        $_POST['action'],
        $filename,
        $timestamp  // Pass the timestamp explicitly
    );
}

http_response_code(200);
?>
