<?php
/**
 * API Service for BLIVE RePlay
 *
 * Provides API endpoints for external devices like Arduino to access system status
 * Uses API key authentication for security
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Required components
require_once 'settings.php';
require_once 'logging.php';
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Check if authentication is required
if (!isset($_GET['api_key']) || empty($_GET['api_key'])) {
    sendResponse(['error' => 'API key is required'], 401);
    exit;
}

// Validate API key
$apiKey = $_GET['api_key'];
$apiSettings = $settings['api_settings'] ?? [];
$apiEnabled = $apiSettings['api_enabled'] ?? false;
$validApiKey = $apiSettings['api_key'] ?? '';

if (!$apiEnabled) {
    sendResponse(['error' => 'API is disabled'], 403);
    exit;
}

if ($apiKey !== $validApiKey) {
    sendResponse(['error' => 'Invalid API key'], 401);
    exit;
}

// Check IP restrictions if configured
$allowedIPs = $apiSettings['api_allowed_ips'] ?? [];
if (!empty($allowedIPs)) {
    $clientIP = $_SERVER['REMOTE_ADDR'];
    if (!in_array($clientIP, $allowedIPs)) {
        sendResponse(['error' => 'Access denied from this IP address'], 403);
        exit;
    }
}

// Process the API request
$endpoint = $_GET['endpoint'] ?? 'status';

switch ($endpoint) {
    case 'status':
        getRecordingStatus();
        break;

    case 'info':
        getSystemInfo();
        break;

    case 'recordings':
        getRecentRecordings();
        break;

    case 'schedule':
        getScheduledRecordings();
        break;

    case 'storage':
        getStorageInfo();
        break;

    case 'health':
        getStreamHealth();
        break;

    case 'logs':
        getRecentLogs();
        break;

    case 'activity':
        getUserActivity();
        break;

    case 'auth_test':
        sendResponse(['success' => true, 'message' => 'API key is valid', 'timestamp' => time()]);
        break;

    case 'start_recording':
        controlRecording('start');
        break;

    case 'stop_recording':
        controlRecording('stop');
        break;

    case 'register_device':
        registerDevice();
        break;

    case 'thumbnails':
        getThumbnails();
        break;

    case 'generate_thumbnail':
        generateThumbnail();
        break;

    default:
        sendResponse(['error' => 'Unknown endpoint'], 404);
        break;
}

/**
 * Get current recording status
 */
function getRecordingStatus() {
    $isRecording = file_exists('ffmpeg_pid.txt');
    $recordingStart = file_exists('recording_start.txt') ? (int)file_get_contents('recording_start.txt') : 0;

    $response = [
        'recording_active' => $isRecording,
        'timestamp' => time()
    ];

    if ($isRecording && $recordingStart > 0) {
        $response['recording_start'] = $recordingStart;
        $response['duration'] = time() - $recordingStart;
        $response['duration_formatted'] = formatDuration(time() - $recordingStart);

        if (file_exists('current_recording.txt')) {
            $response['filename'] = basename(file_get_contents('current_recording.txt'));
        }
    }

    sendResponse($response);
}

/**
 * Get system information
 */
function getSystemInfo() {
    global $settings;

    $totalRecordings = 0;
    $recordingsDir = 'recordings';
    if (is_dir($recordingsDir)) {
        $totalRecordings = count(glob("$recordingsDir/*.mp4"));
    }

    // Get PHP and FFmpeg versions
    $phpVersion = phpversion();
    $ffmpegVersion = trim(shell_exec('ffmpeg -version | head -n 1') ?: 'Unknown');

    // Get system uptime if available
    $uptime = 'Unknown';
    if (function_exists('shell_exec')) {
        $uptime = trim(shell_exec('uptime -p') ?: 'Unknown');
    }

    $response = [
        'version' => '1.6.0',
        'system_time' => date('Y-m-d H:i:s'),
        'timezone' => $settings['timezone'] ?? 'America/Chicago',
        'total_recordings' => $totalRecordings,
        'scheduler_enabled' => $settings['enable_scheduler'] ?? false,
        'php_version' => $phpVersion,
        'ffmpeg_version' => $ffmpegVersion,
        'uptime' => $uptime,
        'timestamp' => time()
    ];

    sendResponse($response);
}

/**
 * Get list of recent recordings
 */
function getRecentRecordings() {
    $recordingsDir = 'recordings';
    $recordings = [];
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

    // Cap limit to reasonable value
    if ($limit <= 0 || $limit > 100) {
        $limit = 20;
    }

    if (is_dir($recordingsDir)) {
        $files = glob("$recordingsDir/*.mp4");
        rsort($files); // Sort by newest first

        // Limit to the requested number of most recent recordings
        $files = array_slice($files, 0, $limit);

        foreach ($files as $file) {
            $fileName = basename($file);
            $fileSize = filesize($file);
            $modTime = filemtime($file);

            // Get video duration if FFmpegService is available
            $duration = "Unknown";
            if (file_exists('FFmpegService.php')) {
                require_once 'FFmpegService.php';
                $ffmpeg = new FFmpegService();
                $duration = $ffmpeg->getVideoDuration($file);
            }

            // Get any notes for this recording
            $note = '';
            $notesFile = 'json/recording_notes.json';
            if (file_exists($notesFile)) {
                $notes = json_decode(file_get_contents($notesFile), true) ?? [];
                $note = $notes[$fileName] ?? '';
            }

            $recordings[] = [
                'filename' => $fileName,
                'size' => $fileSize,
                'size_formatted' => formatFileSize($fileSize),
                'date' => date('Y-m-d H:i:s', $modTime),
                'timestamp' => $modTime,
                'duration' => $duration,
                'note' => $note
            ];
        }
    }

    sendResponse([
        'success' => true,
        'count' => count($recordings),
        'recordings' => $recordings,
        'timestamp' => time()
    ]);
}

/**
 * Get scheduled recordings
 */
function getScheduledRecordings() {
    global $settings;

    // Check if scheduler is enabled
    $schedulerEnabled = $settings['enable_scheduler'] ?? false;

    if (!$schedulerEnabled) {
        sendResponse([
            'success' => true,
            'enabled' => false,
            'message' => 'Scheduler is disabled',
            'timestamp' => time()
        ]);
        return;
    }

    // Get scheduler state
    $stateFile = 'json/scheduler_state.json';
    $state = [];
    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true) ?: [];
    }

    // Get schedules
    $schedulesFile = 'json/recording_schedules.json';
    $schedules = [];
    $nextSchedule = null;

    if (file_exists($schedulesFile)) {
        $schedulesData = json_decode(file_get_contents($schedulesFile), true) ?: [];

        // Only return enabled schedules
        foreach ($schedulesData as $schedule) {
            if (isset($schedule['enabled']) && $schedule['enabled']) {
                $schedules[] = $schedule;
            }
        }

        // Find next scheduled recording
        $now = new DateTime();
        $closestStart = PHP_INT_MAX;

        foreach ($schedules as $schedule) {
            $nextRun = calculateNextRun($schedule, $now);
            if ($nextRun && $nextRun < $closestStart) {
                $closestStart = $nextRun;
                $nextSchedule = $schedule;
                $nextSchedule['next_run'] = date('Y-m-d H:i:s', $nextRun);
                $nextSchedule['next_run_timestamp'] = $nextRun;
                $nextSchedule['time_until'] = formatDuration($nextRun - time());
            }
        }
    }

    sendResponse([
        'success' => true,
        'enabled' => $schedulerEnabled,
        'current_recording' => $state['current_schedule_id'] ?? null,
        'last_action' => $state['last_action'] ?? null,
        'last_action_time' => isset($state['last_action_time']) ? date('Y-m-d H:i:s', $state['last_action_time']) : null,
        'next_schedule' => $nextSchedule,
        'schedules_count' => count($schedules),
        'schedules' => $schedules,
        'timestamp' => time()
    ]);
}

/**
 * Get disk storage information
 */
function getStorageInfo() {
    $recordingsDir = 'recordings';

    // Get directory size
    $totalSize = 0;
    $fileCount = 0;

    if (is_dir($recordingsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($recordingsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
                $fileCount++;
            }
        }
    }

    // Get disk space information
    $diskTotal = disk_total_space($recordingsDir);
    $diskFree = disk_free_space($recordingsDir);
    $diskUsed = $diskTotal - $diskFree;

    sendResponse([
        'success' => true,
        'recordings_count' => $fileCount,
        'recordings_size' => $totalSize,
        'recordings_size_formatted' => formatFileSize($totalSize),
        'disk_total' => $diskTotal,
        'disk_total_formatted' => formatFileSize($diskTotal),
        'disk_used' => $diskUsed,
        'disk_used_formatted' => formatFileSize($diskUsed),
        'disk_free' => $diskFree,
        'disk_free_formatted' => formatFileSize($diskFree),
        'disk_used_percent' => round(($diskUsed / $diskTotal) * 100, 2),
        'timestamp' => time()
    ]);
}

/**
 * Get stream health information
 */
function getStreamHealth() {
    $statusFile = 'json/stream_status.json';
    $streamHealth = [
        'active' => false,
        'message' => 'Stream status unavailable',
        'last_check' => 0,
        'last_success' => null
    ];

    if (file_exists($statusFile)) {
        $streamHealth = json_decode(file_get_contents($statusFile), true) ?: $streamHealth;
    }

    // Get additional health metrics if recording is active
    $additionalMetrics = [];
    if (file_exists('ffmpeg_pid.txt') && file_exists('FFmpegService.php')) {
        require_once 'FFmpegService.php';
        $ffmpeg = new FFmpegService();

        // Check if the healthCheck method exists and use it
        if (method_exists($ffmpeg, 'checkRecordingHealth')) {
            $healthStatus = $ffmpeg->checkRecordingHealth();
            $additionalMetrics = $healthStatus;
        }
    }

    sendResponse([
        'success' => true,
        'stream_active' => $streamHealth['active'] ?? false,
        'stream_message' => $streamHealth['message'] ?? 'Unknown status',
        'last_check' => $streamHealth['last_check'] ?? 0,
        'last_check_formatted' => isset($streamHealth['last_check']) ? date('Y-m-d H:i:s', $streamHealth['last_check']) : 'Never',
        'last_success' => $streamHealth['last_success'] ?? null,
        'last_success_formatted' => isset($streamHealth['last_success']) ? date('Y-m-d H:i:s', $streamHealth['last_success']) : 'Never',
        'health_metrics' => $additionalMetrics,
        'timestamp' => time()
    ]);
}

/**
 * Get recent system logs
 */
function getRecentLogs() {
    // Check log type from query parameter
    $logType = isset($_GET['type']) ? $_GET['type'] : 'ffmpeg';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

    // Cap limit to reasonable value
    if ($limit <= 0 || $limit > 500) {
        $limit = 50;
    }

    // Define available log types and their files
    $logFiles = [
        'ffmpeg' => 'logs/ffmpeg.log',
        'scheduler' => 'logs/scheduler.log',
        'stream' => 'logs/stream_url_check.log',
        'email' => 'logs/email.log',
        'debug' => 'logs/debug.log',
        'user' => 'logs/user_activity.log'
    ];

    // Check if requested log type is valid
    if (!isset($logFiles[$logType])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid log type. Available types: ' . implode(', ', array_keys($logFiles)),
            'timestamp' => time()
        ]);
        return;
    }

    $logFile = $logFiles[$logType];
    $logEntries = [];

    if (file_exists($logFile)) {
        // Read the last N lines from the log file
        $lines = file($logFile);
        $lines = array_slice($lines, -$limit);

        // Handle user activity log specially (JSON format)
        if ($logType === 'user') {
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $logEntries[] = $entry;
                }
            }
        } else {
            $logEntries = $lines;
        }
    }

    sendResponse([
        'success' => true,
        'log_type' => $logType,
        'count' => count($logEntries),
        'entries' => $logEntries,
        'timestamp' => time()
    ]);
}

/**
 * Get user activity statistics
 */
function getUserActivity() {
    $timeRange = isset($_GET['range']) ? $_GET['range'] : 'week';

    // Define allowed time ranges
    $allowedRanges = ['day', 'week', 'month', 'year'];
    if (!in_array($timeRange, $allowedRanges)) {
        $timeRange = 'week';
    }

    // Initialize activity counts
    $activity = [
        'livestream_views' => 0,
        'video_plays' => 0,
        'by_user' => []
    ];

    // Get activity from archive files
    $viewsArchiveFile = 'logs/stream_views.log';
    $playsArchiveFile = 'logs/video_plays.log';
    $activityLogFile = 'logs/user_activity.log';

    // Set cutoff date based on range
    $now = new DateTime();
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

    $cutoffTimestamp = $cutoffDate->getTimestamp();

    // Process archive files if they exist
    $files = [
        'livestream_views' => $viewsArchiveFile,
        'video_plays' => $playsArchiveFile
    ];

    foreach ($files as $type => $file) {
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $line) {
                $parts = explode(',', $line, 3);
                if (count($parts) >= 2) {
                    $timestamp = strtotime($parts[0]);
                    $username = $parts[1];

                    if ($timestamp >= $cutoffTimestamp) {
                        $activity[$type]++;

                        if (!isset($activity['by_user'][$username])) {
                            $activity['by_user'][$username] = [
                                'livestream_views' => 0,
                                'video_plays' => 0
                            ];
                        }

                        $activity['by_user'][$username][$type]++;
                    }
                }
            }
        }
    }

    // Process main activity log for recent entries
    if (file_exists($activityLogFile)) {
        $lines = file($activityLogFile, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && isset($entry['timestamp']) && isset($entry['action'])) {
                $timestamp = strtotime($entry['timestamp']);

                if ($timestamp >= $cutoffTimestamp) {
                    if ($entry['action'] === 'livestream_click') {
                        $activity['livestream_views']++;

                        if (!isset($activity['by_user'][$entry['username']])) {
                            $activity['by_user'][$entry['username']] = [
                                'livestream_views' => 0,
                                'video_plays' => 0
                            ];
                        }

                        $activity['by_user'][$entry['username']]['livestream_views']++;
                    } else if ($entry['action'] === 'played_vlc') {
                        $activity['video_plays']++;

                        if (!isset($activity['by_user'][$entry['username']])) {
                            $activity['by_user'][$entry['username']] = [
                                'livestream_views' => 0,
                                'video_plays' => 0
                            ];
                        }

                        $activity['by_user'][$entry['username']]['video_plays']++;
                    }
                }
            }
        }
    }

    sendResponse([
        'success' => true,
        'time_range' => $timeRange,
        'period_start' => date('Y-m-d H:i:s', $cutoffTimestamp),
        'period_end' => date('Y-m-d H:i:s'),
        'total_livestream_views' => $activity['livestream_views'],
        'total_video_plays' => $activity['video_plays'],
        'by_user' => $activity['by_user'],
        'timestamp' => time()
    ]);
}

/**
 * Control recording (start/stop)
 */
function controlRecording($action) {
    global $settings;

    // Extra security: Check if control API is enabled
    $apiSettings = $settings['api_settings'] ?? [];
    $controlEnabled = $apiSettings['enable_control'] ?? false;

    if (!$controlEnabled) {
        sendResponse(['error' => 'Recording control via API is disabled'], 403);
        exit;
    }

    require_once 'FFmpegService.php';
    require_once 'logging.php';

    $ffmpegService = new FFmpegService();
    $activityLogger = new ActivityLogger();

    switch ($action) {
        case 'start':
            // Check if already recording
            if ($ffmpegService->isRecordingActive()) {
                sendResponse([
                    'success' => false,
                    'message' => 'Recording is already in progress',
                    'timestamp' => time()
                ]);
                return;
            }

            $result = $ffmpegService->startRecording(
                $settings['srt_url'],
                'api_user',
                $activityLogger
            );

            sendResponse([
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'Recording started' : 'Failed to start recording'),
                'details' => $result,
                'timestamp' => time()
            ]);
            break;

        case 'stop':
            // Check if not recording
            if (!$ffmpegService->isRecordingActive()) {
                sendResponse([
                    'success' => false,
                    'message' => 'No active recording to stop',
                    'timestamp' => time()
                ]);
                return;
            }

            $result = $ffmpegService->stopRecording(
                'api_user',
                $activityLogger
            );

            sendResponse([
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'Recording stopped' : 'Failed to stop recording'),
                'details' => $result,
                'timestamp' => time()
            ]);
            break;
    }
}

/**
 * Register an external device for API access
 */
function registerDevice() {
    // Require POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(['error' => 'This endpoint requires POST method'], 405);
        exit;
    }

    // Get registration data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $requiredFields = ['device_id', 'device_name', 'device_type'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(['error' => "Missing required field: $field"], 400);
            exit;
        }
    }

    // Create devices file if it doesn't exist
    $devicesFile = 'json/api_registered_devices.json';
    if (!is_dir('json')) {
        mkdir('json', 0777, true);
    }

    $devices = [];
    if (file_exists($devicesFile)) {
        $devices = json_decode(file_get_contents($devicesFile), true) ?: [];
    }

    // Add or update the device
    $deviceId = $data['device_id'];
    $devices[$deviceId] = [
        'id' => $deviceId,
        'name' => $data['device_name'],
        'type' => $data['device_type'],
        'description' => $data['description'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'],
        'last_seen' => date('Y-m-d H:i:s'),
        'registered' => isset($devices[$deviceId]) ? $devices[$deviceId]['registered'] : date('Y-m-d H:i:s')
    ];

    // Save updated devices list
    if (file_put_contents($devicesFile, json_encode($devices, JSON_PRETTY_PRINT))) {
        sendResponse([
            'success' => true,
            'message' => 'Device registered successfully',
            'device_id' => $deviceId,
            'timestamp' => time()
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to register device',
            'timestamp' => time()
        ], 500);
    }
}

/**
 * Get thumbnails for a specific recording
 */
function getThumbnails() {
    global $settings;

    // Check if video file is provided
    $videoFile = $_GET['file'] ?? '';
    if (empty($videoFile)) {
        sendResponse(['error' => 'Video file parameter is required'], 400);
        exit;
    }

    // Security check: Make sure the video file path is within recordings directory
    $recordingsDir = 'recordings';
    $thumbnailsDir = 'thumbnails';
    $fullPath = realpath($recordingsDir . '/' . basename($videoFile));

    if (!$fullPath || strpos($fullPath, realpath($recordingsDir)) !== 0) {
        sendResponse(['error' => 'Invalid video file path'], 400);
        exit;
    }

    // Check if file exists
    if (!file_exists($fullPath)) {
        sendResponse(['error' => 'Video file not found'], 404);
        exit;
    }

    // Get main thumbnail
    $fileBaseName = pathinfo($videoFile, PATHINFO_FILENAME);
    $mainThumbnail = $thumbnailsDir . '/' . $fileBaseName . '.jpg';

    // Get or generate temporary thumbnails
    $tempThumbnailsDir = $thumbnailsDir . '/temp';
    if (!is_dir($tempThumbnailsDir)) {
        mkdir($tempThumbnailsDir, 0777, true);
    }

    // Include FFmpegService
    require_once 'FFmpegService.php';
    $ffmpegService = new FFmpegService($recordingsDir, $thumbnailsDir);

    // Get video duration
    $durationStr = $ffmpegService->getVideoDuration($fullPath);
    $durationParts = array_map('floatval', explode(':', $durationStr));
    $durationSeconds = 0;
    if (count($durationParts) === 3) {
        $durationSeconds = $durationParts[0] * 3600 + $durationParts[1] * 60 + $durationParts[2];
    }

    if ($durationSeconds <= 0) {
        $durationSeconds = 300; // Default to 5 minutes if duration can't be determined
    }

    // Generate or get thumbnails
    $thumbnails = [];

    // Add current thumbnail
    if (file_exists($mainThumbnail)) {
        $thumbnails[] = [
            'id' => 'current',
            'url' => $mainThumbnail . '?t=' . filemtime($mainThumbnail),
            'type' => 'current'
        ];
    }

    // Generate thumbnails at various points
    $percentages = [5, 25, 50, 75, 95];
    foreach ($percentages as $percentage) {
        $timestamp = floor($durationSeconds * ($percentage / 100));
        $tempThumbnailFile = $tempThumbnailsDir . '/' . $fileBaseName . '_' . $percentage . '.jpg';

        // Check if thumbnail already exists
        if (!file_exists($tempThumbnailFile)) {
            // Format timestamp for FFmpeg
            $hours = floor($timestamp / 3600);
            $minutes = floor(($timestamp % 3600) / 60);
            $seconds = $timestamp % 60;
            $formattedTimestamp = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

            // Generate thumbnail
            $command = "ffmpeg -i " . escapeshellarg($fullPath) .
                " -ss " . escapeshellarg($formattedTimestamp) .
                " -vframes 1 -q:v 2 " .
                escapeshellarg($tempThumbnailFile) .
                " > /dev/null 2>&1";

            shell_exec($command);

            // Set proper permissions
            if (file_exists($tempThumbnailFile)) {
                chmod($tempThumbnailFile, 0644);
            }
        }

        // Add to response if generated successfully
        if (file_exists($tempThumbnailFile) && filesize($tempThumbnailFile) > 0) {
            $thumbnails[] = [
                'id' => 'position_' . $percentage,
                'url' => $tempThumbnailFile . '?t=' . filemtime($tempThumbnailFile),
                'position' => $percentage . '%',
                'type' => 'position'
            ];
        }
    }

    sendResponse([
        'success' => true,
        'video_file' => $videoFile,
        'duration' => $durationStr,
        'thumbnails' => $thumbnails,
        'timestamp' => time()
    ]);
}

/**
 * Apply a selected thumbnail to a recording
 */
function generateThumbnail() {
    global $settings;

    // Only accept POST for this endpoint
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(['error' => 'This endpoint requires POST method'], 405);
        exit;
    }

    // Check for required parameters
    $videoFile = $_POST['file'] ?? '';
    $position = $_POST['position'] ?? '';

    if (empty($videoFile)) {
        sendResponse(['error' => 'Video file parameter is required'], 400);
        exit;
    }

    // Security check: Make sure the video file path is within recordings directory
    $recordingsDir = 'recordings';
    $thumbnailsDir = 'thumbnails';
    $fullPath = realpath($recordingsDir . '/' . basename($videoFile));

    if (!$fullPath || strpos($fullPath, realpath($recordingsDir)) !== 0) {
        sendResponse(['error' => 'Invalid video file path'], 400);
        exit;
    }

    // Check if file exists
    if (!file_exists($fullPath)) {
        sendResponse(['error' => 'Video file not found'], 404);
        exit;
    }

    // Include FFmpegService
    require_once 'FFmpegService.php';
    require_once 'logging.php';
    $ffmpegService = new FFmpegService($recordingsDir, $thumbnailsDir);

    // Get target thumbnail path
    $fileBaseName = pathinfo($videoFile, PATHINFO_FILENAME);
    $thumbnailFile = $thumbnailsDir . '/' . $fileBaseName . '.jpg';

    // If position provided, handle thumbnail from specified position
    if (!empty($position)) {
        // Get video duration
        $durationStr = $ffmpegService->getVideoDuration($fullPath);
        $durationParts = array_map('floatval', explode(':', $durationStr));
        $durationSeconds = 0;
        if (count($durationParts) === 3) {
            $durationSeconds = $durationParts[0] * 3600 + $durationParts[1] * 60 + $durationParts[2];
        }

        if ($durationSeconds <= 0) {
            $durationSeconds = 300; // Default to 5 minutes if duration can't be determined
        }

        // Convert percentage to seconds
        $position = str_replace('%', '', $position);
        $position = min(max((float)$position, 0), 100);
        $timestamp = floor($durationSeconds * ($position / 100));

        // Format timestamp for FFmpeg
        $hours = floor($timestamp / 3600);
        $minutes = floor(($timestamp % 3600) / 60);
        $seconds = $timestamp % 60;
        $formattedTimestamp = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        // Generate thumbnail at specific position
        $command = "ffmpeg -i " . escapeshellarg($fullPath) .
            " -ss " . escapeshellarg($formattedTimestamp) .
            " -vframes 1 -q:v 2 " .
            escapeshellarg($thumbnailFile) .
            " > /dev/null 2>&1";

        shell_exec($command);
    }

    // Verify thumbnail was generated
    if (file_exists($thumbnailFile) && filesize($thumbnailFile) > 0) {
        // Set proper permissions
        chmod($thumbnailFile, 0644);

        // Log the thumbnail generation
        $activityLogger = new ActivityLogger();
        $activityLogger->logActivity('system', 'generated_thumbnail', basename($videoFile));

        // Update change timestamp
        $ffmpegService->updateChangeTimestamp();

        sendResponse([
            'success' => true,
            'message' => 'Thumbnail generated successfully',
            'thumbnail' => $thumbnailFile . '?t=' . time(),
            'timestamp' => time()
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to generate thumbnail',
            'timestamp' => time()
        ], 500);
    }
}

/**
 * Send JSON response to the API
 *
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function sendResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Format file size in human-readable format
 *
 * @param int $bytes Size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes) {
    if ($bytes > 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes > 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes > 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Format duration in human-readable format
 *
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf('%dm %ds', $minutes, $secs);
    } else {
        return sprintf('%ds', $secs);
    }
}

/**
 * Calculate next run time for a schedule
 */
function calculateNextRun($schedule, DateTime $now) {
    switch ($schedule['type']) {
        case 'once':
            if (!isset($schedule['date']) || !isset($schedule['startTime'])) return null;
            $scheduleDateTime = new DateTime("{$schedule['date']} {$schedule['startTime']}");
            return ($scheduleDateTime > $now) ? $scheduleDateTime->getTimestamp() : null;

        case 'daily':
            $todayStart = new DateTime("today {$schedule['startTime']}");
            return $todayStart > $now ? $todayStart->getTimestamp() : (new DateTime("tomorrow {$schedule['startTime']}"))->getTimestamp();

        case 'weekly':
            $currentDay = (int)$now->format('w');
            $daysAhead = null;

            for ($i = 0; $i < 7; $i++) {
                $checkDay = ($currentDay + $i) % 7;
                if (in_array($checkDay, $schedule['weekdays'] ?? [])) {
                    if ($i === 0) {
                        $todayStart = new DateTime("today {$schedule['startTime']}");
                        if ($todayStart > $now) {
                            $daysAhead = 0;
                            break;
                        }
                    } else {
                        $daysAhead = $i;
                        break;
                    }
                }
            }

            if ($daysAhead !== null) {
                $nextDate = clone $now;
                $nextDate->modify("+{$daysAhead} day");
                $nextDate->setTime(
                    (int)substr($schedule['startTime'], 0, 2),
                    (int)substr($schedule['startTime'], 3, 2)
                );
                return $nextDate->getTimestamp();
            }
            return null;

        case 'monthly':
            $currentDay = (int)$now->format('j');
            $currentMonth = (int)$now->format('n');
            $currentYear = (int)$now->format('Y');

            foreach ($schedule['monthdays'] ?? [] as $day) {
                if ($day > $currentDay || ($day == $currentDay && $schedule['startTime'] > $now->format('H:i'))) {
                    $nextDate = new DateTime("{$currentYear}-{$currentMonth}-{$day} {$schedule['startTime']}");
                    return $nextDate->getTimestamp();
                }
            }

            // If no days remain in current month, find first day in next month
            if (!empty($schedule['monthdays'])) {
                sort($schedule['monthdays']);
                $nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
                $nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;
                $nextDate = new DateTime("{$nextYear}-{$nextMonth}-{$schedule['monthdays'][0]} {$schedule['startTime']}");
                return $nextDate->getTimestamp();
            }

            return null;

        default:
            return null;
    }
}