<?php
// Begin or resume session to maintain user authentication state
session_start();
require_once 'FFmpegService.php';
require_once 'settings.php';

// Check if user is authenticated and has admin privileges
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Get settings for timezone
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Initialize FFmpeg service
$ffmpegService = new FFmpegService();

// Get log content with newest entries first (default)
echo $ffmpegService->getFFmpegLog();
