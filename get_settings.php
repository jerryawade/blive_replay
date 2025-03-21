<?php
/**
 * Get settings API
 * Retrieves specific settings for JavaScript components
 */

session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'settings.php';

// Get settings manager
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// If a specific key is requested
if (isset($_GET['key'])) {
    $key = $_GET['key'];

    if (isset($settings[$key])) {
        echo json_encode([
            'success' => true,
            'key' => $key,
            'value' => $settings[$key]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Setting not found'
        ]);
    }
} else {
    // Return all settings (may want to limit this for security)
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}
exit;