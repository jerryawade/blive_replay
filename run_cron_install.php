<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['script'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$script = basename($_POST['script']); // Sanitize to prevent directory traversal
$allowedScripts = [
    'install_log_archiver_cron.sh',
    'install_scheduler_service_cron.sh',
    'install_stream_monitor_service_cron.sh'
];

if (!in_array($script, $allowedScripts)) {
    echo json_encode(['success' => false, 'message' => 'Invalid script name']);
    exit;
}

// Execute the script with sudo
$scriptPath = __DIR__ . '/' . $script; // Adjust path if scripts are elsewhere
$output = shell_exec("sudo bash {$scriptPath} 2>&1");

if (strpos($output, 'Cron job successfully installed') !== false) {
    // Get the updated cron line
    $cronLine = shell_exec("sudo crontab -l 2>/dev/null | grep '" . str_replace('install_', '', str_replace('_cron.sh', '', $script)) . "'");
    echo json_encode([
        'success' => true,
        'message' => 'Cron job installed successfully',
        'cron_line' => trim($cronLine)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to install cron job: ' . htmlspecialchars($output)
    ]);
}
?>