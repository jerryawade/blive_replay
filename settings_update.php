<?php
session_start();
header('Content-Type: application/json');

require_once 'settings.php';
require_once 'logging.php';

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if update request is valid
if (!isset($_POST['update_settings'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Initialize components
    $settingsManager = new SettingsManager();
    
    // Process boolean checkboxes
    $booleanSettings = [
        'show_recordings',
        'show_livestream',
        'allow_vlc',
        'allow_m3u',
        'allow_mp4',
        'enable_scheduler',
        'use_redundant_recording'
    ];
    
    foreach ($booleanSettings as $setting) {
        $_POST[$setting] = isset($_POST[$setting]) && ($_POST[$setting] === '1' || $_POST[$setting] === 'on');
    }
    
    // Prepare new settings
    $newSettings = [
        'server_url' => rtrim($_POST['server_url'], '/'),
        'live_stream_url' => $_POST['live_stream_url'],
        'srt_url' => $_POST['srt_url'],
        'srt_url_secondary' => $_POST['srt_url_secondary'] ?? '',
        'use_redundant_recording' => $_POST['use_redundant_recording'] ?? false,
        'redundant_recording_strategy' => $_POST['redundant_recording_strategy'] ?? 'auto',
        'show_recordings' => $_POST['show_recordings'],
        'show_livestream' => $_POST['show_livestream'],
        'allow_vlc' => $_POST['allow_vlc'],
        'allow_m3u' => $_POST['allow_m3u'],
        'allow_mp4' => $_POST['allow_mp4'],
        'vlc_webpage_url' => $_POST['vlc_webpage_url'],
        'timezone' => $_POST['timezone'],
        'enable_scheduler' => $_POST['enable_scheduler'],
        'scheduler_notification_email' => $_POST['scheduler_notification_email'] ?? ''
    ];
    
    // Update settings
    $settingsManager->updateSettings($newSettings, $_SESSION['username']);
    
    // Determine if a page reload is required
    // Certain settings changes might need a full page reload to take effect
    $reloadRequired = true;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Settings updated successfully',
        'reload' => $reloadRequired
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating settings: ' . $e->getMessage()
    ]);
}
exit;
