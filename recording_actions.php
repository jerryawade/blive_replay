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

require_once 'FFmpegService.php';
require_once 'logging.php';
require_once 'settings.php';

// Initialize components
$ffmpegService = new FFmpegService();
$activityLogger = new ActivityLogger();
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Get the requested action
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'start':
        // Check if recording is already in progress
        if ($ffmpegService->isRecordingActive()) {
            echo json_encode([
                'success' => false,
                'message' => 'Recording is already in progress'
            ]);
            exit;
        }

        // Get stream URLs from settings
        $streamUrl = $settings['srt_url'] ?? '';

        // Check if primary stream URL is configured
        if (empty($streamUrl)) {
            echo json_encode([
                'success' => false,
                'message' => 'No primary stream URL configured'
            ]);
            exit;
        }

        // Check if redundant recording is enabled
        $useRedundant = isset($settings['use_redundant_recording']) && $settings['use_redundant_recording'];
        $streamUrlSecondary = $useRedundant ? ($settings['srt_url_secondary'] ?? '') : '';

        // If redundant recording is enabled but no secondary URL is set, log a warning
        if ($useRedundant && empty($streamUrlSecondary)) {
            $activityLogger->logActivity(
                $_SESSION['username'],
                'recording_warning',
                'Redundant recording enabled but no secondary URL configured'
            );

            // Fall back to non-redundant mode
            $useRedundant = false;
        }

        // Start the recording
        $result = $ffmpegService->startRecording(
            $streamUrl,
            $_SESSION['username'],
            $activityLogger,
            $streamUrlSecondary,
            $useRedundant
        );

        if ($result['success']) {
            // Update scheduler state to indicate this is a manual recording
            $schedulerStateFile = 'json/scheduler_state.json';

            if (file_exists($schedulerStateFile)) {
                $state = json_decode(file_get_contents($schedulerStateFile), true) ?: [];

                // Update the state to indicate this is a manual recording
                $state['current_schedule_id'] = null;
                $state['last_action'] = 'manual_start';
                $state['last_action_time'] = time();

                // Save the updated state
                file_put_contents($schedulerStateFile, json_encode($state, JSON_PRETTY_PRINT));
            }

            // Return success with redundant info if applicable
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'start_time' => $result['start_time'],
                'redundant' => $useRedundant,
                'primary_success' => $result['primary_success'] ?? true,
                'secondary_success' => $result['secondary_success'] ?? false
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        break;

    case 'stop':
        // Check if recording is active
        if (!$ffmpegService->isRecordingActive()) {
            echo json_encode([
                'success' => false,
                'message' => 'No active recording found'
            ]);
            exit;
        }

        // Stop the recording
        $result = $ffmpegService->stopRecording(
            $_SESSION['username'],
            $activityLogger
        );

        if ($result['success']) {
            // Update scheduler state
            $schedulerStateFile = 'json/scheduler_state.json';

            if (file_exists($schedulerStateFile)) {
                $state = json_decode(file_get_contents($schedulerStateFile), true) ?: [];

                // Update the state
                $state['current_schedule_id'] = null;
                $state['last_action'] = 'manual_stop';
                $state['last_action_time'] = time();

                // Save the updated state
                file_put_contents($schedulerStateFile, json_encode($state, JSON_PRETTY_PRINT));
            }

            // Return success with redundant info if applicable
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'filename' => $result['filename'],
                'redundant' => $result['redundant'] ?? false,
                'primary_stopped' => $result['primary_stopped'] ?? true,
                'secondary_stopped' => $result['secondary_stopped'] ?? false
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}