<?php
/**
 * recording_health.php
 * API to check the health of active recordings (primary and backup)
 */

// Start session and perform authentication check
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include necessary files
require_once 'FFmpegService.php';
require_once 'settings.php';

// Initialize services
$ffmpegService = new FFmpegService();
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Check if recording is active
if (!$ffmpegService->isAnyRecordingActive()) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'No active recording found',
        'primary' => ['status' => 'inactive'],
        'backup' => ['status' => 'inactive'],
        'combined' => 'inactive'
    ]);
    exit;
}

// Check recording health
$healthStatus = $ffmpegService->checkRecordingHealth();

// Return health data
echo json_encode([
    'success' => true,
    'primary' => $healthStatus['primary'],
    'backup' => $healthStatus['backup'],
    'combined' => $healthStatus['combined'],
    'timestamp' => time()
]);
