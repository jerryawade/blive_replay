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

// Include required classes for logging, settings, and user management
require_once 'logging.php';
require_once 'settings.php';
require_once 'user_management.php';

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

// Initialize date objects for the time range boundaries
$cutoffDate = new DateTime(); // Start of the time range
$endDate = new DateTime();    // End of the time range (current time)
// Adjust cutoff date based on the selected time range
switch ($timeRange) {
    case 'day':
        // For 'day', set the cutoff to midnight today to count only today's activities
        $cutoffDate->setTime(0, 0, 0);
        break;
    case 'week':
        // For 'week', go back 7 days from now
        $cutoffDate->modify('-7 days');
        break;
    case 'month':
        // For 'month', approximate as 30 days back
        $cutoffDate->modify('-30 days');
        break;
    case 'year':
        // For 'year', go back 365 days (ignores leap years for simplicity)
        $cutoffDate->modify('-365 days');
        break;
}

// Instantiate activity logger and user manager for data retrieval
$activityLogger = new ActivityLogger();
$userManager = new UserManager();

// Fetch all users and extract their usernames
$users = $userManager->getUsers();
$usernames = array_keys($users);
// Retrieve all logged activities (assumes ActivityLogger handles the data source)
$allActivities = $activityLogger->getActivities(null);

// Initialize arrays to store user activity counts and chart data
$userData = [];
$videoPlays = [];
$livestreamViews = [];

// Prepopulate user data with zero counts for video plays and livestream views
foreach ($usernames as $username) {
    $userData[$username] = [
        'played_vlc' => 0,       // Tracks video plays
        'livestream_click' => 0  // Tracks livestream views
    ];
}

// Process each activity and count relevant actions within the time range
foreach ($allActivities as $activity) {
    // Skip if the username isn't recognized or the action isn't relevant
    if (!isset($userData[$activity['username']]) ||
        ($activity['action'] !== 'played_vlc' && $activity['action'] !== 'livestream_click')) {
        continue;
    }

    // Convert activity timestamp to a DateTime object for comparison
    $activityDate = new DateTime($activity['timestamp']);
    // Skip if the activity falls outside the defined time range
    if ($activityDate < $cutoffDate || $activityDate > $endDate) {
        continue;
    }

    // Increment the count for the specific action
    $userData[$activity['username']][$activity['action']]++;
}

// Prepare data arrays for the chart by extracting counts for each user
foreach ($usernames as $username) {
    $videoPlays[] = $userData[$username]['played_vlc'];
    $livestreamViews[] = $userData[$username]['livestream_click'];
}

// Calculate total activity per user for sorting purposes
$totalActivity = [];
foreach ($usernames as $index => $username) {
    $totalActivity[$index] = $videoPlays[$index] + $livestreamViews[$index];
}

// Sort users by total activity in descending order, maintaining array associations
array_multisort($totalActivity, SORT_DESC, $usernames, $videoPlays, $livestreamViews);

// Return the aggregated data as JSON, including time range boundaries for debugging
echo json_encode([
    'success' => true,
    'users' => $usernames,              // List of usernames
    'videoPlays' => $videoPlays,        // Video play counts per user
    'livestreamViews' => $livestreamViews, // Livestream view counts per user
    'timeRange' => $timeRange,          // Selected time range
    'cutoffDate' => $cutoffDate->format('Y-m-d H:i:s'), // Start of the time range
    'endDate' => $endDate->format('Y-m-d H:i:s')        // End of the time range
]);