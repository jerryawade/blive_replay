<?php
/**
 * Regenerate Thumbnail
 * Provides functionality to regenerate a video thumbnail with a frame from the beginning of the video
 */

// Begin or resume session
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check for active recording
if (file_exists('ffmpeg_pid.txt')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot regenerate thumbnails while recording is active']);
    exit;
}

// Validate input
if (!isset($_POST['videoFile'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$videoFile = $_POST['videoFile'];
$thumbnailsDir = 'thumbnails';

// Security check: Make sure the video file path is within recordings directory
$cleanedVideoFile = realpath($videoFile);
$recordingsDir = realpath('recordings');

if (!$cleanedVideoFile || strpos($cleanedVideoFile, $recordingsDir) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid video file path']);
    exit;
}

// Ensure the video exists
if (!file_exists($cleanedVideoFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Video file not found']);
    exit;
}

// Include FFmpegService
require_once 'FFmpegService.php';
require_once 'logging.php';

try {
    // Initialize FFmpeg service
    $ffmpegService = new FFmpegService('recordings', $thumbnailsDir);
    $activityLogger = new ActivityLogger();

    // Get thumbnail filename
    $thumbnailFile = $thumbnailsDir . '/' . pathinfo(basename($cleanedVideoFile), PATHINFO_FILENAME) . '.jpg';

    // Regenerate the thumbnail
    $success = $ffmpegService->generateThumbnail($cleanedVideoFile, $thumbnailFile);

    if ($success) {
        // Log activity
        $activityLogger->logActivity($_SESSION['username'], 'regenerated_thumbnail', basename($cleanedVideoFile));

        echo json_encode([
            'success' => true,
            'message' => 'Thumbnail regenerated successfully',
            'thumbnail' => $thumbnailFile,
            'timestamp' => filemtime($thumbnailFile)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to regenerate thumbnail']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}