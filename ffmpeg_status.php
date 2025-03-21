<?php
// ffmpeg_status.php
header('Content-Type: application/json');

// Set the correct path to your ffmpeg pid file
$pidFile = 'ffmpeg_pid.txt';

// Check if the file exists
$recordingInProgress = file_exists($pidFile);

// Return the status as JSON
echo json_encode(['recording_in_progress' => $recordingInProgress]);
exit;
?>

