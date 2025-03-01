<?php
// Load required components
require_once 'settings.php';
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Handle SSE endpoint
if (isset($_GET['sse']) && $_GET['sse'] === 'listen') {
    // Set headers for SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');  // Disable nginx buffering

    // Disable output buffering
    if (ob_get_level()) ob_end_clean();

    // Set unlimited time limit
    set_time_limit(0);
    ignore_user_abort(false);  // Stop script when client disconnects

    $lastCheck = 0;
    $lastKnownChange = file_exists('last_change.txt') ? (int)file_get_contents('last_change.txt') : 0;

    while (true) {
        // Check for changes
        clearstatcache();
        if (file_exists('last_change.txt')) {
            $currentChange = (int)file_get_contents('last_change.txt');
            if ($currentChange > $lastKnownChange) {
                echo "event: recordingChange\n";
                echo "data: " . $currentChange . "\n\n";
                $lastKnownChange = $currentChange;
                flush();
            }
        }

        // Send heartbeat every 5 seconds
        $now = time();
        if ($now - $lastCheck >= 5) {
            echo "event: heartbeat\n";
            echo "data: " . $now . "\n\n";
            flush();
            $lastCheck = $now;
        }

        // Small sleep to prevent CPU overuse
        usleep(250000); // 0.25 seconds

        // Break if client disconnected
        if (connection_aborted()) {
            break;
        }
    }
    exit();
}

// Handle session keep-alive ping
if (isset($_GET['ping']) && $_GET['ping'] === 'session') {
    if (isAuthenticated()) {
        // Update session last activity time
        $_SESSION['LAST_ACTIVITY'] = time();
        // Send success response
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'timestamp' => time()]);
    } else {
        // Session expired
        header('Content-Type: application/json');
        echo json_encode(['status' => 'expired']);
    }
    exit;
}

// Start the session
session_start();

// Custom logging functions
function debug_log($message)
{
    // Write to PHP error log
    error_log($message);
    // Write to local file
    $logFile = 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Load required components
require_once 'user_management.php';
require_once 'logging.php';
require_once 'FFmpegService.php';  // Include the new FFmpeg service

// Initialize components
$userManager = new UserManager();
$activityLogger = new ActivityLogger();

// Extended session settings
ini_set('max_execution_time', 0);
ini_set('max_input_time', -1);
ignore_user_abort(true);
set_time_limit(0);

// Set session save handler if using files
ini_set('session.save_handler', 'files');

// Set session save path (ensure it's writable)
$sessionPath = __DIR__ . '/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
if (!is_writable($sessionPath)) {
    die("Session directory is not writable.");
}
session_save_path($sessionPath);

// Set session garbage collection parameters
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 43200); // 12 hours in seconds

// Set session cookie parameters
ini_set('session.cookie_lifetime', 43200); // 12 hours in seconds
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

// Set session name and configure cookie parameters
session_name('BLIVESESSID'); // Custom session name
session_set_cookie_params([
    'lifetime' => 43200,          // 12 hours in seconds
    'path' => '/',
    'domain' => '',               // Empty string means current domain only
    'secure' => true,             // Require HTTPS
    'httponly' => true,           // Prevent JavaScript access
    'samesite' => 'Strict'        // CSRF protection
]);

// Start the session
session_start();

// Extend session lifetime on each request
$_SESSION['LAST_ACTIVITY'] = time(); // Update last activity timestamp

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 3600) {
    // Regenerate session ID hourly
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
    error_log("Session ID regenerated at " . date('Y-m-d H:i:s'));
}

// Define directories
$recordingsDir = 'recordings';
$thumbnailsDir = 'thumbnails';

// Initialize FFmpeg service
$ffmpegService = new FFmpegService($recordingsDir, $thumbnailsDir);

// Ensure directories exist with debug logging
if (!is_dir($recordingsDir)) {
    debug_log("Creating recordings directory...");
    $result = mkdir($recordingsDir, 0777, true);
    debug_log("Directory creation result: " . ($result ? "success" : "failed"));
}
if (!is_dir($thumbnailsDir)) {
    debug_log("Creating thumbnails directory...");
    $result = mkdir($thumbnailsDir, 0777, true);
    debug_log("Directory creation result: " . ($result ? "success" : "failed"));
}

// Debug log directory permissions
debug_log("Recordings directory permissions: " . substr(sprintf('%o', fileperms($recordingsDir)), -4));
debug_log("Thumbnails directory permissions: " . substr(sprintf('%o', fileperms($thumbnailsDir)), -4));

// Handle settings updates
handleSettingsUpdate($settingsManager);

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $loginResult = $userManager->verifyLogin($username, $password);
    if ($loginResult['success']) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $loginResult['role'];
        $_SESSION['show_landing'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}

// Handle page navigation
if (isset($_GET['page'])) {
    $_SESSION['show_landing'] = false;
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle return to landing page
if (isset($_POST['return_to_landing'])) {
    $_SESSION['show_landing'] = true;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Check if user is admin
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is authenticated
function isAuthenticated()
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Initialize scheduler files if admin and scheduler is enabled
if (isAdmin() && isset($settings['enable_scheduler']) && $settings['enable_scheduler']) {
    // Create schedules file if it doesn't exist
    $schedulesFile = 'recording_schedules.json';
    if (!file_exists($schedulesFile)) {
        file_put_contents($schedulesFile, json_encode([]));
        chmod($schedulesFile, 0644);
    }

    // Create scheduler state file if it doesn't exist
    $schedulerStateFile = 'scheduler_state.json';
    if (!file_exists($schedulerStateFile)) {
        file_put_contents($schedulerStateFile, json_encode([
            'last_action' => null,
            'last_action_time' => null,
            'current_schedule_id' => null
        ]));
        chmod($schedulerStateFile, 0644);
    }
}

// Generate M3U playlist
function generateM3U($videoFile)
{
    global $settings;
    $fileName = basename($videoFile);
    $serverUrl = $settings['server_url'];
    $m3uContent = "#EXTM3U\n";
    $m3uContent .= "#EXTINF:-1," . $fileName . "\n";
    $m3uContent .= $serverUrl . "/recordings/" . $fileName;
    return $m3uContent;
}

// Generate VLC protocol URL
function generateVLCUrl($videoFile)
{
    global $settings;
    $serverUrl = $settings['server_url'];
    $fullUrl = $serverUrl . "/recordings/" . urlencode(basename($videoFile));
    return "vlc://" . $fullUrl;
}

// Generate video thumbnail - using FFmpegService
function generateThumbnail($videoFile, $thumbnailFile)
{
    global $ffmpegService;
    return $ffmpegService->generateThumbnail($videoFile, $thumbnailFile);
}

// Get video duration - using FFmpegService
function getVideoDuration($videoFile)
{
    global $ffmpegService;
    return $ffmpegService->getVideoDuration($videoFile);
}

// Format VLC stream URL
function formatVLCStreamUrl($url)
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (stripos($userAgent, 'Mac') !== false || stripos($userAgent, 'Linux') !== false) {
        // Remove any existing vlc:// or other protocol prefixes
        $url = preg_replace('#^(vlc://|srt://|rtsp://|rtmp://|srtsp://|rtp://|mms://|udp://)#', '', $url);

        // Remove any leading slashes
        $url = preg_replace('#^//#', '', $url);

        // Add two slashes after protocol if the URL starts with it
        $protocols = ['srt://', 'rtsp://', 'rtmp://', 'srtsp://', 'rtp://', 'mms://', 'udp://'];
        foreach ($protocols as $protocol) {
            if (strpos($url, substr($protocol, 0, -2)) === 0) {
                $url = substr($protocol, 0, -2) . '//' . substr($url, strlen(substr($protocol, 0, -2)));
            }
        }

        // Prepend vlc:// to the URL
        return 'vlc://' . $url;
    } else {
        $url = preg_replace('#^(vlc://|srt://|rtsp://|rtmp://|srtsp://|rtp://|mms://|udp://)#', '', $url);
        $url = preg_replace('#^//#', '', $url);
        return 'vlc://' . $url;
    }
}

// Handle M3U download request
if (isset($_GET['getm3u']) && isAuthenticated()) {
    $requestedFile = basename($_GET['getm3u']);
    if (file_exists($recordingsDir . '/' . $requestedFile)) {
        $activityLogger->logActivity($_SESSION['username'], 'downloaded_m3u', $requestedFile);
        $m3uContent = generateM3U($requestedFile);
        header('Content-Disposition: attachment; filename="' . pathinfo($requestedFile, PATHINFO_FILENAME) . '.m3u"');
        echo $m3uContent;
        exit;
    }
}

// Handle MP4 download request
if (isset($_GET['download']) && isAuthenticated()) {
    $requestedFile = basename($_GET['download']);
    $filePath = $recordingsDir . '/' . $requestedFile;
    if (file_exists($filePath)) {
        $activityLogger->logActivity($_SESSION['username'], 'downloaded_mp4', $requestedFile);
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// Handle recording controls (admin only)
if (isAdmin()) {
    // Start recording - using FFmpegService
    if (isset($_POST['start'])) {
        $result = $ffmpegService->startRecording(
            $settings['srt_url'],
            $_SESSION['username'],
            $activityLogger
        );

        // Add this code block after the startRecording call
        if ($result['success']) {
            // Update scheduler state to indicate this is a manual recording
            $schedulerStateFile = 'scheduler_state.json';

            if (file_exists($schedulerStateFile)) {
                $state = json_decode(file_get_contents($schedulerStateFile), true) ?: [];

                // Update the state to indicate this is a manual recording
                $state['current_schedule_id'] = null;
                $state['last_action'] = 'manual_start';
                $state['last_action_time'] = time();

                // Save the updated state
                file_put_contents($schedulerStateFile, json_encode($state, JSON_PRETTY_PRINT));
                debug_log("Manual recording started - updated scheduler state");
            }
        }

        if (!$result['success']) {
            // You could add error handling here if needed
            debug_log("Failed to start recording: " . ($result['message'] ?? 'Unknown error'));
        }
    }

    // Stop recording - using FFmpegService
    if (isset($_POST['stop'])) {
        $result = $ffmpegService->stopRecording(
            $_SESSION['username'],
            $activityLogger
        );

        // Add this code block after the stopRecording call
        if ($result['success']) {
            // Update scheduler state
            $schedulerStateFile = 'scheduler_state.json';

            if (file_exists($schedulerStateFile)) {
                $state = json_decode(file_get_contents($schedulerStateFile), true) ?: [];

                // Update the state
                $state['current_schedule_id'] = null;
                $state['last_action'] = 'manual_stop';
                $state['last_action_time'] = time();

                // Save the updated state
                file_put_contents($schedulerStateFile, json_encode($state, JSON_PRETTY_PRINT));
                debug_log("Manual recording stopped - updated scheduler state");
            }
        }

        if (!$result['success']) {
            // Error handling
            debug_log("Failed to stop recording: " . ($result['message'] ?? 'Unknown error'));
        }
    }

    // Delete recording
    if (isset($_GET['delete'])) {
        $fileToDelete = $_GET['delete'];
        if (strpos($fileToDelete, $recordingsDir) === 0 && file_exists($fileToDelete)) {
            // Remove note for this file
            $notesFile = 'recording_notes.json';
            if (file_exists($notesFile)) {
                $notes = json_decode(file_get_contents($notesFile), true) ?? [];
                $baseFileName = basename($fileToDelete);

                // Remove the specific note
                if (isset($notes[$baseFileName])) {
                    unset($notes[$baseFileName]);
                    file_put_contents($notesFile, json_encode($notes));
                }
            }

            // Delete the recording using FFmpegService
            $ffmpegService->deleteRecording(
                $fileToDelete,
                $_SESSION['username'],
                $activityLogger
            );

            // Update change timestamp for all clients
            $ffmpegService->updateChangeTimestamp();
        }
    }
}

// Get all recordings
$recordings = glob($recordingsDir . '/*.mp4');
rsort($recordings);

// Check recording status - using FFmpegService
$recordingActive = $ffmpegService->isRecordingActive();
$recordingStart = $ffmpegService->getRecordingStartTime();

// Update live stream URL
$formattedStreamUrl = formatVLCStreamUrl($settings['live_stream_url']);

// Include the HTML template
include 'templates/header.php';

if (!isAuthenticated()) {
    include 'templates/login.php';
} elseif (isset($_SESSION['show_landing']) && $_SESSION['show_landing']) {
    include 'templates/landing.php';
} else {
    include 'templates/main.php';
}

// Add the about modal for ALL authenticated users
if (isAuthenticated()) {
    echo renderAboutModal();
}

if (isAdmin()) {
    echo renderSettingsModal($settings);
    echo renderUserManagementModal($userManager->getUsers(), $_SESSION['username']);
    include 'templates/delete_modal.php';
    include 'templates/activity_log_modal.php';

    // Remove the about modal from here since we now render it for all users
    // echo renderAboutModal();

    // Add scheduler modals if enabled
    if (isset($settings['enable_scheduler']) && $settings['enable_scheduler']) {
        include 'templates/schedule_modal.php';
    }
}

include 'templates/footer.php';

// Add scheduler JavaScript if enabled (after footer.php is included)
if (isAdmin() && isset($settings['enable_scheduler']) && $settings['enable_scheduler']) {
    echo '<script src="assets/js/recording_schedule.js"></script>';
}

// Add JavaScript to handle the about button click for all users
if (isAuthenticated()) {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const aboutInfoButton = document.getElementById("aboutInfoButton");
        if (aboutInfoButton) {
            aboutInfoButton.addEventListener("click", function() {
                const aboutModal = new bootstrap.Modal(document.getElementById("aboutModal"));
                aboutModal.show();
            });
        }
    });
    </script>';
}
?>
