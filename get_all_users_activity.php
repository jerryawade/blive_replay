<?php
// Start the session to track user authentication and roles
session_start();
// Set the response content type to JSON for API compatibility
header('Content-Type: application/json');

// Check if the user is authenticated and has admin privileges
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Return a 403 Forbidden response if the user lacks proper access
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include the necessary files
require_once 'settings.php';
require_once 'user_management.php';
require_once 'activity_log_archiver.php';

// Initialize settings manager and retrieve application settings
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set the timezone from settings, defaulting to 'America/Chicago' if not specified
$timezone = $settings['timezone'] ?? 'America/Chicago';
date_default_timezone_set($timezone);

// Get the time range parameter from the query string, defaulting to 'week'
$timeRange = $_GET['timeRange'] ?? 'week';
// Define valid time ranges to ensure input integrity
$validTimeRanges = ['day', 'week', 'month', 'year'];
if (!in_array($timeRange, $validTimeRanges)) {
    // Return an error if an invalid time range is provided
    echo json_encode(['success' => false, 'message' => 'Invalid time range specified']);
    exit;
}

// Use the activity log archiver to get the analytics data
$archiver = new ActivityLogArchiver();
$analyticsData = $archiver->getAnalytics(null, $timeRange);

// Instantiate user manager for data retrieval
$userManager = new UserManager();

// Fetch all users and extract their usernames
$users = $userManager->getUsers();
$usernames = array_keys($users);

// Calculate start and end dates for the time range for inclusion in the response
$endDate = new DateTime();
$cutoffDate = new DateTime();

// Adjust cutoff date based on the selected time range
switch ($timeRange) {
    case 'day':
        $cutoffDate->setTime(0, 0, 0);
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

// Prepare arrays for chart data
$chartUsernames = [];
$videoPlays = [];
$livestreamViews = [];

// Make sure all users are represented in data arrays, even if they have no activity
foreach ($usernames as $username) {
    $chartUsernames[] = $username;

    // Get values from analytics data or default to 0
    $plays = isset($analyticsData['videoPlays'][$username]) ?
        $analyticsData['videoPlays'][$username] : 0;

    $views = isset($analyticsData['livestreamViews'][$username]) ?
        $analyticsData['livestreamViews'][$username] : 0;

    $videoPlays[] = $plays;
    $livestreamViews[] = $views;
}

// Calculate total activity for sorting
$totalActivity = [];
foreach ($chartUsernames as $index => $username) {
    $totalActivity[$index] = $videoPlays[$index] + $livestreamViews[$index];
}

// Sort arrays by total activity (descending)
array_multisort($totalActivity, SORT_DESC, $chartUsernames, $videoPlays, $livestreamViews);

// Return the results as JSON
echo json_encode([
    'success' => true,
    'users' => $chartUsernames,
    'videoPlays' => $videoPlays,
    'livestreamViews' => $livestreamViews,
    'timeRange' => $timeRange,
    'cutoffDate' => $cutoffDate->format('Y-m-d H:i:s'),
    'endDate' => $endDate->format('Y-m-d H:i:s')
]);