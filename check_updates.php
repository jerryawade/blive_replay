// check_updates.php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simple function to get content hash
function getContentHash() {
    $hash = '';

    // Get recordings list with timestamps
    $recordings = glob('recordings/*.mp4');
    foreach ($recordings as $file) {
        $hash .= basename($file) . ':' . filemtime($file) . ';';
    }

    // Add recording status
    $hash .= 'recording:' . (file_exists('ffmpeg_pid.txt') ? '1' : '0');

    // Add notes file modification time if it exists
    if (file_exists('json/recording_notes.json')) {
        $hash .= filemtime('json/recording_notes.json');
    }

    return md5($hash);
}

// Return current hash and status
header('Content-Type: application/json');
echo json_encode([
    'hash' => getContentHash()
]);
?>
