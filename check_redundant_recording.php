<?php
/**
 * check_redundant_recording.php
 * Returns the status of both primary and secondary recording processes
 */

session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'FFmpegService.php';
require_once 'settings.php';

// Initialize services
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();
$ffmpegService = new FFmpegService();

// Check if redundant recording is enabled in settings
$usingRedundant = isset($settings['use_redundant_recording']) &&
    ($settings['use_redundant_recording'] === true ||
        $settings['use_redundant_recording'] === '1' ||
        $settings['use_redundant_recording'] === 1);

// Check if recording is active at all
$recordingActive = $ffmpegService->isRecordingActive();

if (!$recordingActive) {
    echo json_encode([
        'recording_active' => false,
        'primary' => false,
        'secondary' => false,
        'using_redundant' => $usingRedundant
    ]);
    exit;
}

// Get redundant status
$redundantStatus = $ffmpegService->getRedundantStatus();

// If not using redundant recording or no status info available
if (!$usingRedundant || !$redundantStatus) {
    echo json_encode([
        'recording_active' => true,
        'primary' => true,
        'secondary' => false,
        'using_redundant' => false
    ]);
    exit;
}

// Check current stream status - this will verify if processes are still running
$streamStatus = $ffmpegService->checkRedundantStreamStatus();

echo json_encode([
    'recording_active' => true,
    'primary' => $streamStatus['primary'],
    'secondary' => $streamStatus['secondary'],
    'using_redundant' => true,
    'started_at' => isset($redundantStatus['timestamp']) ? date('Y-m-d H:i:s', $redundantStatus['timestamp']) : null,
    'primary_file' => isset($redundantStatus['primary_file']) ? basename($redundantStatus['primary_file']) : null,
    'secondary_file' => isset($redundantStatus['secondary_file']) ? basename($redundantStatus['secondary_file']) : null
]);
exit;