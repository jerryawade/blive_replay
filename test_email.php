<?php
/**
 * Test Email Endpoint
 * Checks email configuration and sends a test email
 */

// Start session and include required files
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include required classes
require_once 'settings.php';
require_once 'EmailService.php';

// Download PHPMailer if it doesn't exist
function downloadPHPMailer()
{
    // Create directories if they don't exist
    if (!is_dir('phpmailer')) {
        mkdir('phpmailer', 0755);
    }
    if (!is_dir('phpmailer/src')) {
        mkdir('phpmailer/src', 0755);
    }

    // Files to download
    $files = [
        'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
        'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
        'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
    ];

    $success = true;
    foreach ($files as $filename => $url) {
        $destination = "phpmailer/src/$filename";
        if (!file_exists($destination)) {
            $content = @file_get_contents($url);
            if ($content !== false) {
                file_put_contents($destination, $content);
            } else {
                $success = false;
            }
        }
    }

    return $success;
}

try {
    // First, ensure PHPMailer is available
    if (!file_exists('phpmailer/src/PHPMailer.php')) {
        $downloaded = downloadPHPMailer();
        if (!$downloaded) {
            echo json_encode([
                'success' => false,
                'message' => 'Could not download PHPMailer library. Please install it manually.'
            ]);
            exit;
        }
    }

    // Check if notification email is provided
    if (empty($_POST['scheduler_notification_email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Notification email address is required'
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

    // Get temporary settings from form data
    $testSettings = [
        'email_notifications_enabled' => isset($_POST['email_notifications_enabled']),
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? '587',
        'smtp_security' => $_POST['smtp_security'] ?? 'tls',
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '',
        'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
        'smtp_from_name' => $_POST['smtp_from_name'] ?? 'RePlay System',
        'scheduler_notification_email' => $_POST['scheduler_notification_email'] ?? ''
    ];

    // Validate basic settings
    if (empty($testSettings['smtp_host'])) {
        echo json_encode([
            'success' => false,
            'message' => 'SMTP Host is required'
        ]);
        exit;
    }

    if (empty($testSettings['smtp_username'])) {
        echo json_encode([
            'success' => false,
            'message' => 'SMTP Username is required'
        ]);
        exit;
    }

    if (empty($testSettings['smtp_password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'SMTP Password is required'
        ]);
        exit;
    }

    // Get email to send to
    $toEmail = $_POST['scheduler_notification_email'] ?? '';

    // Initialize email service with test settings
    $emailService = new EmailService($testSettings);

    // Send test email
    $result = $emailService->sendTestEmail($toEmail);

    // Return the result
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}