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
    $currentSettings = $settingsManager->getSettings();

    // Check if SRT URL has changed
    $srtUrlChanged = false;
    if (isset($_POST['srt_url']) && $_POST['srt_url'] !== $currentSettings['srt_url']) {
        $srtUrlChanged = true;

        // Set a session flag to notify check_stream_url.php that the URL has changed
        $_SESSION['srt_url_changed'] = true;

        // Log the change
        $logFile = 'logs/stream_url_check.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [info] SRT URL changed from '{$currentSettings['srt_url']}' to '{$_POST['srt_url']}'\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Remove existing status file to force a fresh check
        $statusFile = 'json/stream_status.json';
        if (file_exists($statusFile)) {
            unlink($statusFile);
        }

        // Remove any lock file
        $lockFile = 'json/stream_check.lock';
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    // Process boolean checkboxes
    $booleanSettings = [
        'show_recordings',
        'show_livestream',
        'allow_vlc',
        'allow_m3u',
        'allow_mp4',
        'enable_scheduler',
        'email_notifications_enabled'
    ];

    foreach ($booleanSettings as $setting) {
        $_POST[$setting] = isset($_POST[$setting]) && ($_POST[$setting] === '1' || $_POST[$setting] === 'on');
    }

// Validate email notification settings
    if ($_POST['email_notifications_enabled']) {
        // Check if notification email is provided
        if (empty($_POST['scheduler_notification_email'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Notification email is required when email notifications are enabled'
            ]);
            exit;
        }

        // Extract and validate email addresses
        $emails = array_map('trim', explode(',', $_POST['scheduler_notification_email']));
        $emails = array_filter($emails); // Remove empty entries

        if (empty($emails)) {
            echo json_encode([
                'success' => false,
                'message' => 'At least one valid email address is required'
            ]);
            exit;
        }

        // Validate all email addresses
        $validEmails = [];
        $invalidEmails = [];

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $invalidEmails[] = $email;
            }
        }

        if (empty($validEmails)) {
            echo json_encode([
                'success' => false,
                'message' => 'No valid email addresses found'
            ]);
            exit;
        }

        if (!empty($invalidEmails)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format: ' . implode(', ', $invalidEmails)
            ]);
            exit;
        }

        // Validate required SMTP fields
        $requiredSmtpFields = [
            'smtp_host' => 'SMTP Host',
            'smtp_username' => 'SMTP Username',
            'smtp_password' => 'SMTP Password'
        ];

        foreach ($requiredSmtpFields as $field => $label) {
            if (empty($_POST[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => $label . ' is required when email notifications are enabled'
                ]);
                exit;
            }
        }
    }

    // Prepare new settings
    $newSettings = [
        'server_url' => rtrim($_POST['server_url'], '/'),
        'live_stream_url' => $_POST['live_stream_url'],
        'open_webpage_for_livestream' => $_POST['open_webpage_for_livestream'] ?? false,
        'srt_url' => $_POST['srt_url'],
        'stream_check_interval' => max(1, min(60, (int)$_POST['stream_check_interval'])),
        'show_recordings' => $_POST['show_recordings'],
        'show_livestream' => $_POST['show_livestream'],
        'allow_vlc' => $_POST['allow_vlc'],
        'allow_m3u' => $_POST['allow_m3u'],
        'allow_mp4' => $_POST['allow_mp4'],
        'vlc_webpage_url' => $_POST['vlc_webpage_url'],
        'timezone' => $_POST['timezone'],
        'enable_scheduler' => $_POST['enable_scheduler'],
        'scheduler_notification_email' => $_POST['scheduler_notification_email'] ?? '',

        // Email notification settings
        'email_notifications_enabled' => $_POST['email_notifications_enabled'],
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_security' => $_POST['smtp_security'],
        'smtp_username' => $_POST['smtp_username'],
        'smtp_password' => $_POST['smtp_password'],
        'smtp_from_email' => $_POST['smtp_from_email'],
        'smtp_from_name' => $_POST['smtp_from_name'],
    ];

    // Update settings
    $settingsManager->updateSettings($newSettings, $_SESSION['username']);

    // Determine if a page reload is required
    // Certain settings changes might need a full page reload to take effect
    $reloadRequired = true;

    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully',
        'reload' => $reloadRequired,
        'srt_url_changed' => $srtUrlChanged
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating settings: ' . $e->getMessage()
    ]);
}
exit;
