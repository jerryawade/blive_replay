<?php
/**
 * thumbnail_selector.php
 * Generates multiple thumbnails from different points in a video for selection
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
    echo json_encode(['success' => false, 'message' => 'Cannot generate thumbnails while recording is active']);
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
$tempThumbnailsDir = 'thumbnails/temp';

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

// Create temp thumbnails directory if it doesn't exist
if (!is_dir($tempThumbnailsDir)) {
    mkdir($tempThumbnailsDir, 0777, true);
}

// Include FFmpegService
require_once 'FFmpegService.php';
require_once 'logging.php';

// Define action type
$action = $_POST['action'] ?? 'generate';

try {
    // Initialize FFmpeg service
    $ffmpegService = new FFmpegService('recordings', $thumbnailsDir);
    $activityLogger = new ActivityLogger();
    
    // Get video duration to calculate thumbnail positions
    $durationStr = $ffmpegService->getVideoDuration($cleanedVideoFile);
    
    // Parse duration string (HH:MM:SS.ms) to seconds
    $durationParts = array_map('floatval', explode(':', $durationStr));
    $durationSeconds = 0;
    if (count($durationParts) === 3) {
        $durationSeconds = $durationParts[0] * 3600 + $durationParts[1] * 60 + $durationParts[2];
    }
    
    // If duration is unknown or too short, use default timestamps
    if ($durationSeconds <= 0) {
        $durationSeconds = 300; // Default to 5 minutes if duration can't be determined
    }
    
    if ($action === 'generate') {
        // Generate 5 thumbnails at different points in the video
        $thumbnails = [];
        $fileBaseName = pathinfo(basename($cleanedVideoFile), PATHINFO_FILENAME);
        
        // Add recording.png as the first option
        $thumbnails[] = [
            'id' => 'recording_png',
            'src' => 'assets/imgs/recording.png',
            'label' => 'Default Recording Icon',
            'type' => 'static'
        ];
        
        // Generate thumbnails at 1%, 2%, 3%, 4%, and 5% of the video
        $percentages = [1, 2, 3, 4, 5];
        
        foreach ($percentages as $index => $percentage) {
            $timestamp = floor($durationSeconds * ($percentage / 100));
            
            // Format timestamp for FFmpeg (HH:MM:SS)
            $hours = floor($timestamp / 3600);
            $minutes = floor(($timestamp % 3600) / 60);
            $seconds = $timestamp % 60;
            $formattedTimestamp = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            
            // Create unique temp thumbnail filename
            $tempThumbnailFile = $tempThumbnailsDir . '/' . $fileBaseName . '_' . $percentage . '.jpg';
            
            // Generate the thumbnail
            $command = "ffmpeg -i " . escapeshellarg($cleanedVideoFile) .
                " -ss " . escapeshellarg($formattedTimestamp) .
                " -vframes 1 " .
                " -q:v 2 " .
                escapeshellarg($tempThumbnailFile) .
                " > /dev/null 2>&1";
            
            shell_exec($command);
            
            // Check if thumbnail was generated
            if (file_exists($tempThumbnailFile) && filesize($tempThumbnailFile) > 0) {
                // Set file permissions to ensure web accessibility
                chmod($tempThumbnailFile, 0644);
                
                $thumbnails[] = [
                    'id' => 'thumb_' . $percentage,
                    'src' => $tempThumbnailFile . '?t=' . time(), // Add timestamp to prevent caching
                    'label' => 'At ' . $formattedTimestamp . ' (' . $percentage . '%)',
                    'type' => 'video',
                    'timestamp' => $formattedTimestamp
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Thumbnails generated successfully',
            'thumbnails' => $thumbnails,
            'videoFile' => $cleanedVideoFile,
            'currentThumbnail' => $thumbnailsDir . '/' . $fileBaseName . '.jpg'
        ]);
        
    } elseif ($action === 'select') {
        // Handle thumbnail selection
        if (!isset($_POST['selected']) || !isset($_POST['videoFile'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        $selected = $_POST['selected'];
        $videoFile = $_POST['videoFile'];
        $fileBaseName = pathinfo(basename($videoFile), PATHINFO_FILENAME);
        $targetThumbnail = $thumbnailsDir . '/' . $fileBaseName . '.jpg';
        
        if ($selected === 'recording_png') {
            // Copy the static recording.png file
            copy('assets/imgs/recording.png', $targetThumbnail);
            $success = true;
        } else {
            // Extract the percentage from the selected ID (format: thumb_XX)
            $parts = explode('_', $selected);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                $percentage = (int)$parts[1];
                $tempThumbnailFile = $tempThumbnailsDir . '/' . $fileBaseName . '_' . $percentage . '.jpg';
                
                if (file_exists($tempThumbnailFile)) {
                    // Copy the selected thumbnail to the target location
                    $success = copy($tempThumbnailFile, $targetThumbnail);
                } else {
                    $success = false;
                }
            } else {
                $success = false;
            }
        }
        
        if ($success) {
            // Log activity
            $activityLogger->logActivity($_SESSION['username'], 'selected_thumbnail', basename($videoFile));

            // Update the change timestamp to notify all clients
            $ffmpegService->updateChangeTimestamp();

            echo json_encode([
                'success' => true,
                'message' => 'Thumbnail selected successfully',
                'thumbnail' => $targetThumbnail . '?t=' . time()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to select thumbnail']);
        }
        
    } elseif ($action === 'cleanup') {
        // Clean up temporary thumbnails
        $fileBaseName = pathinfo(basename($cleanedVideoFile), PATHINFO_FILENAME);
        
        // Get all temporary thumbnails for this video
        $tempThumbnails = glob($tempThumbnailsDir . '/' . $fileBaseName . '_*.jpg');
        
        // Delete each temporary thumbnail
        foreach ($tempThumbnails as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Temporary thumbnails cleaned up'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
