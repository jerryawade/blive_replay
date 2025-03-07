<?php
// check_updates.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/**
 * Get a consistent hash representing the current state of recordings
 * 
 * @return string MD5 hash of recordings state
 */
function getContentHash() {
    $hash = '';

    // Get recordings list with timestamps
    $recordingsDir = 'recordings';
    $recordings = glob($recordingsDir . '/*.mp4');
    
    // Sort recordings to ensure consistent order
    sort($recordings);

    foreach ($recordings as $file) {
        $baseFile = basename($file);
        $hash .= $baseFile . ':' . filemtime($file) . ';';
    }

    // Add recording status
    $hash .= 'recording:' . (file_exists('ffmpeg_pid.txt') ? '1' : '0');

    // Add notes file modification time if it exists
    $notesFile = 'json/recording_notes.json';
    if (file_exists($notesFile)) {
        $hash .= 'notes:' . filemtime($notesFile);
    }

    // Add current recording file if exists
    $currentRecordingFile = 'current_recording.txt';
    if (file_exists($currentRecordingFile)) {
        $hash .= 'current:' . file_get_contents($currentRecordingFile);
    }

    return md5($hash);
}

// Return current hash and status
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode([
    'hash' => getContentHash(),
    'timestamp' => time()
]);
?>
