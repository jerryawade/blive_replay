<?php
session_start();
header('Content-Type: application/json');

require_once 'logging.php';

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if recording is active
if (file_exists('ffmpeg_pid.txt')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot update notes while recording is active']);
    exit;
}

// Validate input
if (!isset($_POST['recording_file']) || !isset($_POST['note'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$recordingFile = $_POST['recording_file'];
$note = trim($_POST['note']);
$notesFile = 'recording_notes.json';

try {
    // Load existing notes
    $notes = [];
    if (file_exists($notesFile)) {
        $notes = json_decode(file_get_contents($notesFile), true) ?? [];
    }

    // Update note
    $notes[basename($recordingFile)] = $note;

    // Save notes
    if (file_put_contents($notesFile, json_encode($notes)) === false) {
        throw new Exception('Failed to save note');
    }

    // Update change timestamp for all clients
    file_put_contents('last_change.txt', time());

    // Log activity
    $activityLogger = new ActivityLogger();
    $activityLogger->logActivity($_SESSION['username'], 'updated_note', basename($recordingFile));

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
