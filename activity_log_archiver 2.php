<?php
/**
 * Activity Log Archiver
 *
 * This script processes the user_activity.log file, extracts view/playback data,
 * and stores it in a compact format for long-term analytics while keeping the
 * main log file manageable.
 *
 * Recommended to run daily via cron:
 * 0 0 * * * php /path/to/activity_log_archiver.php > /dev/null 2>&1
 */

chdir(dirname(__FILE__));

require_once 'settings.php';
require_once 'logging.php';

class ActivityLogArchiver
{
    // Main activity log
    private $mainLogFile = 'logs/user_activity.log';

    // Archive files
    private $archiveDir = 'logs';
    private $viewsArchiveFile = 'logs/stream_views.log';
    private $playsArchiveFile = 'logs/video_plays.log';

    // Processed entries tracking file
    private $processedEntriesFile = 'logs/processed_entries.txt';

    // Main log retention period (days)
    private $mainLogRetention = 30; // Keep 30 days in main log

    // Archive retention period (days)
    private $archiveRetention = 365; // Keep 1 year of analytics data

    // Actions to extract and archive
    private $trackActions = [
        'livestream_click',
        'played_vlc'
    ];

    // Settings
    private $settings;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load settings for timezone
        $settingsManager = new SettingsManager();
        $this->settings = $settingsManager->getSettings();

        // Set timezone
        date_default_timezone_set($this->settings['timezone'] ?? 'America/Chicago');

        // Ensure archive directory exists
        if (!is_dir($this->archiveDir)) {
            mkdir($this->archiveDir, 0755, true);
            $this->log("Created archive directory: {$this->archiveDir}");
        }

        // Initialize archive files if they don't exist
        foreach ([$this->viewsArchiveFile, $this->playsArchiveFile, $this->processedEntriesFile] as $file) {
            if (!file_exists($file)) {
                file_put_contents($file, "");
                $this->log("Created file: $file");
            }
        }
    }

    /**
     * Log message to debug log
     *
     * @param string $message Message to log
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [ArchiveProcessor] $message\n";
        file_put_contents('logs/debug.log', $logMessage, FILE_APPEND);
    }

    /**
     * Load previously processed entry hashes
     */
    private function loadProcessedEntries()
    {
        if (file_exists($this->processedEntriesFile)) {
            return array_filter(explode("\n", file_get_contents($this->processedEntriesFile)));
        }
        return [];
    }

    /**
     * Save processed entry hashes
     */
    private function saveProcessedEntries($entries)
    {
        file_put_contents($this->processedEntriesFile, implode("\n", array_unique($entries)));
    }

    /**
     * Process and archive the activity log
     */
    public function processLog()
    {
        $this->log("Starting log processing");

        if (!file_exists($this->mainLogFile)) {
            $this->log("Main log file not found: {$this->mainLogFile}");
            return;
        }

        // Load previously processed entries
        $processedEntries = $this->loadProcessedEntries();

        // Read and parse the log file
        $this->log("Reading main log file");
        $logLines = file($this->mainLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $remainingLines = []; // Lines to keep in main log
        $processedCount = 0;
        $livestreamViews = [];
        $videoPlays = [];
        $newProcessedEntries = $processedEntries;

        // Process each log line
        foreach ($logLines as $line) {
            $entry = json_decode($line, true);

            // Keep invalid entries in main log
            if (!$entry || !isset($entry['action']) || !isset($entry['timestamp'])) {
                $remainingLines[] = $line;
                continue;
            }

            // Create a unique hash of the entry
            $entryHash = md5($line);

            // Skip if already processed
            if (in_array($entryHash, $processedEntries)) {
                $remainingLines[] = $line; // Keep in main log if not processed for archiving
                continue;
            }

            // Only process target actions
            if (!in_array($entry['action'], $this->trackActions)) {
                $remainingLines[] = $line;
                continue;
            }

            // Parse timestamp
            $timestamp = strtotime($entry['timestamp']);
            $date = date('Y-m-d', $timestamp);

            // Format the archive entry: timestamp,username,file
            $archiveEntry = sprintf(
                "%s,%s,%s",
                $entry['timestamp'],
                $entry['username'],
                $entry['filename'] ?? ''
            );

            // Add to appropriate archive
            if ($entry['action'] === 'livestream_click') {
                $livestreamViews[] = $archiveEntry;
            } else if ($entry['action'] === 'played_vlc') {
                $videoPlays[] = $archiveEntry;
            }

            $newProcessedEntries[] = $entryHash;
            $processedCount++;
        }

        // Append to archive files
        if (!empty($livestreamViews)) {
            file_put_contents($this->viewsArchiveFile, implode("\n", $livestreamViews) . "\n", FILE_APPEND);
            $this->log("Archived " . count($livestreamViews) . " livestream views");
        }

        if (!empty($videoPlays)) {
            file_put_contents($this->playsArchiveFile, implode("\n", $videoPlays) . "\n", FILE_APPEND);
            $this->log("Archived " . count($videoPlays) . " video plays");
        }

        // Update main log file with remaining lines
        file_put_contents($this->mainLogFile, implode("\n", $remainingLines) . (empty($remainingLines) ? '' : "\n"));

        // Save processed entries
        $this->saveProcessedEntries($newProcessedEntries);

        // Clean up old archive entries
        $this->cleanArchives();

        $this->log("Log processing complete. Processed $processedCount new entries");
    }

    /**
     * Clean up the main activity log, keeping only recent entries
     */
    private function cleanMainLog()
    {
        $this->log("Cleaning main activity log");

        // Get current log data
        $logLines = file($this->mainLogFile, FILE_IGNORE_NEW_LINES);

        // Calculate cutoff date (days to keep)
        $cutoffTime = strtotime("-{$this->mainLogRetention} days");

        // Filter to keep only recent entries
        $keptLines = [];
        $removedCount = 0;

        foreach ($logLines as $line) {
            $entry = json_decode($line, true);

            // Keep non-JSON lines and entries without timestamps
            if (!$entry || !isset($entry['timestamp'])) {
                $keptLines[] = $line;
                continue;
            }

            // Parse entry timestamp
            $entryTime = strtotime($entry['timestamp']);

            // Keep entry if newer than cutoff
            if ($entryTime >= $cutoffTime) {
                $keptLines[] = $line;
            } else {
                $removedCount++;
            }
        }

        // Write filtered log back
        file_put_contents($this->mainLogFile, implode("\n", $keptLines));

        $this->log("Cleaned main log: removed $removedCount old entries");
    }

    /**
     * Clean up old archive entries beyond retention period
     */
    private function cleanArchives()
    {
        $this->log("Cleaning archive files");

        foreach ([$this->viewsArchiveFile, $this->playsArchiveFile] as $archiveFile) {
            if (!file_exists($archiveFile)) {
                continue;
            }

            // Get current archive data
            $lines = file($archiveFile, FILE_IGNORE_NEW_LINES);

            // Calculate cutoff date
            $cutoffTime = strtotime("-{$this->archiveRetention} days");

            // Filter to keep only entries within retention period
            $keptLines = [];
            $removedCount = 0;

            foreach ($lines as $line) {
                $parts = explode(',', $line, 2);

                // Skip malformed lines
                if (count($parts) < 2) {
                    $keptLines[] = $line;
                    continue;
                }

                // Parse timestamp from first part
                $timestamp = strtotime($parts[0]);

                // Keep entry if newer than cutoff
                if ($timestamp >= $cutoffTime) {
                    $keptLines[] = $line;
                } else {
                    $removedCount++;
                }
            }

            // Write filtered archive back
            file_put_contents($archiveFile, implode("\n", $keptLines));

            $filename = basename($archiveFile);
            $this->log("Cleaned $filename: removed $removedCount outdated entries");
        }
    }

    /**
     * Get analytics data for a specific user and time range
     *
     * @param string $username Username to filter by (null for all users)
     * @param string $timeRange Time range ('day', 'week', 'month', 'year')
     * @return array Analytics data
     */
    public function getAnalytics($username = null, $timeRange = 'week')
    {
        // Define time cutoffs based on range
        $now = new DateTime();
        $cutoffDate = new DateTime();

        switch ($timeRange) {
            case 'day':
                $cutoffDate->setTime(0, 0, 0);
                break;
            case 'week':
                $cutoffDate->modify('-7 days');
                break;
            case 'month':
                $cutoffDate->modify('-30 days');
                break;
            case 'year':
                $cutoffDate->modify('-365 days');
                break;
            default:
                $cutoffDate->modify('-7 days');
        }

        // Initialize result arrays
        $result = [
            'livestreamViews' => [],
            'videoPlays' => []
        ];

        // Process both archive files
        $archives = [
            'livestreamViews' => $this->viewsArchiveFile,
            'videoPlays' => $this->playsArchiveFile
        ];

        foreach ($archives as $key => $file) {
            if (!file_exists($file)) {
                continue;
            }

            // Read and parse the archive
            $lines = file($file, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $line) {
                $parts = explode(',', $line);

                // Skip malformed lines
                if (count($parts) < 2) {
                    continue;
                }

                $entryTimestamp = $parts[0];
                $entryUsername = $parts[1];
                $entryFile = $parts[2] ?? '';

                // Filter by username if provided
                if ($username && $entryUsername !== $username) {
                    continue;
                }

                // Check if in time range
                $entryDate = new DateTime($entryTimestamp);
                if ($entryDate < $cutoffDate || $entryDate > $now) {
                    continue;
                }

                // Add to results
                if (!isset($result[$key][$entryUsername])) {
                    $result[$key][$entryUsername] = 0;
                }

                $result[$key][$entryUsername]++;
            }
        }

        // Add data from main log file for recent events that might not be archived yet
        $activityLogger = new ActivityLogger();
        $recentActivities = $activityLogger->getActivities();

        foreach ($recentActivities as $activity) {
            // Skip if username is filtered and doesn't match
            if ($username && $activity['username'] !== $username) {
                continue;
            }

            // Check action type
            if ($activity['action'] === 'livestream_click' || $activity['action'] === 'played_vlc') {
                // Convert timestamp to DateTime
                $activityDate = new DateTime($activity['timestamp']);

                // Skip if outside time range
                if ($activityDate < $cutoffDate || $activityDate > $now) {
                    continue;
                }

                // Map action to result key
                $resultKey = $activity['action'] === 'livestream_click' ? 'livestreamViews' : 'videoPlays';

                // Initialize counter if needed
                if (!isset($result[$resultKey][$activity['username']])) {
                    $result[$resultKey][$activity['username']] = 0;
                }

                // Increment counter
                $result[$resultKey][$activity['username']]++;
            }
        }

        return $result;
    }
}

// Run the archiver if executed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    $archiver = new ActivityLogArchiver();
    $archiver->processLog();
}