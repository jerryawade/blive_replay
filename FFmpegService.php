<?php
/**
 * FFmpegService.php
 *
 * A robust service class to handle FFmpeg operations for BLIVE RePlay with extensive logging.
 */

class FFmpegService
{
    private string $pidFile = 'ffmpeg_pid.txt';
    private string $currentRecordingFile = 'current_recording.txt';
    private string $recordingStartFile = 'recording_start.txt';
    private string $lastChangeFile = 'last_change.txt';
    private string $logFile = 'logs/ffmpeg.log';

    private string $recordingsDir;
    private string $thumbnailsDir;

    public function __construct(string $recordingsDir = 'recordings', string $thumbnailsDir = 'thumbnails')
    {
        $this->log("Initializing FFmpegService");
        $this->recordingsDir = $recordingsDir;
        $this->thumbnailsDir = $thumbnailsDir;

        $this->log("Config - Recordings dir: {$recordingsDir}");
        $this->log("Config - Thumbnails dir: {$thumbnailsDir}");

        try {
            if (!$this->ensureDirectoryExists($recordingsDir) || !$this->ensureDirectoryExists($thumbnailsDir)) {
                throw new Exception("Failed to initialize directories");
            }
            $this->log("Directory initialization successful");

            if (!$this->isFFmpegAvailable()) {
                throw new Exception("FFmpeg is not available on the system");
            }
            $this->log("FFmpeg availability confirmed");
        } catch (Exception $e) {
            $this->log("Construction error: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function log(string $message, ?string $pid = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $memory = round(memory_get_usage() / 1024 / 1024, 2) . 'MB';
        $pidPart = $pid ? "[PID: $pid]" : "";
        $detailedMessage = "[$timestamp] $pidPart [Memory: $memory] $message";

        try {
            if (!is_dir(dirname($this->logFile))) {
                mkdir(dirname($this->logFile), 0777, true);
            }
            $result = file_put_contents($this->logFile, "$detailedMessage\n", FILE_APPEND | LOCK_EX);
            if ($result === false) {
                error_log("Failed to write to FFmpeg log: $detailedMessage");
            }
        } catch (Exception $e) {
            error_log("Logging error: " . $e->getMessage());
        }
    }

    private function ensureDirectoryExists(string $directory): bool
    {
        $this->log("Ensuring directory exists: $directory");
        try {
            if (!is_dir($directory)) {
                $this->log("Directory does not exist, creating: $directory");
                $result = mkdir($directory, 0777, true);
                $this->log("Directory creation result: " . ($result ? "success" : "failed"));

                if (!$result) {
                    $this->log("Error details: " . error_get_last()['message'] ?? 'Unknown error');
                    return false;
                }

                $perms = substr(sprintf('%o', fileperms($directory)), -4);
                $this->log("Created directory permissions: $perms");
            }

            $isWritable = is_writable($directory);
            $this->log("Directory writable: " . ($isWritable ? "yes" : "no"));
            return $isWritable;
        } catch (Exception $e) {
            $this->log("Directory creation error: " . $e->getMessage());
            return false;
        }
    }

    public function isRecordingActive(): bool
    {
        $this->log("Checking recording status");
        $exists = file_exists($this->pidFile);
        $this->log("PID file exists: " . ($exists ? "yes" : "no"));

        if ($exists) {
            $pid = trim(file_get_contents($this->pidFile));
            $this->log("Found PID: $pid", $pid);
            $isRunning = shell_exec("ps -p $pid > /dev/null 2>&1") === null;
            $this->log("Process running: " . ($isRunning ? "yes" : "no"), $pid);
            return $isRunning;
        }
        return false;
    }

    public function getRecordingStartTime(): int
    {
        $this->log("Getting recording start time");
        try {
            if (file_exists($this->recordingStartFile)) {
                $time = (int)file_get_contents($this->recordingStartFile);
                $this->log("Found start time: " . date('Y-m-d H:i:s', $time));
                return $time;
            }
            $this->log("No start time file found");
            return 0;
        } catch (Exception $e) {
            $this->log("Error getting start time: " . $e->getMessage());
            return 0;
        }
    }

    public function getCurrentRecordingFile(): ?string
    {
        $this->log("Getting current recording file");
        try {
            if (file_exists($this->currentRecordingFile)) {
                $file = trim(file_get_contents($this->currentRecordingFile));
                $this->log("Found recording file: $file");
                return $file;
            }
            $this->log("No recording file found");
            return null;
        } catch (Exception $e) {
            $this->log("Error getting recording file: " . $e->getMessage());
            return null;
        }
    }

    public function startRecording(string $srtUrl, string $username, $activityLogger): array
    {
        $this->log("\n=== Starting Recording Process ===");
        try {
            $this->log("Input - SRT URL: $srtUrl");
            $this->log("Input - Username: $username");
            $this->log("Environment - Working dir: " . getcwd());
            $this->log("Environment - User: " . get_current_user());
            $this->log("Environment - PHP PID: " . getmypid());

            if (empty($srtUrl)) {
                throw new InvalidArgumentException("SRT URL cannot be empty");
            }

            if ($this->isRecordingActive()) {
                throw new RuntimeException("Recording already in progress");
            }

            if (!$this->isSrtSupported()) {
                throw new RuntimeException("FFmpeg does not support SRT protocol");
            }

            $timestamp = date('Ymd_His');
            $outputFile = "{$this->recordingsDir}/BLIVE_{$timestamp}.mp4";
            $this->log("Output file path: $outputFile");

            if (!is_writable($this->recordingsDir)) {
                throw new RuntimeException("Recordings directory is not writable");
            }

            $command = $this->buildFFmpegCommand($srtUrl, $outputFile);
            $execCommand = "$command > /dev/null 2>&1 & echo $!";
            $this->log("Executing command: $command");

            $pid = trim(shell_exec($execCommand));
            $this->log("Process started with PID: $pid", $pid);

            if (!$pid || !is_numeric($pid)) {
                throw new RuntimeException("Failed to get FFmpeg process ID");
            }

            $this->writeControlFiles($pid, $outputFile);
            $this->logActivity($activityLogger, $username, 'started_recording', basename($outputFile), $pid);
            $this->updateChangeTimestamp();

            $this->log("=== Recording Started Successfully ===", $pid);
            return [
                'success' => true,
                'message' => 'Recording started successfully',
                'start_time' => time(),
                'filename' => basename($outputFile),
                'full_path' => $outputFile
            ];
        } catch (Exception $e) {
            $this->log("Recording failed: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => "Recording failed: " . $e->getMessage()
            ];
        }
    }

    private function buildFFmpegCommand(string $srtUrl, string $outputFile): string
    {
        $command = sprintf(
            'ffmpeg -err_detect ignore_err -i %s -vsync 1 -async 1 -copyts -start_at_zero ' .
            '-c:v libx264 -preset veryfast -crf 23 -c:a aac -b:a 128k -ac 2 -ar 44100 ' .
            '-max_muxing_queue_size 1024 %s',
            escapeshellarg($srtUrl),
            escapeshellarg($outputFile)
        );
        $this->log("Built FFmpeg command: $command");
        return $command;
    }

    private function writeControlFiles(string $pid, string $outputFile): void
    {
        $this->log("Writing control files", $pid);
        $now = time();

        foreach ([
                     $this->pidFile => $pid,
                     $this->currentRecordingFile => $outputFile,
                     $this->recordingStartFile => $now
                 ] as $file => $content) {
            $result = file_put_contents($file, $content, LOCK_EX);
            $this->log("Writing $file: " . ($result !== false ? "success" : "failed"), $pid);
            if ($result === false) {
                throw new RuntimeException("Failed to write $file: " . error_get_last()['message']);
            }
        }
    }

    private function logActivity($activityLogger, string $username, string $action, string $file, ?string $pid = null): void
    {
        if ($activityLogger) {
            try {
                $activityLogger->logActivity($username, $action, $file);
                $this->log("Activity logged: $action for $file by $username", $pid);
            } catch (Exception $e) {
                $this->log("Activity logging failed: " . $e->getMessage(), $pid);
            }
        }
    }

    public function stopRecording(string $username, $activityLogger): array
    {
        $this->log("\n=== Stopping Recording Process ===");
        try {
            if (!$this->isRecordingActive()) {
                $this->log("No active recording found");
                return [
                    'success' => false,
                    'message' => 'No active recording found'
                ];
            }

            $pid = trim(file_get_contents($this->pidFile));
            $currentRecording = $this->getCurrentRecordingFile();
            $this->log("Stopping PID: $pid", $pid);
            $this->log("Recording file: " . ($currentRecording ?: "none"), $pid);

            $this->terminateProcess($pid);
            $this->cleanupRecordingFiles();

            if ($currentRecording) {
                $this->logActivity($activityLogger, $username, 'stopped_recording', basename($currentRecording), $pid);
                $this->updateChangeTimestamp();
            }

            $this->log("=== Recording Stopped Successfully ===", $pid);
            return [
                'success' => true,
                'message' => 'Recording stopped successfully',
                'filename' => $currentRecording ? basename($currentRecording) : ''
            ];
        } catch (Exception $e) {
            $this->log("Stop recording failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Failed to stop recording: " . $e->getMessage()
            ];
        }
    }

    private function terminateProcess(string $pid): void
    {
        $this->log("Attempting to terminate process $pid", $pid);
        $result = shell_exec("kill $pid 2>&1");
        $this->log("Kill result: " . ($result ?: "no output"), $pid);

        if (shell_exec("ps -p $pid > /dev/null 2>&1") !== null) {
            $this->log("Process still running, forcing termination", $pid);
            $forceResult = shell_exec("kill -9 $pid 2>&1");
            $this->log("Force kill result: " . ($forceResult ?: "no output"), $pid);
        }
    }

    private function cleanupRecordingFiles(): bool
    {
        $this->log("Cleaning up control files");
        $success = true;

        foreach ([$this->pidFile, $this->currentRecordingFile, $this->recordingStartFile] as $file) {
            if (file_exists($file)) {
                $result = unlink($file);
                $this->log("Cleanup $file: " . ($result ? "success" : "failed"));
                $success = $success && $result;
            }
        }
        return $success;
    }

    public function deleteRecording(string $filePath, string $username, $activityLogger): bool
    {
        $this->log("Deleting recording: $filePath");
        try {
            if (!file_exists($filePath)) {
                $this->log("File not found");
                return false;
            }

            $this->logActivity($activityLogger, $username, 'deleted_file', basename($filePath));

            $thumbnailFile = $this->thumbnailsDir . '/' . pathinfo(basename($filePath), PATHINFO_FILENAME) . '.jpg';
            $videoDeleted = unlink($filePath);
            $this->log("Video deletion: " . ($videoDeleted ? "success" : "failed"));

            $thumbnailDeleted = !file_exists($thumbnailFile) || unlink($thumbnailFile);
            $this->log("Thumbnail deletion: " . ($thumbnailDeleted ? "success" : "failed"));

            $this->updateChangeTimestamp();
            return $videoDeleted && $thumbnailDeleted;
        } catch (Exception $e) {
            $this->log("Delete failed: " . $e->getMessage());
            return false;
        }
    }

    public function generateThumbnail(string $videoFile, string $thumbnailFile): bool
    {
        $this->log("Generating thumbnail for: $videoFile");
        try {
            if (!file_exists($videoFile)) {
                throw new InvalidArgumentException("Video file does not exist");
            }

            $this->ensureDirectoryExists(dirname($thumbnailFile));
            $timestamps = ['00:00:05', '00:00:10', '00:00:01', '00:00:15', '00:00:20', '00:00:25'];

            foreach ($timestamps as $ts) {
                $command = sprintf(
                    "ffmpeg -i %s -ss %s -vframes 1 -q:v 2 %s > /dev/null 2>&1",
                    escapeshellarg($videoFile),
                    escapeshellarg($ts),
                    escapeshellarg($thumbnailFile)
                );

                $this->log("Trying thumbnail at $ts");
                shell_exec($command);

                clearstatcache();
                if (file_exists($thumbnailFile) && filesize($thumbnailFile) > 0) {
                    chmod($thumbnailFile, 0644);
                    $this->log("Thumbnail generated successfully at $ts");
                    return true;
                }
            }

            $this->log("All thumbnail attempts failed");
            return false;
        } catch (Exception $e) {
            $this->log("Thumbnail generation failed: " . $e->getMessage());
            return false;
        }
    }

    public function getVideoDuration(string $videoFile): string
    {
        $this->log("Getting duration for: $videoFile");
        try {
            if (!file_exists($videoFile)) {
                $this->log("Video file not found");
                return "Unknown";
            }

            $command = "ffmpeg -i " . escapeshellarg($videoFile) . " 2>&1";
            $output = shell_exec($command);
            $this->log("FFmpeg duration output: " . substr($output, 0, 1000)); // Limited for log

            if (preg_match("/Duration: (.*?),/", $output, $matches)) {
                $duration = trim($matches[1]);
                $this->log("Found duration: $duration");
                return $duration;
            }

            $this->log("Duration not found in output");
            return "Unknown";
        } catch (Exception $e) {
            $this->log("Duration fetch failed: " . $e->getMessage());
            return "Unknown";
        }
    }

    public function cleanFFmpegLog(int $days = 14): bool
    {
        $this->log("Cleaning FFmpeg log, keeping $days days");
        try {
            if (!file_exists($this->logFile)) {
                $this->log("No log file exists");
                return true;
            }

            $content = file_get_contents($this->logFile);
            if ($content === false) {
                throw new RuntimeException("Failed to read log file");
            }

            $lines = explode("\n", $content);
            $cutoffDate = strtotime("-{$days} days");
            $this->log("Cutoff date: " . date('Y-m-d H:i:s', $cutoffDate));

            $keptLines = array_filter($lines, function ($line) use ($cutoffDate) {
                if (empty(trim($line))) {
                    return true;
                }

                if (preg_match('/^\[([\d-]+ [\d:]+)\]/', $line, $matches)) {
                    $lineDate = strtotime($matches[1]);
                    $keep = $lineDate >= $cutoffDate;
                    $this->log("Line date: {$matches[1]}, Keep: " . ($keep ? "yes" : "no"));
                    return $keep;
                }
                return true;
            });

            $newContent = implode("\n", $keptLines);
            $result = file_put_contents($this->logFile, $newContent, LOCK_EX);
            $this->log("Log cleanup: " . ($result !== false ? "success" : "failed"));
            return $result !== false;
        } catch (Exception $e) {
            $this->log("Log cleanup failed: " . $e->getMessage());
            return false;
        }
    }

    public function getFFmpegLog(bool $reverse = true): string
    {
        $this->log("Retrieving FFmpeg log");
        try {
            $this->cleanFFmpegLog();

            if (!file_exists($this->logFile)) {
                $this->log("No log file exists");
                return "No FFmpeg log entries found.";
            }

            $content = file_get_contents($this->logFile);
            if ($content === false) {
                throw new RuntimeException("Failed to read log file");
            }

            if ($reverse) {
                $lines = explode("\n", $content);
                $content = implode("\n", array_reverse($lines));
                $this->log("Log reversed");
            }

            $this->log("Log retrieved, size: " . strlen($content) . " bytes");
            return $content;
        } catch (Exception $e) {
            $this->log("Log retrieval failed: " . $e->getMessage());
            return "Error retrieving log: " . $e->getMessage();
        }
    }

    public function updateChangeTimestamp(): bool
    {
        $this->log("Updating change timestamp");
        $result = file_put_contents($this->lastChangeFile, time(), LOCK_EX);
        $this->log("Timestamp update: " . ($result !== false ? "success" : "failed"));
        return $result !== false;
    }

    public function isFFmpegAvailable(): bool
    {
        $this->log("Checking FFmpeg availability");
        $result = shell_exec('which ffmpeg 2>/dev/null');
        $this->log("FFmpeg path: " . ($result ?: "not found"));
        return !empty($result);
    }

    public function isSrtSupported(): bool
    {
        $this->log("Checking SRT support");
        $result = shell_exec('ffmpeg -protocols 2>&1 | grep srt');
        $this->log("SRT support: " . ($result ? "yes" : "no"));
        return !empty($result);
    }

    public function getFFmpegVersion(): string
    {
        $this->log("Getting FFmpeg version");
        $result = shell_exec('ffmpeg -version 2>&1 | head -n 1');
        $version = trim($result ?? 'Unknown');
        $this->log("FFmpeg version: $version");
        return $version;
    }
}