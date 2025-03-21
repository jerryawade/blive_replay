<?php
/**
 * EmailService.php
 *
 * A service class to handle all email-related operations for the BLIVE RePlay application.
 * Uses PHPMailer for reliable email delivery with SMTP support.
 */

// Include PHPMailer classes - make sure these are installed via Composer
// If not installed, you can download them from https://github.com/PHPMailer/PHPMailer
// and place them in a 'vendor/phpmailer' directory
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class EmailService
{
    // Settings properties
    private $settings;

    // Logger
    private $logFile = 'logs/email.log';

    /**
     * Constructor
     *
     * @param array $settings Application settings
     */
    public function __construct(?array $settings = null)
    {
        // If no settings provided, load them from the settings manager
        if ($settings === null) {
            require_once 'settings.php';
            $settingsManager = new SettingsManager();
            $settings = $settingsManager->getSettings();
        }

        // Ensure settings is always an array
        $this->settings = $settings ?? [];

        // Set timezone from settings
        date_default_timezone_set($this->settings['timezone'] ?? 'America/Chicago');

        // Ensure log directory exists
        $this->ensureLogDirectoryExists();
    }

    /**
     * Log email-related messages
     *
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     */
    public function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Ensure the logs directory exists
     */
    private function ensureLogDirectoryExists(): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Include PHPMailer library
     *
     * @return bool True if successful
     */
    private function includePHPMailer(): bool
    {
        // Check if PHPMailer is available through Composer autoload
        if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
            return true;
        }

        // Check for PHPMailer in vendor directory
        $phpmailerPaths = [
            'vendor/phpmailer/phpmailer/src/PHPMailer.php',
            'vendor/phpmailer/phpmailer/src/SMTP.php',
            'vendor/phpmailer/phpmailer/src/Exception.php'
        ];

        $allFound = true;
        foreach ($phpmailerPaths as $path) {
            if (!file_exists($path)) {
                $allFound = false;
                $this->log("PHPMailer file not found: $path", 'error');
            }
        }

        if ($allFound) {
            require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
            return true;
        }

        // Fallback - try to get PHPMailer from a local directory
        // This assumes you've downloaded PHPMailer manually and placed it in the 'phpmailer' directory
        $localPaths = [
            'phpmailer/src/PHPMailer.php',
            'phpmailer/src/SMTP.php',
            'phpmailer/src/Exception.php'
        ];

        $allFound = true;
        foreach ($localPaths as $path) {
            if (!file_exists($path)) {
                $allFound = false;
                $this->log("PHPMailer file not found in local directory: $path", 'error');
            }
        }

        if ($allFound) {
            require_once 'phpmailer/src/PHPMailer.php';
            require_once 'phpmailer/src/SMTP.php';
            require_once 'phpmailer/src/Exception.php';
            return true;
        }

        $this->log("PHPMailer library not found. Cannot send emails.", 'error');
        return false;
    }

    /**
     * Create and configure a PHPMailer instance
     *
     * @return PHPMailer|null PHPMailer instance or null if configuration fails
     */
    private function createMailer()
    {
        if (!$this->includePHPMailer()) {
            return null;
        }

        try {
            $mail = new PHPMailer(true);

            // Check if notifications are enabled
            if (!isset($this->settings['email_notifications_enabled']) ||
                !$this->settings['email_notifications_enabled']) {
                $this->log("Email notifications are disabled in settings", 'warning');
                return null;
            }

            // Check required SMTP settings
            if (empty($this->settings['smtp_host']) ||
                empty($this->settings['smtp_username']) ||
                empty($this->settings['smtp_password'])) {
                $this->log("SMTP settings incomplete: host, username, or password missing", 'error');
                return null;
            }

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            $mail->Port = $this->settings['smtp_port'] ?? '587';

            // Security settings
            $security = $this->settings['smtp_security'] ?? 'tls';
            if ($security === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($security === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            // Authentication
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];

            // Debug level
            // 0 = off, 1 = client messages, 2 = client & server messages
            $mail->SMTPDebug = 0;

            // Sender
            $mail->setFrom(
                $this->settings['smtp_from_email'] ?: $this->settings['smtp_username'],
                $this->settings['smtp_from_name'] ?? 'RePlay System'
            );

            // Character encoding
            $mail->CharSet = 'UTF-8';

            return $mail;

        } catch (Exception $e) {
            $this->log("Error creating PHPMailer instance: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Send an email
     *
     * @param string|array $to Recipient email address(es) - can be a string with comma-separated emails or an array
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param string $plainText Plain text alternative
     * @param array $attachments Array of attachment file paths
     * @return array Result with success status and message
     */
    public function sendEmail($to, string $subject, string $message,
                              string $plainText = '', array $attachments = []): array
    {
        // Convert string email addresses to array
        if (is_string($to)) {
            $to = array_map('trim', explode(',', $to));
        }

        // Filter out any empty values
        $to = array_filter($to);

        if (empty($to)) {
            $this->log("No valid recipient email addresses provided", 'error');
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses provided'
            ];
        }

        $this->log("Preparing to send email to: " . implode(', ', $to));

        // Validate recipients
        $validRecipients = [];
        $invalidRecipients = [];

        foreach ($to as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validRecipients[] = $email;
            } else {
                $invalidRecipients[] = $email;
            }
        }

        if (empty($validRecipients)) {
            $this->log("No valid email addresses found: " . implode(', ', $invalidRecipients), 'error');
            return [
                'success' => false,
                'message' => 'No valid recipient email addresses'
            ];
        }

        if (!empty($invalidRecipients)) {
            $this->log("Some invalid email addresses were skipped: " . implode(', ', $invalidRecipients), 'warning');
        }

        // Get PHPMailer instance
        $mail = $this->createMailer();
        if ($mail === null) {
            return [
                'success' => false,
                'message' => 'Failed to initialize email system'
            ];
        }

        try {
            // Add all valid recipients
            foreach ($validRecipients as $email) {
                $mail->addAddress($email);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            // Set plain text alternative if provided
            if (!empty($plainText)) {
                $mail->AltBody = $plainText;
            } else {
                // Create plain text version from HTML
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
            }

            // Add attachments if any
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                } else {
                    $this->log("Attachment file not found: $attachment", 'warning');
                }
            }

            // Send the email
            $mail->send();

            $recipientList = implode(', ', $validRecipients);
            $this->log("Email sent successfully to: $recipientList");
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'recipients' => $validRecipients
            ];

        } catch (Exception $e) {
            $errorMessage = "Email sending failed: " . $mail->ErrorInfo;
            $this->log($errorMessage, 'error');

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }

    /**
     * Get current formatted time
     *
     * @return string Current time in Y-m-d H:i:s format
     */
    private function getCurrentTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Send a test email
     *
     * @param string $to Recipient email address
     * @return array Result with success status and message
     */
    public function sendTestEmail(string $to): array
    {
        $subject = 'Test Email from RePlay System';

        $html = <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #3ea9de; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>RePlay System Test Email</h2>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>This is a test email from your BLIVE RePlay system.</p>
            <p>If you're receiving this message, your email notification settings are working correctly!</p>
            <p>The system will now be able to send you notifications about scheduled recordings.</p>
            <p>Server time: {$this->getCurrentTime()}</p>
        </div>
        <div class="footer">
            <p>This is an automated message from the RePlay System. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $plainText = "RePlay System Test Email\n\n"
            . "Hello,\n\n"
            . "This is a test email from your BLIVE RePlay system.\n"
            . "If you're receiving this message, your email notification settings are working correctly!\n"
            . "The system will now be able to send you notifications about scheduled recordings.\n\n"
            . "Server time: {$this->getCurrentTime()}\n\n"
            . "This is an automated message from the RePlay System. Please do not reply to this email.";

        return $this->sendEmail($to, $subject, $html, $plainText);
    }

    /**
     * Send recording start notification
     *
     * @param array $recordingData Recording details
     * @return array Result with success status and message
     */
    public function sendRecordingStartNotification(array $recordingData): array
    {
        $this->log("Preparing recording start notification");

        // Get notification recipient(s)
        $to = $this->settings['scheduler_notification_email'] ?? '';
        if (empty($to)) {
            $this->log("No notification email configured, skipping notification", 'warning');
            return [
                'success' => false,
                'message' => 'No notification email configured'
            ];
        }

        // Extract recording data
        $filename = $recordingData['filename'] ?? 'Unknown';
        $startTime = $recordingData['start_time'] ?? time();
        $formattedStartTime = date('Y-m-d H:i:s', $startTime);
        $scheduleName = $recordingData['schedule_name'] ?? 'Scheduled Recording';

        $subject = "Recording Started: $scheduleName";

        $html = <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #dc3545; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
        .details { background-color: #f0f0f0; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Recording Started</h2>
        </div>
        <div class="content">
            <p>A scheduled recording has started on your RePlay system.</p>
            
            <div class="details">
                <p><strong>Schedule Name:</strong> {$scheduleName}</p>
                <p><strong>Recording File:</strong> {$filename}</p>
                <p><strong>Start Time:</strong> {$formattedStartTime}</p>
            </div>
            
            <p>The recording is now in progress. You will receive another notification when it completes.</p>
        </div>
        <div class="footer">
            <p>This is an automated message from the RePlay System. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $plainText = "Recording Started: $scheduleName\n\n"
            . "A scheduled recording has started on your RePlay system.\n\n"
            . "Schedule Name: $scheduleName\n"
            . "Recording File: $filename\n"
            . "Start Time: $formattedStartTime\n\n"
            . "The recording is now in progress. You will receive another notification when it completes.\n\n"
            . "This is an automated message from the RePlay System. Please do not reply to this email.";

        return $this->sendEmail($to, $subject, $html, $plainText);
    }

    /**
     * Send recording complete notification
     *
     * @param array $recordingData Recording details
     * @return array Result with success status and message
     */
    public function sendRecordingCompleteNotification(array $recordingData): array
    {
        $this->log("Preparing recording complete notification");

        // Get notification recipient(s)
        $to = $this->settings['scheduler_notification_email'] ?? '';
        if (empty($to)) {
            $this->log("No notification email configured, skipping notification", 'warning');
            return [
                'success' => false,
                'message' => 'No notification email configured'
            ];
        }

        // Extract recording data
        $filename = $recordingData['filename'] ?? 'Unknown';
        $startTime = $recordingData['start_time'] ?? 0;
        $endTime = $recordingData['end_time'] ?? time();
        $formattedStartTime = date('Y-m-d H:i:s', $startTime);
        $formattedEndTime = date('Y-m-d H:i:s', $endTime);
        $scheduleName = $recordingData['schedule_name'] ?? 'Scheduled Recording';

        // Calculate duration
        $duration = $endTime - $startTime;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;
        $formattedDuration = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

        // Get file size if available
        $filePath = $recordingData['full_path'] ?? '';
        $fileSize = 'Unknown';
        if (!empty($filePath) && file_exists($filePath)) {
            $sizeBytes = filesize($filePath);
            if ($sizeBytes > 1073741824) { // 1 GB
                $fileSize = number_format($sizeBytes / 1073741824, 2) . ' GB';
            } else {
                $fileSize = number_format($sizeBytes / 1048576, 2) . ' MB';
            }
        }

        $subject = "Recording Complete: $scheduleName";

        $html = <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
        .details { background-color: #f0f0f0; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Recording Complete</h2>
        </div>
        <div class="content">
            <p>A scheduled recording has completed on your RePlay system.</p>
            
            <div class="details">
                <p><strong>Schedule Name:</strong> {$scheduleName}</p>
                <p><strong>Recording File:</strong> {$filename}</p>
                <p><strong>Start Time:</strong> {$formattedStartTime}</p>
                <p><strong>End Time:</strong> {$formattedEndTime}</p>
                <p><strong>Duration:</strong> {$formattedDuration}</p>
                <p><strong>File Size:</strong> {$fileSize}</p>
            </div>
            
            <p>The recording has been saved to your RePlay system and is ready for viewing.</p>
        </div>
        <div class="footer">
            <p>This is an automated message from the RePlay System. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $plainText = "Recording Complete: $scheduleName\n\n"
            . "A scheduled recording has completed on your RePlay system.\n\n"
            . "Schedule Name: $scheduleName\n"
            . "Recording File: $filename\n"
            . "Start Time: $formattedStartTime\n"
            . "End Time: $formattedEndTime\n"
            . "Duration: $formattedDuration\n"
            . "File Size: $fileSize\n\n"
            . "The recording has been saved to your RePlay system and is ready for viewing.\n\n"
            . "This is an automated message from the RePlay System. Please do not reply to this email.";

        return $this->sendEmail($to, $subject, $html, $plainText);
    }
}
