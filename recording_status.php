<?php
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check recording status
$recordingActive = file_exists('ffmpeg_pid.txt');
$recordingStart = file_exists('recording_start.txt') ? (int)file_get_contents('recording_start.txt') : 0;

// Return status as JSON
echo json_encode([
    'recording_active' => $recordingActive,
    'recording_start' => $recordingStart
]);
exit;
?>
