<?php
session_start();
require_once 'logging.php';
require_once 'settings.php';


// Check if user is authenticated and is admin
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

$activityLogger = new ActivityLogger();
$activities = $activityLogger->getActivities();

header('Content-Type: application/json');
echo json_encode(['activities' => $activities]);
?>