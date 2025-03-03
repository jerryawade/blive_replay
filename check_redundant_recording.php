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

// Initialize FFmpeg service
$ffmpegService = new FFmpegService();

// Check if recording is active at all
$recordingActive = $ffmpegService->isRecordingActive();

if (!$recordingActive) {
    echo json_encode([
        'recording_active' => false,
        'primary' => false,
        'secondary' => false
    ]);
    exit;
}

// Get redundant status
$redundantStatus = $ffmpegService->getRedundantStatus();

// If not using redundant recording
if (!$redundantStatus) {
    echo json_encode([
        'recording_active' => true,
        'primary' => true,
        'secondary' => false,
        'using_redundant' => false
    ]);
    exit;
}

// Check current stream status
$streamStatus = $ffmpegService->checkRedundantStreamStatus();

echo json_encode([
    'recording_active' => true,
    'primary' => $streamStatus['primary'],
    'secondary' => $streamStatus['secondary'],
    'using_redundant' => true,
    'started_at' => isset($redundantStatus['timestamp']) ? date('Y-m-d H:i:s', $redundantStatus['timestamp']) : null
]);
exit;