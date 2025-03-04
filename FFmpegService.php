<?php
/**
 * FFmpegService.php
 *
 * A service class to handle all FFmpeg-related operations for the BLIVE RePlay application.
 * This service encapsulates video recording, thumbnail generation, and related operations.
 */

class FFmpegService
{
    // File paths for tracking recording status
    private string $pidFile = 'ffmpeg_pid.txt';
    private string $currentRecordingFile = 'current_recording.txt';
    private string $recordingStartFile = 'recording_start.txt';
    private string $lastChangeFile = 'last_change.txt';
    private string $logFile = 'logs/ffmpeg.log';

    // New properties for redundant recording
    private string $pidFileSecondary = 'ffmpeg_pid_secondary.txt';
    private string $currentRecordingFileSecondary = 'current_recording_secondary.txt';
    private string $redundantStatusFile = 'redundant_status.json';

    // Directories
    private string $recordingsDir;
    private string $thumbnailsDir;

    // Constructor with optional directory configuration
    public function __construct(string $recordingsDir = 'recordings', string $thumbnailsDir = 'thumbnails')
    {
        $this->recordingsDir = $recordingsDir;
        $this->thumbnailsDir = $thumbnailsDir;

        // Ensure directories exist
        $this->ensureDirectoryExists($recordingsDir);
        $this->ensureDirectoryExists($thumbnailsDir);
    }

    /**
     * Log FFmpeg-related messages
     *
     * @param string $message Message to log
     */
    public function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    /**
     * Ensure the specified directory exists
     *
     * @param string $directory Directory path
     * @return bool Success status
     */
    private function ensureDirectoryExists(string $directory): bool
    {
        if (!is_dir($directory)) {
            $this->log("Creating directory: $directory");
            $result = mkdir($directory, 0777, true);
            $this->log("Directory creation result: " . ($result ? "success" : "failed"));

            if ($result) {
                $this->log("Directory permissions: " . substr(sprintf('%o', fileperms($directory)), -4));
            }

            return $result;
        }
        return true;
    }

    /**
     * Check if recording is currently active - either primary or secondary
     *
     * @return bool True if either primary or secondary recording is active
     */
    public function isRecordingActive(): bool
    {
        // Recording is active if either primary or secondary process is running
        $primaryActive = file_exists($this->pidFile);
        $secondaryActive = file_exists($this->pidFileSecondary);

        // Log detailed status info
        if ($primaryActive || $secondaryActive) {
            $this->log("Recording status check: Primary: " . ($primaryActive ? "Active" : "Inactive") .
                ", Secondary: " . ($secondaryActive ? "Active" : "Inactive"));
        }

        return $primaryActive || $secondaryActive;
    }

    /**
     * Get the start time of the current recording
     *
     * @return int Unix timestamp or 0 if not recording
     */
    public function getRecordingStartTime(): int
    {
        return file_exists($this->recordingStartFile) ? (int)file_get_contents($this->recordingStartFile) : 0;
    }

    /**
     * Get the current recording file path
     *
     * @return string|null Path to the current recording file or null if not recording
     */
    public function getCurrentRecordingFile(): ?string
    {
        return file_exists($this->currentRecordingFile) ? file_get_contents($this->currentRecordingFile) : null;
    }

    /**
     * Start a new recording with support for redundant streams
     *
     * @param string $srtUrl Primary SRT URL to record from
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @param string|null $srtUrlSecondary Optional secondary SRT URL for redundancy
     * @param bool $useRedundant Whether to use redundant recording
     * @return array Result with success status, message, and recording details
     */
    public function startRecording(string $srtUrl, string $username, $activityLogger, string $srtUrlSecondary = null, bool $useRedundant = false): array
    {
        $this->log("\n=== Starting Recording Process ===");
        $this->log("Current working directory: " . getcwd());
        $this->log("User running PHP: " . get_current_user());
        $this->log("PHP process ID: " . getmypid());

        // Log settings
        $this->log("Primary SRT URL: $srtUrl");
        if ($useRedundant && $srtUrlSecondary) {
            $this->log("Secondary SRT URL: $srtUrlSecondary");
            $this->log("Redundant recording enabled: Yes");
        } else {
            $this->log("Redundant recording enabled: No");
        }

        $timestamp = date('Ymd_His');
        $outputFile = $this->recordingsDir . "/BLIVE_{$timestamp}.mp4";
        $this->log("Primary output file will be: " . $outputFile);

        // For redundant recording, create a secondary output file path
        $outputFileSecondary = null;
        if ($useRedundant && $srtUrlSecondary) {
            $outputFileSecondary = $this->recordingsDir . "/BLIVE_{$timestamp}_secondary.mp4";
            $this->log("Secondary output file will be: " . $outputFileSecondary);
        }

        // Directory checks
        $this->log("Recordings directory exists: " . (is_dir($this->recordingsDir) ? 'yes' : 'no'));
        $this->log("Recordings directory writable: " . (is_writable($this->recordingsDir) ? 'yes' : 'no'));

        // FFmpeg version check
        $ffmpegOutput = shell_exec('ffmpeg -version 2>&1');
        $this->log("FFmpeg version check output: " . $ffmpegOutput);

        // Test FFmpeg SRT capability
        $testCommand = 'ffmpeg -protocols 2>&1 | grep srt';
        $srtSupport = shell_exec($testCommand);
        $this->log("FFmpeg SRT support check: " . ($srtSupport ? "SRT supported" : "SRT not found in protocols"));

        // Start primary recording
        $primaryResult = $this->startSingleRecording($srtUrl, $outputFile);
        $this->log("Primary recording result: " . ($primaryResult['success'] ? "success" : "failed"));

        // Start secondary recording if enabled and secondary URL is provided
        $secondaryResult = ['success' => false, 'pid' => null];
        if ($useRedundant && $srtUrlSecondary && !empty($srtUrlSecondary) && $outputFileSecondary) {
            $this->log("Starting secondary recording process...");
            $secondaryResult = $this->startSingleRecording($srtUrlSecondary, $outputFileSecondary, true);
            $this->log("Secondary recording result: " . ($secondaryResult['success'] ? "success" : "failed"));
        } else if ($useRedundant) {
            $this->log("Secondary recording skipped - missing URL or output file");
        }

        // If at least one recording started successfully
        if ($primaryResult['success'] || ($useRedundant && $secondaryResult['success'])) {
            // Use the successful one or primary by default
            $successfulOutput = $primaryResult['success'] ? $outputFile : $outputFileSecondary;
            $now = time();

            // Write primary control files
            if ($primaryResult['success']) {
                $this->log("Writing primary control files with PID: " . $primaryResult['pid']);
                file_put_contents($this->pidFile, $primaryResult['pid']);
                file_put_contents($this->currentRecordingFile, $outputFile);
            } else {
                $this->log("Not writing primary control files - primary recording failed");
            }

            // Write secondary control files
            if ($useRedundant && $secondaryResult['success']) {
                $this->log("Writing secondary control files with PID: " . $secondaryResult['pid']);
                file_put_contents($this->pidFileSecondary, $secondaryResult['pid']);
                file_put_contents($this->currentRecordingFileSecondary, $outputFileSecondary);
            } else if ($useRedundant) {
                $this->log("Not writing secondary control files - secondary recording failed or not enabled");
            }

            // Write shared control files
            file_put_contents($this->recordingStartFile, $now);

            // Save redundant status information - always save even if not using redundant (set flags to false)
            $redundantStatus = [
                'timestamp' => $now,
                'primary_active' => $primaryResult['success'],
                'secondary_active' => $useRedundant && $secondaryResult['success'],
                'primary_file' => $outputFile,
                'secondary_file' => $outputFileSecondary,
                'primary_pid' => $primaryResult['pid'],
                'secondary_pid' => $secondaryResult['pid'],
                'using_redundant' => $useRedundant
            ];
            file_put_contents($this->redundantStatusFile, json_encode($redundantStatus, JSON_PRETTY_PRINT));

            // Update change timestamp for all clients
            file_put_contents($this->lastChangeFile, time());

            // Log activity
            if ($activityLogger) {
                $activityLogger->logActivity($username, 'started_recording', basename($successfulOutput));
                if ($useRedundant) {
                    $activityLogger->logActivity(
                        $username,
                        'redundant_recording_status',
                        "Primary: " . ($primaryResult['success'] ? 'Started' : 'Failed') .
                        ", Secondary: " . ($secondaryResult['success'] ? 'Started' : 'Failed')
                    );
                }
            }

            $this->log("=== Recording Process Complete ===\n");

            return [
                'success' => true,
                'message' => 'Recording started successfully' .
                    ($useRedundant ? ' with ' .
                        ($primaryResult['success'] && $secondaryResult['success'] ? 'both streams' :
                            ($primaryResult['success'] ? 'primary stream only' : 'secondary stream only')) : ''),
                'start_time' => $now,
                'filename' => basename($successfulOutput),
                'full_path' => $successfulOutput,
                'redundant' => $useRedundant,
                'primary_success' => $primaryResult['success'],
                'secondary_success' => $useRedundant && $secondaryResult['success']
            ];
        } else {
            $this->log("=== Recording Process Failed - No streams started ===\n");
            return [
                'success' => false,
                'message' => 'Failed to start recording on any stream'
            ];
        }
    }

    /**
     * Start a single recording stream
     *
     * @param string $srtUrl SRT URL to record from
     * @param string $outputFile Output file path
     * @param bool $isSecondary Whether this is the secondary stream
     * @return array Result with success status and PID
     */
    private function startSingleRecording(string $srtUrl, string $outputFile, bool $isSecondary = false): array
    {
        $streamLabel = $isSecondary ? "Secondary" : "Primary";
        $this->log("Starting $streamLabel recording process...");

        // Only attempt to start recording if SRT URL is not empty
        if (empty($srtUrl)) {
            $this->log("$streamLabel SRT URL is empty, skipping recording");
            return [
                'success' => false,
                'pid' => null,
                'message' => "$streamLabel SRT URL is empty"
            ];
        }

        // Command preparation
        $command = 'ffmpeg -i ' . escapeshellarg($srtUrl) .
            ' -vsync 1' .                         // Enable video sync
            ' -async 1' .                         // Enable audio sync
            ' -copyts' .                          // Copy timestamps
            ' -start_at_zero' .                   // Start timestamps at zero
            ' -c:v libx264' .                     // Video codec
            ' -preset veryfast  ' .               // Compression preset
            ' -crf 23' .                          // Reasonable quality
            ' -c:a aac' .                         // Audio codec
            ' -b:a 128k' .                        // Audio bitrate
            ' -ac 2' .                            // 2 audio channels
            ' -ar 44100' .                        // Audio sample rate
            ' -max_muxing_queue_size 1024' .      // Prevent muxing errors
            ' ' . escapeshellarg($outputFile);
        $execCommand = $command . ' > /dev/null 2>&1 & echo $!';
        $this->log("$streamLabel FFmpeg command: " . $command);

        // Execute command with error capture
        $this->log("Executing $streamLabel FFmpeg command...");
        $pid = shell_exec($execCommand . ' 2>&1');
        $this->log("$streamLabel FFmpeg command executed. PID: " . ($pid ? $pid : 'no pid returned'));

        // Test if process is running
        if ($pid) {
            $pid = trim($pid); // Ensure no whitespace in PID
            $psCommand = "ps -p " . intval($pid) . " > /dev/null 2>&1";
            exec($psCommand, $psOutput, $psReturnCode);
            $processRunning = ($psReturnCode === 0);
            $this->log("$streamLabel process check result: " . ($processRunning ? "Process running" : "Process not found") . " (Return code: $psReturnCode)");

            return [
                'success' => $processRunning,
                'pid' => $pid
            ];
        }

        return [
            'success' => false,
            'pid' => null
        ];
    }

    /**
     * Stop the current recording(s)
     *
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @return array Result with success status and message
     */
    public function stopRecording(string $username, $activityLogger): array
    {
        $this->log("\n=== Stopping Recording Process ===");

        // Check if redundant recording was used
        $redundantStatus = $this->getRedundantStatus();
        $usingRedundant = $redundantStatus && ($redundantStatus['primary_active'] || $redundantStatus['secondary_active']);

        $this->log("Using redundant recording: " . ($usingRedundant ? 'Yes' : 'No'));

        // Variables to track stop results
        $primaryStopped = false;
        $secondaryStopped = false;
        $currentRecording = null;
        $currentRecordingSecondary = null;

        // Stop primary recording
        if (file_exists($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
            $this->log("Found primary PID: " . $pid);

            $currentRecording = file_exists($this->currentRecordingFile) ? file_get_contents($this->currentRecordingFile) : '';
            $this->log("Primary recording file: " . $currentRecording);

            $this->log("Attempting to kill primary process " . $pid);
            $killResult = shell_exec('kill ' . $pid . ' 2>&1');
            $this->log("Primary kill command result: " . ($killResult ? $killResult : 'no output'));

            // Verify process is killed
            $psCheck = shell_exec("ps -p " . intval($pid) . " > /dev/null 2>&1");
            $primaryStopped = $psCheck === null;
            $this->log("Primary process kill verification: " . ($primaryStopped ? "Process killed" : "Process still running"));

            // If process is still running, force kill
            if (!$primaryStopped) {
                $this->log("Primary process still running, attempting force kill");
                $forceKillResult = shell_exec('kill -9 ' . $pid . ' 2>&1');
                $this->log("Primary force kill command result: " . ($forceKillResult ? $forceKillResult : 'no output'));
                $psCheck = shell_exec("ps -p " . intval($pid) . " > /dev/null 2>&1");
                $primaryStopped = $psCheck === null;
            }
        } else {
            $this->log("No primary PID file found");
            $primaryStopped = true; // Consider stopped if no PID file
        }

        // Stop secondary recording if it exists
        if (file_exists($this->pidFileSecondary)) {
            $pidSecondary = file_get_contents($this->pidFileSecondary);
            $this->log("Found secondary PID: " . $pidSecondary);

            $currentRecordingSecondary = file_exists($this->currentRecordingFileSecondary) ?
                file_get_contents($this->currentRecordingFileSecondary) : '';
            $this->log("Secondary recording file: " . $currentRecordingSecondary);

            $this->log("Attempting to kill secondary process " . $pidSecondary);
            $killResultSecondary = shell_exec('kill ' . $pidSecondary . ' 2>&1');
            $this->log("Secondary kill command result: " . ($killResultSecondary ? $killResultSecondary : 'no output'));

            // Verify process is killed
            $psCheckSecondary = shell_exec("ps -p " . intval($pidSecondary) . " > /dev/null 2>&1");
            $secondaryStopped = $psCheckSecondary === null;
            $this->log("Secondary process kill verification: " . ($secondaryStopped ? "Process killed" : "Process still running"));

            // If process is still running, force kill
            if (!$secondaryStopped) {
                $this->log("Secondary process still running, attempting force kill");
                $forceKillResultSecondary = shell_exec('kill -9 ' . $pidSecondary . ' 2>&1');
                $this->log("Secondary force kill command result: " . ($forceKillResultSecondary ? $forceKillResultSecondary : 'no output'));
                $psCheckSecondary = shell_exec("ps -p " . intval($pidSecondary) . " > /dev/null 2>&1");
                $secondaryStopped = $psCheckSecondary === null;
            }
        } else {
            if ($usingRedundant) {
                $this->log("No secondary PID file found although redundant recording was active");
            }
            $secondaryStopped = true; // Consider stopped if no PID file
        }

        // Update redundant status with final state
        if ($usingRedundant) {
            $redundantStatus['primary_active'] = false;
            $redundantStatus['secondary_active'] = false;
            $redundantStatus['stopped_at'] = time();
            file_put_contents($this->redundantStatusFile, json_encode($redundantStatus, JSON_PRETTY_PRINT));
        }

        // Clean up files
        $this->cleanupRecordingFiles();

        // Handle the recording files based on redundant strategy
        $finalRecordingFile = null;
        if ($usingRedundant && $currentRecording && $currentRecordingSecondary) {
            $finalRecordingFile = $this->handleRedundantRecordings(
                $currentRecording,
                $currentRecordingSecondary,
                $redundantStatus['primary_active'],
                $redundantStatus['secondary_active']
            );
        } else {
            $finalRecordingFile = $currentRecording;
        }

        if ($finalRecordingFile) {
            // Log activity
            if ($activityLogger) {
                $activityLogger->logActivity($username, 'stopped_recording', basename($finalRecordingFile));
                if ($usingRedundant) {
                    $activityLogger->logActivity(
                        $username,
                        'redundant_recording_complete',
                        "Primary: " . ($primaryStopped ? 'Stopped' : 'Failed to stop') .
                        ", Secondary: " . ($secondaryStopped ? 'Stopped' : 'Failed to stop') .
                        ", Final file: " . basename($finalRecordingFile)
                    );
                }
            }

            // Update change timestamp for all clients
            file_put_contents($this->lastChangeFile, time());

            $this->log("=== Stop Recording Process Complete ===\n");

            return [
                'success' => true,
                'message' => 'Recording stopped successfully',
                'filename' => basename($finalRecordingFile),
                'redundant' => $usingRedundant,
                'primary_stopped' => $primaryStopped,
                'secondary_stopped' => $secondaryStopped
            ];
        }

        $this->log("=== Stop Recording Process Complete with no recording file ===\n");

        return [
            'success' => true,
            'message' => 'Recording process stopped, but no recording file was found',
            'filename' => ''
        ];
    }

    /**
     * Handle redundant recordings based on settings strategy
     *
     * @param string $primaryFile Primary recording file path
     * @param string $secondaryFile Secondary recording file path
     * @param bool $primaryActive Whether primary recording was active
     * @param bool $secondaryActive Whether secondary recording was active
     * @return string The path to the final recording file to use
     */
    private function handleRedundantRecordings(
        string $primaryFile,
        string $secondaryFile,
        bool $primaryActive,
        bool $secondaryActive
    ): string {
        // Get the redundancy strategy from settings
        $settingsManager = new SettingsManager();
        $settings = $settingsManager->getSettings();
        $strategy = $settings['redundant_recording_strategy'] ?? 'auto';

        $this->log("Handling redundant recordings with strategy: $strategy");
        $this->log("Primary file: $primaryFile (Active: " . ($primaryActive ? 'Yes' : 'No') . ")");
        $this->log("Secondary file: $secondaryFile (Active: " . ($secondaryActive ? 'Yes' : 'No') . ")");

        // If only one file exists/active, use that one
        if ($primaryActive && !$secondaryActive) {
            $this->log("Only primary recording was active, using it");
            if (file_exists($secondaryFile)) {
                $this->log("Deleting unused secondary file");
                unlink($secondaryFile);
            }
            return $primaryFile;
        }

        if (!$primaryActive && $secondaryActive) {
            $this->log("Only secondary recording was active, using it");

            // If strategy is not 'both', rename secondary to match primary name pattern
            if ($strategy !== 'both') {
                $primaryBasename = basename($primaryFile);
                $newSecondaryPath = dirname($secondaryFile) . '/' . $primaryBasename;
                $this->log("Renaming secondary file to match primary pattern: $newSecondaryPath");

                if (file_exists($primaryFile)) {
                    $this->log("Deleting inactive primary file first");
                    unlink($primaryFile);
                }

                rename($secondaryFile, $newSecondaryPath);
                return $newSecondaryPath;
            }

            return $secondaryFile;
        }

        // If we get here, both recordings should exist - handle based on strategy
        if (!file_exists($primaryFile) || !file_exists($secondaryFile)) {
            $this->log("Warning: Expected both files to exist but at least one is missing");
            return file_exists($primaryFile) ? $primaryFile : (file_exists($secondaryFile) ? $secondaryFile : $primaryFile);
        }

        switch ($strategy) {
            case 'primary':
                $this->log("Using primary file as per strategy");
                if (file_exists($secondaryFile)) {
                    $this->log("Deleting secondary file");
                    unlink($secondaryFile);
                }
                return $primaryFile;

            case 'secondary':
                $this->log("Using secondary file as per strategy");
                // Rename secondary to match primary name pattern
                $primaryBasename = basename($primaryFile);
                $newSecondaryPath = dirname($secondaryFile) . '/' . $primaryBasename;

                if (file_exists($primaryFile)) {
                    $this->log("Deleting primary file");
                    unlink($primaryFile);
                }

                rename($secondaryFile, $newSecondaryPath);
                return $newSecondaryPath;

            case 'both':
                $this->log("Keeping both files as per strategy");
                return $primaryFile; // Return primary as the "main" file

            case 'auto':
            default:
                // Auto: Compare file sizes and duration, pick the better one
                $primarySize = filesize($primaryFile);
                $secondarySize = filesize($secondaryFile);

                $primaryDuration = $this->getVideoDurationSeconds($primaryFile);
                $secondaryDuration = $this->getVideoDurationSeconds($secondaryFile);

                $this->log("Auto comparison - Primary: $primarySize bytes, $primaryDuration seconds");
                $this->log("Auto comparison - Secondary: $secondarySize bytes, $secondaryDuration seconds");

                // First check duration - prefer longer recording (with 5-sec tolerance)
                if (abs($primaryDuration - $secondaryDuration) > 5) {
                    if ($primaryDuration > $secondaryDuration) {
                        $this->log("Primary has longer duration, selecting it");
                        if (file_exists($secondaryFile)) {
                            $this->log("Deleting secondary file");
                            unlink($secondaryFile);
                        }
                        return $primaryFile;
                    } else {
                        $this->log("Secondary has longer duration, selecting it");
                        $primaryBasename = basename($primaryFile);
                        $newSecondaryPath = dirname($secondaryFile) . '/' . $primaryBasename;

                        if (file_exists($primaryFile)) {
                            $this->log("Deleting primary file");
                            unlink($primaryFile);
                        }

                        rename($secondaryFile, $newSecondaryPath);
                        return $newSecondaryPath;
                    }
                }

                // If durations are similar, use file size as the deciding factor
                if ($primarySize > $secondarySize * 1.1) { // Primary is 10% larger
                    $this->log("Primary file is significantly larger, selecting it");
                    if (file_exists($secondaryFile)) {
                        unlink($secondaryFile);
                    }
                    return $primaryFile;
                } else if ($secondarySize > $primarySize * 1.1) { // Secondary is 10% larger
                    $this->log("Secondary file is significantly larger, selecting it");
                    $primaryBasename = basename($primaryFile);
                    $newSecondaryPath = dirname($secondaryFile) . '/' . $primaryBasename;

                    if (file_exists($primaryFile)) {
                        unlink($primaryFile);
                    }

                    rename($secondaryFile, $newSecondaryPath);
                    return $newSecondaryPath;
                } else {
                    // Files are similar in size and duration, prefer primary
                    $this->log("Files are similar, defaulting to primary");
                    if (file_exists($secondaryFile)) {
                        unlink($secondaryFile);
                    }
                    return $primaryFile;
                }
        }
    }

    /**
     * Get video duration in seconds
     *
     * @param string $videoFile Path to the video file
     * @return int Duration in seconds or 0 if unable to determine
     */
    private function getVideoDurationSeconds(string $videoFile): int
    {
        $command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoFile) . " 2>&1";
        $output = shell_exec($command);

        if ($output !== null && is_numeric(trim($output))) {
            return (int)floatval(trim($output));
        }

        return 0;
    }

    /**
     * Clean up recording control files including secondary files
     *
     * @return bool Success status
     */
    private function cleanupRecordingFiles(): bool
    {
        $success = true;

        // Clean primary files
        if (file_exists($this->pidFile)) {
            $unlinkResult = unlink($this->pidFile);
            $this->log("PID file cleanup: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        if (file_exists($this->currentRecordingFile)) {
            $unlinkResult = unlink($this->currentRecordingFile);
            $this->log("Current recording file cleanup: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        // Clean secondary files
        if (file_exists($this->pidFileSecondary)) {
            $unlinkResult = unlink($this->pidFileSecondary);
            $this->log("Secondary PID file cleanup: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        if (file_exists($this->currentRecordingFileSecondary)) {
            $unlinkResult = unlink($this->currentRecordingFileSecondary);
            $this->log("Secondary current recording file cleanup: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        // Clean shared files
        if (file_exists($this->recordingStartFile)) {
            $unlinkResult = unlink($this->recordingStartFile);
            $this->log("Start time file cleanup: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        return $success;
    }

    /**
     * Get redundant recording status
     *
     * @return array|null Status information or null if not using redundant recording
     */
    public function getRedundantStatus(): ?array
    {
        // Always check if the file exists first
        if (!file_exists($this->redundantStatusFile)) {
            $this->log("Redundant status file not found");
            return null;
        }

        try {
            $statusJson = file_get_contents($this->redundantStatusFile);
            if ($statusJson === false) {
                $this->log("Failed to read redundant status file");
                return null;
            }

            $status = json_decode($statusJson, true);
            if ($status === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->log("Failed to parse redundant status file: " . json_last_error_msg());
                return null;
            }

            // Ensure basic fields exist
            if (!isset($status['primary_active']) || !isset($status['secondary_active'])) {
                $this->log("Redundant status file missing required fields");
                return null;
            }

            // Verify if processes are still running
            if (isset($status['primary_pid']) && !empty($status['primary_pid'])) {
                $primaryPid = trim($status['primary_pid']);
                $psCommand = "ps -p " . intval($primaryPid) . " > /dev/null 2>&1";
                exec($psCommand, $psOutput, $psReturnCode);
                $status['primary_active'] = ($psReturnCode === 0);
            }

            if (isset($status['secondary_pid']) && !empty($status['secondary_pid'])) {
                $secondaryPid = trim($status['secondary_pid']);
                $psCommand = "ps -p " . intval($secondaryPid) . " > /dev/null 2>&1";
                exec($psCommand, $psOutput, $psReturnCode);
                $status['secondary_active'] = ($psReturnCode === 0);
            }

            // Update the status file with current process status
            file_put_contents($this->redundantStatusFile, json_encode($status, JSON_PRETTY_PRINT));

            return $status;
        } catch (Exception $e) {
            $this->log("Error getting redundant status: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if both primary and secondary streams are active
     *
     * @return array Status of both streams with 'primary' and 'secondary' keys
     */
    public function checkRedundantStreamStatus(): array
    {
        $status = [
            'primary' => false,
            'secondary' => false
        ];

        // Check primary stream
        if (file_exists($this->pidFile)) {
            $pid = trim(file_get_contents($this->pidFile));
            if (!empty($pid)) {
                $psCommand = "ps -p " . intval($pid) . " > /dev/null 2>&1";
                exec($psCommand, $psOutput, $psReturnCode);
                $status['primary'] = ($psReturnCode === 0);
            }
        }

        // Check secondary stream
        if (file_exists($this->pidFileSecondary)) {
            $pid = trim(file_get_contents($this->pidFileSecondary));
            if (!empty($pid)) {
                $psCommand = "ps -p " . intval($pid) . " > /dev/null 2>&1";
                exec($psCommand, $psOutput, $psReturnCode);
                $status['secondary'] = ($psReturnCode === 0);
            }
        }

        $this->log("Stream status check: Primary: " . ($status['primary'] ? "Running" : "Not running") .
            ", Secondary: " . ($status['secondary'] ? "Running" : "Not running"));

        return $status;
    }

    /**
     * Generate a thumbnail for a video file
     *
     * @param string $videoFile Path to the video file
     * @param string $thumbnailFile Path to save the thumbnail
     * @return bool Success status
     */
    public function generateThumbnail(string $videoFile, string $thumbnailFile): bool
    {
        // Ensure the thumbnails directory exists
        $thumbnailDir = dirname($thumbnailFile);
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0777, true);
        }

        // Always try to generate a new thumbnail
        $attempts = 0;
        $success = false;

        while ($attempts < 3 && !$success) {
            // Use multiple timestamp attempts to ensure thumbnail
            $timestamps = ['00:00:05', '00:00:10', '00:00:01'];
            $currentTimestamp = $timestamps[$attempts % count($timestamps)];

            $command = "ffmpeg -i " . escapeshellarg($videoFile) .
                " -ss " . escapeshellarg($currentTimestamp) .
                " -vframes 1 " .
                " -q:v 2 " .
                escapeshellarg($thumbnailFile) .
                " > /dev/null 2>&1";

            shell_exec($command);

            // Verify thumbnail was created
            clearstatcache();
            if (file_exists($thumbnailFile) && filesize($thumbnailFile) > 0) {
                $success = true;
                // Set file permissions to ensure web accessibility
                chmod($thumbnailFile, 0644);
            }

            $attempts++;
        }

        // Log if thumbnail generation consistently fails
        if (!$success) {
            $this->log("Failed to generate thumbnail for: " . $videoFile);
        }

        return $success;
    }

    /**
     * Get the duration of a video file
     *
     * @param string $videoFile Path to the video file
     * @return string Duration in HH:MM:SS format or "Unknown"
     */
    public function getVideoDuration(string $videoFile): string
    {
        $command = "ffmpeg -i " . escapeshellarg($videoFile) . " 2>&1";
        $output = shell_exec($command);

        if (preg_match("/Duration: (.*?),/", $output, $matches)) {
            return trim($matches[1]);
        }

        return "Unknown";
    }

    /**
     * Clean up the FFmpeg log file, removing entries older than specified days
     *
     * @param int $days Number of days to keep logs for
     * @return bool Success status
     */
    public function cleanFFmpegLog(int $days = 14): bool
    {
        if (!file_exists($this->logFile)) {
            return true;
        }

        // Read the entire log file content
        $content = file_get_contents($this->logFile);
        if ($content === false) {
            error_log("Unable to read FFmpeg log file");
            return false;
        }

        // Split content into individual lines
        $lines = explode("\n", $content);

        // Calculate timestamp for cutoff date
        $cutoffDate = strtotime("-{$days} days");

        // Filter lines, keeping only those newer than the cutoff date or without timestamps
        $keptLines = array_filter($lines, function ($line) use ($cutoffDate) {
            // Keep empty lines
            if (empty(trim($line))) {
                return true;
            }

            // Extract timestamp from line (expected format: [YYYY-MM-DD HH:MM:SS])
            if (preg_match('/^\[([\d-]+ [\d:]+)\]/', $line, $matches)) {
                $lineDate = strtotime($matches[1]);
                return $lineDate >= $cutoffDate;
            }

            // Keep lines without timestamps (e.g., continuation lines)
            return true;
        });

        // Join filtered lines back into a single string
        $newContent = implode("\n", $keptLines);

        // Write the cleaned content back to the log file
        if (file_put_contents($this->logFile, $newContent) === false) {
            error_log("Failed to write updated FFmpeg log file");
            return false;
        }

        return true;
    }

    /**
     * Get the FFmpeg log content
     *
     * @param bool $reverse Whether to reverse the order (newest first)
     * @return string Log content
     */
    public function getFFmpegLog(bool $reverse = true): string
    {
        if (!file_exists($this->logFile)) {
            return "No FFmpeg log entries found.";
        }

        // Clean the log before returning
        $this->cleanFFmpegLog();

        // Read the log file
        $content = file_get_contents($this->logFile);

        if ($reverse) {
            // Split content into lines
            $lines = explode("\n", $content);

            // Reverse the lines array to show newest entries first
            $lines = array_reverse($lines);

            // Join the lines back together
            $content = implode("\n", $lines);
        }

        return $content;
    }

    /**
     * Notify clients of a recording status change
     */
    public function updateChangeTimestamp(): bool
    {
        return file_put_contents($this->lastChangeFile, time()) !== false;
    }

    /**
     * Check if FFmpeg is available on the system
     *
     * @return bool True if FFmpeg is available
     */
    public function isFFmpegAvailable(): bool
    {
        $command = 'which ffmpeg 2>/dev/null';
        $result = shell_exec($command);

        return !empty($result);
    }

    /**
     * Check if FFmpeg supports SRT protocol
     *
     * @return bool True if SRT is supported
     */
    public function isSrtSupported(): bool
    {
        $command = 'ffmpeg -protocols 2>&1 | grep srt';
        $result = shell_exec($command);

        return !empty($result);
    }

    /**
     * Get FFmpeg version information
     *
     * @return string Version information
     */
    public function getFFmpegVersion(): string
    {
        $command = 'ffmpeg -version 2>&1 | head -n 1';
        $result = shell_exec($command);

        return trim($result ?? 'Unknown');
    }

    /**
     * Delete a recording file and its thumbnail
     *
     * @param string $videoFile Path to the video file
     * @param string $username Username for logging
     * @param object $activityLogger Logger for activity
     * @return bool Success status
     */
    public function deleteRecording(string $videoFile, string $username, $activityLogger): bool
    {
        $fileName = basename($videoFile);
        $this->log("Deleting recording: $fileName");

        $success = true;

        // Delete the video file
        if (file_exists($videoFile)) {
            $unlinkResult = unlink($videoFile);
            $this->log("Video file deletion: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        } else {
            $this->log("Video file not found: $videoFile");
            $success = false;
        }

        // Delete the thumbnail
        $thumbnailFile = $this->thumbnailsDir . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '.jpg';
        if (file_exists($thumbnailFile)) {
            $unlinkResult = unlink($thumbnailFile);
            $this->log("Thumbnail deletion: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        // Log the activity
        if ($activityLogger) {
            $activityLogger->logActivity($username, 'deleted_recording', $fileName);
        }

        return $success;
    }
}