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
     * Check if recording is currently active
     *
     * @return bool True if recording is active
     */
    public function isRecordingActive(): bool
    {
        return file_exists($this->pidFile);
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
     * Start a new recording
     *
     * @param string $srtUrl SRT URL to record from
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @return array Result with success status, message, and recording details
     */
    public function startRecording($srtUrl, $username, $activityLogger) {
        $maxWaitTime = 30; // Maximum wait time in seconds
        $frameCheckInterval = 1; // Check for frames every second
        $requiredFrames = 5; // Minimum number of frames to confirm recording

        $outputFile = $this->recordingsDir . "/BLIVE_" . date('Ymd_His') . ".mp4";

        $command = "ffmpeg -i " . escapeshellarg($srtUrl) .
            " -vsync 1 -async 1 -copyts -start_at_zero" .
            " -c:v libx264 -preset veryfast -crf 23" .
            " -c:a aac -b:a 128k" .
            " -max_muxing_queue_size 1024" .
            " -frames:v " . ($maxWaitTime * 2) . // Limit total frames to prevent infinite wait
            " " . escapeshellarg($outputFile) .
            " 2>&1";

        // Start recording in background
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            $this->log("Failed to start FFmpeg process");
            return ['success' => false, 'message' => 'Could not start recording process'];
        }

        // Wait and check for frames
        $startTime = time();
        $framesDetected = 0;

        while (time() - $startTime < $maxWaitTime) {
            // Use ffprobe to count frames
            $frameCountCmd = "ffprobe -v error -select_streams v:0 -count_packets " .
                "-show_entries stream=nb_read_packets -of csv=p=0 " .
                escapeshellarg($outputFile);

            $frameCountOutput = shell_exec($frameCountCmd);
            $frameCount = ($frameCountOutput !== null) ? trim($frameCountOutput) : "0";

            if ($frameCount > $requiredFrames) {
                // Write PID and other control files
                file_put_contents($this->pidFile, proc_get_status($process)['pid']);
                file_put_contents($this->currentRecordingFile, $outputFile);
                file_put_contents($this->recordingStartFile, time());

                $this->log("Recording started successfully. Frames detected: $frameCount");
                return [
                    'success' => true,
                    'message' => 'Recording started',
                    'filename' => basename($outputFile),
                    'full_path' => $outputFile
                ];
            }

            sleep($frameCheckInterval);
        }

        // If no frames detected, terminate process
        proc_terminate($process);
        proc_close($process);

        $this->log("No frames detected after $maxWaitTime seconds");
        return ['success' => false, 'message' => 'No video frames detected'];
    }

    /**
     * Stop the current recording
     *
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @return array Result with success status and message
     */
    public function stopRecording(string $username, $activityLogger): array
    {
        $this->log("\n=== Stopping Recording Process ===");

        if (!file_exists($this->pidFile)) {
            $this->log("No PID file found - recording may not have been started");
            return [
                'success' => false,
                'message' => 'No active recording found'
            ];
        }

        $pid = file_get_contents($this->pidFile);
        $this->log("Found PID: " . $pid);

        $currentRecording = file_exists($this->currentRecordingFile) ? file_get_contents($this->currentRecordingFile) : '';
        $this->log("Current recording file: " . $currentRecording);

        $this->log("Attempting to kill process " . $pid);
        $killResult = shell_exec('kill ' . $pid . ' 2>&1');
        $this->log("Kill command result: " . ($killResult ? $killResult : 'no output'));

        // Verify process is killed
        $psCheck = shell_exec("ps -p " . intval($pid) . " > /dev/null 2>&1");
        $this->log("Process kill verification: " . ($psCheck === null ? "Process killed" : "Process still running"));

        // If process is still running, force kill
        if ($psCheck !== null) {
            $this->log("Process still running, attempting force kill");
            $forceKillResult = shell_exec('kill -9 ' . $pid . ' 2>&1');
            $this->log("Force kill command result: " . ($forceKillResult ? $forceKillResult : 'no output'));
        }

        // Clean up files
        $cleanupResult = $this->cleanupRecordingFiles();
        $this->log("Control files cleanup: " . ($cleanupResult ? "success" : "failed"));

        if ($currentRecording) {
            // Log activity
            if ($activityLogger) {
                $activityLogger->logActivity($username, 'stopped_recording', basename($currentRecording));
            }

            // Update change timestamp for all clients
            file_put_contents($this->lastChangeFile, time());

            $this->log("=== Stop Recording Process Complete ===\n");

            return [
                'success' => true,
                'message' => 'Recording stopped successfully',
                'filename' => basename($currentRecording)
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
     * Clean up recording control files
     *
     * @return bool Success status
     */
    private function cleanupRecordingFiles(): bool
    {
        $success = true;

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

        if (file_exists($this->recordingStartFile)) {
            $unlinkResult = unlink($this->recordingStartFile);
            $this->log("Start time file cleanup: " . ($unlinkResult ? "success" : "failed"));
            $success = $success && $unlinkResult;
        }

        return $success;
    }

    /**
     * Delete a recorded video and its thumbnail
     *
     * @param string $filePath Path to the video file
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @return bool Success status
     */
    public function deleteRecording(string $filePath, string $username, $activityLogger): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Log activity
        if ($activityLogger) {
            $activityLogger->logActivity($username, 'deleted_file', basename($filePath));
        }

        // Delete video file
        $videoDeleted = unlink($filePath);

        // Delete thumbnail
        $thumbnailFile = $this->thumbnailsDir . '/' . pathinfo(basename($filePath), PATHINFO_FILENAME) . '.jpg';
        $thumbnailDeleted = true;

        if (file_exists($thumbnailFile)) {
            $thumbnailDeleted = unlink($thumbnailFile);
        }

        // Update change timestamp for all clients
        file_put_contents($this->lastChangeFile, time());

        return $videoDeleted && $thumbnailDeleted;
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

        if ($output !== null && preg_match("/Duration: (.*?),/", $output, $matches)) {
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

        return ($result !== null) ? trim($result) : 'Unknown';
    }
}