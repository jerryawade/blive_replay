<?php
/**
 * Log Archiver Utility
 *
 * Web interface to manually trigger the log archiver and view archive statistics.
 * This should only be accessible to administrators.
 */

// Start session and check authentication
session_start();

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Include required files
require_once 'settings.php';
require_once 'activity_log_archiver.php';

// Initialize
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

$message = '';
$status = '';

// Check if we're running the archiver
if (isset($_POST['run_archiver'])) {
    try {
        $archiver = new ActivityLogArchiver();
        $archiver->processLog();
        $message = "Log archiver executed successfully!";
        $status = "success";
    } catch (Exception $e) {
        $message = "Error running log archiver: " . $e->getMessage();
        $status = "danger";
    }
}

// Get archive statistics
$mainLogFile = 'logs/user_activity.log';
$viewsArchiveFile = 'logs/stream_views.log';
$playsArchiveFile = 'logs/video_plays.log';
$processedEntriesFile = 'logs/processed_entries.txt';

$mainLogSize = file_exists($mainLogFile) ? filesize($mainLogFile) : 0;
$viewsArchiveSize = file_exists($viewsArchiveFile) ? filesize($viewsArchiveFile) : 0;
$playsArchiveSize = file_exists($playsArchiveFile) ? filesize($playsArchiveFile) : 0;
$processedEntriesCount = file_exists($processedEntriesFile) ? count(file($processedEntriesFile)) : 0;

$mainLogCount = file_exists($mainLogFile) ? count(file($mainLogFile)) : 0;
$viewsArchiveCount = file_exists($viewsArchiveFile) ? count(file($viewsArchiveFile)) : 0;
$playsArchiveCount = file_exists($playsArchiveFile) ? count(file($playsArchiveFile)) : 0;

// Format sizes
function formatSize($bytes) {
    if ($bytes > 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } else if ($bytes > 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

$mainLogSizeFormatted = formatSize($mainLogSize);
$viewsArchiveSizeFormatted = formatSize($viewsArchiveSize);
$playsArchiveSizeFormatted = formatSize($playsArchiveSize);

// Check when the archiver was last run
$debugLogFile = 'logs/debug.log';
$lastRunTime = '';

if (file_exists($debugLogFile)) {
    $debugLog = file($debugLogFile);
    $debugLog = array_reverse($debugLog);

    foreach ($debugLog as $line) {
        if (strpos($line, '[ArchiveProcessor] Log processing complete') !== false) {
            preg_match('/^\[(.*?)\]/', $line, $matches);
            if (isset($matches[1])) {
                $lastRunTime = $matches[1];
                break;
            }
        }
    }
}

// Get cron status
$cronInstalled = false;
$cronCommand = 'sudo crontab -l 2>/dev/null | grep activity_log_archiver.php';
exec($cronCommand, $cronOutput, $cronReturnVal);

if ($cronReturnVal === 0 && !empty($cronOutput)) {
    $cronInstalled = true;
}

// HTML for the utility page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Archiver Utility</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="container mt-4">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-journal-text me-2"></i> Log Archiver Utility</h1>
    <a href="index.php" class="btn btn-secondary icon-btn">
        <i class="bi bi-arrow-left"></i> Back to RePlay
    </a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-archive me-2"></i> Archive Status
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>File</th>
                            <th>Size</th>
                            <th>Entries</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>Main Activity Log</td>
                            <td><?php echo $mainLogSizeFormatted; ?></td>
                            <td><?php echo $mainLogCount; ?> entries</td>
                        </tr>
                        <tr>
                            <td>Stream Views Archive</td>
                            <td><?php echo $viewsArchiveSizeFormatted; ?></td>
                            <td><?php echo $viewsArchiveCount; ?> entries</td>
                        </tr>
                        <tr>
                            <td>Video Plays Archive</td>
                            <td><?php echo $playsArchiveSizeFormatted; ?></td>
                            <td><?php echo $playsArchiveCount; ?> entries</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($lastRunTime)): ?>
                    <p class="mb-0 mt-3"><strong>Last Run:</strong> <?php echo $lastRunTime; ?></p>
                <?php endif; ?>
                <p class="mb-0">Processed Entries: <?php echo $processedEntriesCount; ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-gear me-2"></i> Archive Actions
            </div>
            <div class="card-body">
                <form method="post" class="mb-3">
                    <button type="submit" name="run_archiver" class="btn btn-primary icon-btn">
                        <i class="bi bi-arrow-clockwise"></i> Run Log Archiver Now
                    </button>
                </form>

                <div class="alert <?php echo $cronInstalled ? 'alert-success' : 'alert-warning'; ?>">
                    <h5>
                        <i class="bi bi-<?php echo $cronInstalled ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
                        Cron Status
                    </h5>
                    <?php if ($cronInstalled): ?>
                        <p class="mb-1">The log archiver is set up to run automatically daily via cron.</p>
                        <pre class="mt-2 bg-light p-2" style="font-size: 0.8rem;"><?php echo htmlspecialchars(implode("\n", $cronOutput)); ?></pre>
                    <?php else: ?>
                        <p class="mb-1">The log archiver is not set up to run automatically.</p>
                        <p class="mb-0">Run the <code>install_log_archiver_cron.sh</code> script to set up the cron job.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i> About Log Archiver
            </div>
            <div class="card-body">
                <p>The log archiver extracts livestream views and video plays from the main activity log and stores them in a more compact format for long-term analytics.</p>
                <ul>
                    <li>Main activity log is kept for 30 days</li>
                    <li>Analytics archives are kept for 1 year</li>
                    <li>Archives are automatically pruned of old entries</li>
                </ul>
                <p class="mb-0">The <code>get_all_users_activity.php</code> script uses this archived data to generate usage statistics.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>