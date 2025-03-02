<?php
// Begin session
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'logging.php';
require_once 'settings.php';
require_once 'user_management.php';

// Get settings for timezone
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Get time range parameter
$timeRange = $_GET['timeRange'] ?? 'week';

// Validate time range
$validTimeRanges = ['day', 'week', 'month', 'year'];
if (!in_array($timeRange, $validTimeRanges)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid time range specified'
    ]);
    exit;
}

// Set cutoff date based on selected time range
$cutoffDate = new DateTime();
switch ($timeRange) {
    case 'day':
        $cutoffDate->modify('-1 day');
        break;
    case 'week':
        $cutoffDate->modify('-7 days');
        break;
    case 'month':
        $cutoffDate->modify('-30 days');
        break;
    case 'year':
        $cutoffDate->modify('-365 days');
        break;
}

// Initialize logger to get data
$activityLogger = new ActivityLogger();
$userManager = new UserManager();

// Get all users
$users = $userManager->getUsers();
$usernames = array_keys($users);

// Get all activities within the time range
$allActivities = $activityLogger->getActivities(null);

// Prepare data for the chart
$userData = [];
$videoPlays = [];
$livestreamViews = [];

// Initialize counts for each user
foreach ($usernames as $username) {
    $userData[$username] = [
        'played_vlc' => 0,
        'livestream_click' => 0
    ];
}

// Count activities for each user
foreach ($allActivities as $activity) {
    // Skip if username not in our list or activity type not relevant
    if (!isset($userData[$activity['username']]) || 
        ($activity['action'] !== 'played_vlc' && $activity['action'] !== 'livestream_click')) {
        continue;
    }
    
    // Check if within time range
    $activityDate = new DateTime($activity['timestamp']);
    if ($activityDate < $cutoffDate) {
        continue;
    }
    
    // Increment the appropriate count
    $userData[$activity['username']][$activity['action']]++;
}

// Extract data for chart
foreach ($usernames as $username) {
    $videoPlays[] = $userData[$username]['played_vlc'];
    $livestreamViews[] = $userData[$username]['livestream_click'];
}

// Sort data by total activity (optional)
$totalActivity = [];
foreach ($usernames as $index => $username) {
    $totalActivity[$index] = $videoPlays[$index] + $livestreamViews[$index];
}

// Sort users by total activity
array_multisort(
    $totalActivity, SORT_DESC,
    $usernames, 
    $videoPlays, 
    $livestreamViews
);

// Return the result
echo json_encode([
    'success' => true,
    'users' => $usernames,
    'videoPlays' => $videoPlays,
    'livestreamViews' => $livestreamViews,
    'timeRange' => $timeRange
]);
