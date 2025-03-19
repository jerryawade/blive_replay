<?php
/**
 * FFmpegService.php
 *
 * A service class to handle all FFmpeg-related operations for the BLIVE RePlay application.
 * This service encapsulates video recording, thumbnail generation, and related operations.
 * Enhanced with more robust error handling and comprehensive logging.
 *
 * @version 1.1.0
 * @date 2025-03-18
 */

class FFmpegService
{
    // File paths for tracking recording status
    private string $pidFile = 'ffmpeg_pid.txt';
    private string $currentRecordingFile = 'current_recording.txt';
    private string $recordingStartFile = 'recording_start.txt';
    private string $lastChangeFile = 'last_change.txt';
    private string $logFile = 'logs/ffmpeg.log';

    // Add a backup PID file to prevent lost processes
    private string $backupPidFile = 'backup_ffmpeg_pid.txt';

    // Monitoring files
    private string $ffmpegStatsFile = 'logs/ffmpeg_stats.json';
    private string $recordingHealthFile = 'logs/recording_health.json';

    // Directories
    private string $recordingsDir;
    private string $thumbnailsDir;

    // Internal configuration
    private int $maxRetries = 3;
    private int $retryDelay = 2; // seconds
    private int $thumbnailQuality = 2; // 1-31, lower is better
    private array $ffmpegEnvVars = [];
    private bool $debugMode = false;

    /**
     * Constructor with optional directory configuration
     *
     * @param string $recordingsDir Directory for recorded videos
     * @param string $thumbnailsDir Directory for video thumbnails
     * @param bool $debugMode Enable additional debug logging
     */
    public function __construct(string $recordingsDir = 'recordings', string $thumbnailsDir = 'thumbnails', bool $debugMode = false)
    {
        $this->recordingsDir = $recordingsDir;
        $this->thumbnailsDir = $thumbnailsDir;
        $this->debugMode = $debugMode;

        // Log the initialization
        $this->log("===== FFmpegService initialized =====");
        $this->log("PHP Version: " . phpversion());
        $this->log("Operating System: " . php_uname());
        $this->log("Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'));
        $this->log("User running PHP: " . get_current_user());
        $this->log("Current working directory: " . getcwd());
        $this->log("Recordings directory: $recordingsDir");
        $this->log("Thumbnails directory: $thumbnailsDir");
        $this->log("Debug mode: " . ($debugMode ? 'Enabled' : 'Disabled'));

        // Check FFmpeg installation
        $this->checkFFmpegInstallation();

        // Ensure directories exist
        $this->ensureDirectoryExists($recordingsDir);
        $this->ensureDirectoryExists($thumbnailsDir);
        $this->ensureDirectoryExists(dirname($this->logFile));

        // Set up environment variables for FFmpeg
        $this->setupEnvironment();

        // Check for orphaned recordings (processes that might be running but not tracked)
        $this->checkForOrphanedRecordings();
    }

    /**
     * Set up environment variables for FFmpeg
     */
    private function setupEnvironment(): void
    {
        // Set default environment variables to optimize FFmpeg
        $this->ffmpegEnvVars = [
            'FFREPORT' => 'file=logs/ffmpeg_detailed.log:level=32', // Enable detailed FFmpeg logging
            'TMPDIR' => sys_get_temp_dir(), // Ensure temp directory is set
        ];

        $this->log("FFmpeg environment variables configured");
        if ($this->debugMode) {
            $this->log("FFmpeg environment: " . json_encode($this->ffmpegEnvVars));
        }
    }

    /**
     * Check FFmpeg installation and capabilities
     */
    private function checkFFmpegInstallation(): void
    {
        $this->log("Checking FFmpeg installation...");

        // Check if FFmpeg is available
        if (!$this->isFFmpegAvailable()) {
            $this->log("WARNING: FFmpeg not found in path!", 'error');
            return;
        }

        // Get FFmpeg version
        $ffmpegVersion = $this->getFFmpegVersion();
        $this->log("FFmpeg version: $ffmpegVersion");

        // Check if FFmpeg supports SRT protocol
        $this->log("Checking SRT protocol support...");
        $srtSupported = $this->isSrtSupported();
        if ($srtSupported) {
            $this->log("SRT protocol is supported");
        } else {
            $this->log("WARNING: SRT protocol is NOT supported. Stream recording may fail!", 'error');
        }

        // Check libx264 support (for H.264 encoding)
        $this->log("Checking libx264 support...");
        $command = 'ffmpeg -codecs 2>&1 | grep libx264';
        $output = shell_exec($command);

        if ($output) {
            $this->log("libx264 codec is supported");
        } else {
            $this->log("WARNING: libx264 codec may not be supported. Check FFmpeg compilation.", 'warning');
        }

        // Check AAC support (for audio encoding)
        $this->log("Checking AAC support...");
        $command = 'ffmpeg -codecs 2>&1 | grep aac';
        $output = shell_exec($command);

        if ($output) {
            $this->log("AAC codec is supported");
        } else {
            $this->log("WARNING: AAC codec may not be supported. Check FFmpeg compilation.", 'warning');
        }
    }

    /**
     * Check for orphaned recordings
     *
     * Sometimes FFmpeg processes might be left running but not tracked by our system
     * This method checks for such processes and attempts to clean them up
     */
    private function checkForOrphanedRecordings(): void
    {
        $this->log("Checking for orphaned recording processes...");

        // Check backup PID file
        if (file_exists($this->backupPidFile)) {
            $backupPid = file_get_contents($this->backupPidFile);
            $this->log("Found backup PID file with PID: $backupPid");

            // Check if normal PID file exists
            if (!file_exists($this->pidFile)) {
                $this->log("Main PID file missing but backup exists - possible orphaned recording");

                // Check if process is still running
                $command = "ps -p $backupPid -o comm= 2>/dev/null";
                $output = trim(shell_exec($command));

                if ($output && strpos($output, 'ffmpeg') !== false) {
                    $this->log("Found orphaned FFmpeg process ($backupPid) - attempting to terminate");
                    $killResult = shell_exec("kill $backupPid 2>&1");
                    $this->log("Kill result: " . ($killResult ?: 'No output (likely success)'));
                }

                // Clean up backup PID file
                unlink($this->backupPidFile);
                $this->log("Removed backup PID file");
            }
        }

        // Check for FFmpeg processes that might be ours
        $command = "ps aux | grep -v grep | grep -v 'ffprobe' | grep 'ffmpeg.*" . escapeshellarg($this->recordingsDir) . "' | awk '{print $2}'";
        $output = trim(shell_exec($command));

        if ($output) {
            $pids = explode("\n", $output);
            $this->log("Found " . count($pids) . " potential FFmpeg recording processes");

            foreach ($pids as $pid) {
                $pid = trim($pid);
                if (!$pid) continue;

                // Check if this PID matches our active recording
                $our_pid = file_exists($this->pidFile) ? trim(file_get_contents($this->pidFile)) : '';

                if ($pid !== $our_pid) {
                    $this->log("Found orphaned FFmpeg process with PID $pid - checking command line");

                    // Get more info about the process
                    $cmdLine = trim(shell_exec("ps -p $pid -o args= 2>/dev/null"));
                    $this->log("Process command line: $cmdLine");

                    // If it contains our recording directory, it's likely our orphaned process
                    if (strpos($cmdLine, $this->recordingsDir) !== false) {
                        $this->log("Confirmed orphaned FFmpeg process - attempting to terminate");
                        $killResult = shell_exec("kill $pid 2>&1");
                        $this->log("Kill result: " . ($killResult ?: 'No output (likely success)'));

                        // Double-check if the process was terminated
                        sleep(1);
                        $stillRunning = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));

                        if ($stillRunning) {
                            $this->log("Process still running, attempting force kill");
                            $forceKillResult = shell_exec("kill -9 $pid 2>&1");
                            $this->log("Force kill result: " . ($forceKillResult ?: 'No output (likely success)'));
                        }
                    }
                }
            }
        } else {
            $this->log("No orphaned FFmpeg recording processes found");
        }
    }

    /**
     * Log FFmpeg-related messages with severity level
     *
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error, debug)
     */
    public function log(string $message, string $level = 'info'): void
    {
        // Ensure logs directory exists
        $this->ensureDirectoryExists(dirname($this->logFile));

        $timestamp = date('Y-m-d H:i:s');
        $formattedMsg = "[$timestamp] [$level] $message\n";

        // Write to log file with error checking
        $result = file_put_contents($this->logFile, $formattedMsg, FILE_APPEND);

        // If debug mode is enabled, also output to PHP error log
        if ($this->debugMode || $level === 'error' || $level === 'warning') {
            error_log("FFmpegService: $level - $message");
        }

        // Try to recover from file writing failures
        if ($result === false) {
            // Try again with directory creation
            $this->ensureDirectoryExists(dirname($this->logFile), true);
            $retryResult = file_put_contents($this->logFile, $formattedMsg, FILE_APPEND);

            if ($retryResult === false) {
                // Last resort: log to PHP error log
                error_log("FFmpegService: Failed to write to log file! Original message: $level - $message");
            }
        }
    }

    /**
     * Ensure the specified directory exists with detailed logging and retry logic
     *
     * @param string $directory Directory path
     * @param bool $forceCreate Force directory creation even if it appears to exist
     * @return bool Success status
     */
    private function ensureDirectoryExists(string $directory, bool $forceCreate = false): bool
    {
        if (empty($directory)) {
            $this->log("Warning: Empty directory path provided", 'warning');
            return false;
        }

        // Check if directory needs to be created
        if (!is_dir($directory) || $forceCreate) {
            $this->log("Creating directory: $directory");

            // Try multiple times with increasing permissions
            $attempts = 0;
            $created = false;
            $modes = [0755, 0775, 0777]; // Try more restrictive permissions first

            while (!$created && $attempts < count($modes)) {
                $mode = $modes[$attempts];
                $this->log("Attempt " . ($attempts + 1) . " to create directory with mode: " . decoct($mode));

                $result = mkdir($directory, $mode, true);

                if ($result) {
                    $created = true;
                    $this->log("Successfully created directory with mode: " . decoct($mode));
                    $this->log("Directory permissions: " . substr(sprintf('%o', fileperms($directory)), -4));

                    // Check if directory is writable
                    if (is_writable($directory)) {
                        $this->log("Directory is writable: $directory");
                    } else {
                        $this->log("WARNING: Directory is not writable: $directory", 'warning');

                        // Try to fix permissions
                        $chmodResult = chmod($directory, 0777);
                        $this->log("Chmod result: " . ($chmodResult ? "success" : "failed"));
                    }
                } else {
                    $this->log("Failed to create directory with mode: " . decoct($mode), 'warning');
                    $error = error_get_last();
                    if ($error) {
                        $this->log("Error: " . $error['message'], 'error');
                    }
                }

                $attempts++;
            }

            return $created;
        }

        // Check if existing directory is writable
        if (!is_writable($directory)) {
            $this->log("WARNING: Directory exists but is not writable: $directory", 'warning');

            // Try to fix permissions
            $this->log("Attempting to fix permissions for: $directory");
            $chmodResult = chmod($directory, 0777);
            $this->log("Chmod result: " . ($chmodResult ? "success" : "failed"));

            return is_writable($directory);
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
        $hasMainPidFile = file_exists($this->pidFile);
        $hasBackupPidFile = file_exists($this->backupPidFile);

        // If neither file exists, recording is not active
        if (!$hasMainPidFile && !$hasBackupPidFile) {
            return false;
        }

        // If main PID file exists, check if the process is running
        if ($hasMainPidFile) {
            $pid = trim(file_get_contents($this->pidFile));

            if (empty($pid)) {
                $this->log("PID file exists but is empty", 'warning');
                $this->cleanupRecordingFiles();
                return false;
            }

            // Check if the process is running
            $command = "ps -p $pid -o pid= 2>/dev/null";
            $output = trim(shell_exec($command));

            if (empty($output)) {
                $this->log("PID file exists but process $pid is not running", 'warning');
                $this->cleanupRecordingFiles();
                return false;
            }

            return true;
        }

        // If only backup PID file exists, check if the process is running and try to restore main PID file
        if ($hasBackupPidFile) {
            $pid = trim(file_get_contents($this->backupPidFile));

            if (empty($pid)) {
                $this->log("Backup PID file exists but is empty", 'warning');
                unlink($this->backupPidFile);
                return false;
            }

            // Check if the process is running
            $command = "ps -p $pid -o pid= 2>/dev/null";
            $output = trim(shell_exec($command));

            if (empty($output)) {
                $this->log("Backup PID file exists but process $pid is not running", 'warning');
                unlink($this->backupPidFile);
                return false;
            }

            // Process is running but main PID file is missing - restore it
            $this->log("Found active recording process ($pid) with missing main PID file - restoring", 'warning');
            file_put_contents($this->pidFile, $pid);

            // Check if we have current recording file
            if (!file_exists($this->currentRecordingFile)) {
                $this->log("Current recording file is missing - attempting to find current recording file");

                // Try to find the recording file by looking at the process command line
                $command = "ps -p $pid -o args= 2>/dev/null";
                $cmdLine = trim(shell_exec($command));

                if (preg_match('/\s([^\s]+\.mp4)(?:\s|$)/', $cmdLine, $matches)) {
                    $recordingFile = $matches[1];
                    $this->log("Found recording file from process command line: $recordingFile");
                    file_put_contents($this->currentRecordingFile, $recordingFile);
                }
            }

            // Check if we have start time file
            if (!file_exists($this->recordingStartFile)) {
                $this->log("Recording start time file is missing - using process start time");

                // Use process start time as estimate
                $command = "ps -p $pid -o lstart= 2>/dev/null";
                $startTimeStr = trim(shell_exec($command));
                $startTime = strtotime($startTimeStr);

                if ($startTime) {
                    $this->log("Using process start time: $startTimeStr");
                    file_put_contents($this->recordingStartFile, $startTime);
                } else {
                    $this->log("Failed to determine process start time - using current time");
                    file_put_contents($this->recordingStartFile, time());
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Check if any recording is active (primary or backup)
     *
     * @return bool True if any recording is active
     */
    public function isAnyRecordingActive(): bool
    {
        return $this->isRecordingActive();
    }

    /**
     * Get the start time of the current recording
     *
     * @return int Unix timestamp or 0 if not recording
     */
    public function getRecordingStartTime(): int
    {
        if (!$this->isRecordingActive()) {
            return 0;
        }

        if (file_exists($this->recordingStartFile)) {
            $startTime = (int)file_get_contents($this->recordingStartFile);

            // Validate start time
            if ($startTime <= 0) {
                $this->log("Invalid start time in file: $startTime", 'warning');
                return 0;
            }

            return $startTime;
        }

        return 0;
    }

    /**
     * Get the current recording file path
     *
     * @return string|null Path to the current recording file or null if not recording
     */
    public function getCurrentRecordingFile(): ?string
    {
        if (!$this->isRecordingActive()) {
            return null;
        }

        if (file_exists($this->currentRecordingFile)) {
            $filePath = trim(file_get_contents($this->currentRecordingFile));

            // Validate file path
            if (empty($filePath)) {
                $this->log("Empty current recording file path", 'warning');
                return null;
            }

            return $filePath;
        }

        return null;
    }

    /**
     * Start a new recording with enhanced error handling and retries
     *
     * @param string $srtUrl SRT URL to record from
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @param array $options Additional options for FFmpeg
     * @return array Result with success status, message, and recording details
     */
    public function startRecording(string $srtUrl, string $username, $activityLogger, array $options = []): array
    {
        $this->log("\n====================================================================");
        $this->log("=== Starting Recording Process (" . date('Y-m-d H:i:s') . ") ===");
        $this->log("====================================================================");
        $this->log("Current working directory: " . getcwd());
        $this->log("User running PHP: " . get_current_user());
        $this->log("PHP process ID: " . getmypid());
        $this->log("PHP memory limit: " . ini_get('memory_limit'));
        $this->log("PHP execution time limit: " . ini_get('max_execution_time'));

        // Check if recording is already active
        if ($this->isRecordingActive()) {
            $this->log("Cannot start recording: Already recording", 'error');

            // Get current recording file
            $currentFile = $this->getCurrentRecordingFile() ?: 'unknown';
            $startTime = $this->getRecordingStartTime();
            $formattedStartTime = $startTime ? date('Y-m-d H:i:s', $startTime) : 'unknown';

            $this->log("Current recording: $currentFile (started: $formattedStartTime)");

            return [
                'success' => false,
                'message' => 'Recording is already in progress',
                'current_file' => $currentFile,
                'start_time' => $startTime
            ];
        }

        // Log settings
        $this->log("SRT URL: $srtUrl");
        $this->log("Username: $username");
        $this->log("Options: " . json_encode($options));

        // Sanitize and validate SRT URL
        if (empty(trim($srtUrl))) {
            $this->log("Error: Empty SRT URL provided", 'error');
            return [
                'success' => false,
                'message' => 'SRT URL cannot be empty'
            ];
        }

        // Validate URL format
        if (strpos($srtUrl, 'srt://') !== 0) {
            $this->log("Warning: SRT URL does not start with 'srt://' protocol", 'warning');
        }

        // Generate output file path
        $timestamp = date('Ymd_His');
        $outputFile = $this->recordingsDir . "/BLIVE_{$timestamp}.mp4";
        $this->log("Output file will be: " . $outputFile);

        // Directory checks
        $this->log("Recordings directory exists: " . (is_dir($this->recordingsDir) ? 'yes' : 'no'));
        $this->log("Recordings directory writable: " . (is_writable($this->recordingsDir) ? 'yes' : 'no'));

        // Create recordings directory if it doesn't exist
        if (!$this->ensureDirectoryExists($this->recordingsDir)) {
            $this->log("Failed to create recordings directory", 'error');
            return [
                'success' => false,
                'message' => 'Failed to create recordings directory'
            ];
        }

        // FFmpeg version check
        $ffmpegVersion = $this->getFFmpegVersion();
        $this->log("FFmpeg version: " . $ffmpegVersion);

        if (empty($ffmpegVersion)) {
            $this->log("FFmpeg not found or not executable", 'error');
            return [
                'success' => false,
                'message' => 'FFmpeg not found or not executable'
            ];
        }

        // Command preparation with customizable options
        $defaultOptions = [
            'video_codec' => 'libx264',
            'preset' => 'veryfast',
            'crf' => 23,
            'audio_codec' => 'aac',
            'audio_bitrate' => '128k',
            'audio_channels' => 2,
            'audio_rate' => 44100,
            'max_muxing_queue_size' => 1024,
            'timeout' => 10,  // Timeout in seconds for connection
            'retries' => $this->maxRetries
        ];

        // Merge default options with provided options
        $options = array_merge($defaultOptions, $options);

        // Build the FFmpeg command
        $command = 'ffmpeg';

        // Add timeout
        if ($options['timeout'] > 0) {
            $command .= ' -timeout ' . (int)$options['timeout'] * 1000000; // microseconds
        }

        // Add error detection and handling
        $command .= ' -err_detect ignore_err';

        // Add input options
        $command .= ' -i ' . escapeshellarg($srtUrl);

        // Add sync options
        $command .= ' -vsync 1';
        $command .= ' -async 1';
        $command .= ' -copyts';
        $command .= ' -start_at_zero';

        // Add video codec options
        $command .= ' -c:v ' . escapeshellarg($options['video_codec']);
        $command .= ' -preset ' . escapeshellarg($options['preset']);
        $command .= ' -crf ' . (int)$options['crf'];

        // Add audio codec options
        $command .= ' -c:a ' . escapeshellarg($options['audio_codec']);
        $command .= ' -b:a ' . escapeshellarg($options['audio_bitrate']);
        $command .= ' -ac ' . (int)$options['audio_channels'];
        $command .= ' -ar ' . (int)$options['audio_rate'];

        // Add muxing options
        $command .= ' -max_muxing_queue_size ' . (int)$options['max_muxing_queue_size'];

        // Add logging options for FFmpeg to create its own log
        $dateStr = date('Y-m-d_H-i-s');
        $command .= ' -report';

        // Add output file
        $command .= ' ' . escapeshellarg($outputFile);

        // Build exec command to run in background and return PID
        $execCommand = $command . ' > /dev/null 2>&1 & echo $!';

        $this->log("FFmpeg command: " . $command);

        // Set environment variables for FFmpeg
        $env = '';
        foreach ($this->ffmpegEnvVars as $key => $value) {
            $env .= "$key=" . escapeshellarg($value) . " ";
        }

        // Execute command with error capture
        $this->log("Executing FFmpeg command...");
        $pid = shell_exec($env . $execCommand . ' 2>&1');
        $this->log("FFmpeg command executed. PID: " . ($pid ? $pid : 'no pid returned'));
        $this->log("Full command output: " . ($pid ? $pid : 'no output'));

        // Test if process is running
        if ($pid) {
            $pid = trim($pid); // Remove any whitespace

            // Check if the PID is valid (numeric)
            if (!is_numeric($pid)) {
                $this->log("Invalid PID returned: $pid", 'error');
                return [
                    'success' => false,
                    'message' => 'Invalid PID returned from FFmpeg'
                ];
            }

            $psCommand = "ps -p " . intval($pid) . " > /dev/null 2>&1";
            $processCheckExitCode = 0;
            system($psCommand, $processCheckExitCode);
            $processRunning = ($processCheckExitCode === 0);

            $this->log("Process check result: " . ($processRunning ? "Process running" : "Process not found"));

            if (!$processRunning) {
                $this->log("Process not running immediately after starting - checking for errors", 'error');

                // Try to get FFmpeg error output
                $errorLog = "logs/ffmpeg_error_$dateStr.log";
                if (file_exists($errorLog)) {
                    $errorContent = file_get_contents($errorLog);
                    $this->log("FFmpeg error log: $errorContent", 'error');
                } else {
                    $this->log("No FFmpeg error log found", 'error');
                }

                $this->log("=== Recording Process Failed ===\n");
                return [
                    'success' => false,
                    'message' => 'FFmpeg process failed to start'
                ];
            }

            // Wait a moment to ensure FFmpeg is properly initialized
            sleep(1);

            // Check again to make sure the process is still running
            $psCommand = "ps -p " . intval($pid) . " > /dev/null 2>&1";
            $processCheckExitCode = 0;
            system($psCommand, $processCheckExitCode);
            $processRunning = ($processCheckExitCode === 0);

            if (!$processRunning) {
                $this->log("FFmpeg process terminated shortly after starting", 'error');

                // Try to get FFmpeg error output
                $errorLog = "logs/ffmpeg_error_$dateStr.log";
                if (file_exists($errorLog)) {
                    $errorContent = file_get_contents($errorLog);
                    $this->log("FFmpeg error log: $errorContent", 'error');
                }

                return [
                    'success' => false,
                    'message' => 'FFmpeg process terminated shortly after starting'
                ];
            }
        } else {
            $this->log("=== Recording Process Failed - No PID returned ===\n", 'error');
            return [
                'success' => false,
                'message' => 'Failed to start FFmpeg process - no PID returned'
            ];
        }

        // Write control files with error checking
        $this->log("Writing control files...");
        try {
            $now = time();

            // Save PID to both main and backup files
            $pidWritten = file_put_contents($this->pidFile, $pid);
            $this->log("PID file written: " . ($pidWritten !== false ? 'yes' : 'no'));
            if ($pidWritten === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                $this->log("Failed to write PID file. Error: $errorMsg", 'error');

                // Try to kill the process since we couldn't save the PID
                $this->log("Attempting to kill process $pid as PID file could not be written");
                shell_exec("kill $pid 2>&1");

                return [
                    'success' => false,
                    'message' => 'Failed to write PID file'
                ];
            }

            // Write backup PID file
            $backupPidWritten = file_put_contents($this->backupPidFile, $pid);
            $this->log("Backup PID file written: " . ($backupPidWritten !== false ? 'yes' : 'no'));
            if ($backupPidWritten === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                $this->log("Failed to write backup PID file. Error: $errorMsg", 'warning');
                // Continue anyway since we have the main PID file
            }

            $outputWritten = file_put_contents($this->currentRecordingFile, $outputFile);
            $this->log("Current recording file written: " . ($outputWritten !== false ? 'yes' : 'no'));
            if ($outputWritten === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                $this->log("Failed to write current recording file. Error: $errorMsg", 'error');

                // Try to kill the process and clean up
                $this->log("Attempting to kill process $pid as recording file could not be written");
                shell_exec("kill $pid 2>&1");

                if (file_exists($this->pidFile)) {
                    unlink($this->pidFile);
                }

                if (file_exists($this->backupPidFile)) {
                    unlink($this->backupPidFile);
                }

                return [
                    'success' => false,
                    'message' => 'Failed to write recording file'
                ];
            }

            $timeWritten = file_put_contents($this->recordingStartFile, $now);
            $this->log("Start time file written: " . ($timeWritten !== false ? 'yes' : 'no'));
            if ($timeWritten === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                $this->log("Failed to write start time file. Error: $errorMsg", 'error');

                // Try to continue anyway since this is not critical
                $this->log("Continuing with recording despite start time file error");
            }

            // Update change timestamp for all clients
            $changeTimestampWritten = file_put_contents($this->lastChangeFile, time());
            $this->log("Change timestamp file written: " . ($changeTimestampWritten !== false ? 'yes' : 'no'));
            if ($changeTimestampWritten === false) {
                $this->log("Failed to update change timestamp file", 'warning');
                // Not critical, continue anyway
            }

            // Create initial recording health data
            $healthData = [
                'pid' => $pid,
                'start_time' => $now,
                'command' => $command,
                'last_check' => $now,
                'status' => 'started',
                'checks' => [],
                'errors' => []
            ];

            file_put_contents($this->recordingHealthFile, json_encode($healthData, JSON_PRETTY_PRINT));
            $this->log("Recording health data initialized");

        } catch (Exception $e) {
            $this->log("Error writing control files: " . $e->getMessage(), 'error');
            $this->log("Error trace: " . $e->getTraceAsString(), 'error');

            // Try to kill the process since we had an error
            if (!empty($pid)) {
                $this->log("Attempting to kill process $pid due to control file error");
                shell_exec("kill $pid 2>&1");
            }

            // Clean up any created files
            $this->cleanupRecordingFiles();

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }

        // Log activity
        if ($activityLogger) {
            try {
                $activityLogger->logActivity($username, 'started_recording', basename($outputFile));
                $this->log("Activity logged successfully for user: $username");
            } catch (Exception $e) {
                $this->log("Warning: Failed to log activity: " . $e->getMessage(), 'warning');
                // Non-critical, continue anyway
            }
        }

        // Start health monitoring in the background if supported
        if (function_exists('exec')) {
            $this->log("Starting health monitoring for recording process");
            $monitorCommand = "php health_monitor.php $pid > /dev/null 2>&1 &";
            exec($monitorCommand);
        }

        $this->log("=== Recording Process Started Successfully ===\n");

        return [
            'success' => true,
            'message' => 'Recording started successfully',
            'start_time' => $now,
            'filename' => basename($outputFile),
            'full_path' => $outputFile,
            'pid' => $pid
        ];
    }

    /**
     * Stop the current recording with enhanced error handling
     *
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @return array Result with success status and message
     */
    public function stopRecording(string $username, $activityLogger): array
    {
        $this->log("\n====================================================================");
        $this->log("=== Stopping Recording Process (" . date('Y-m-d H:i:s') . ") ===");
        $this->log("====================================================================");
        $this->log("User initiating stop: $username");

        if (!$this->isRecordingActive()) {
            $this->log("No active recording found", 'warning');
            return [
                'success' => false,
                'message' => 'No active recording found'
            ];
        }

        // Get recording information before stopping
        $pid = file_exists($this->pidFile) ? trim(file_get_contents($this->pidFile)) : '';
        $backupPid = file_exists($this->backupPidFile) ? trim(file_get_contents($this->backupPidFile)) : '';
        $currentRecording = file_exists($this->currentRecordingFile) ? file_get_contents($this->currentRecordingFile) : '';
        $startTime = $this->getRecordingStartTime();

        $this->log("Found PID: " . $pid);
        $this->log("Backup PID: " . $backupPid);
        $this->log("Current recording file: " . $currentRecording);
        $this->log("Recording start time: " . ($startTime ? date('Y-m-d H:i:s', $startTime) : 'unknown'));

        // Recording duration
        $duration = $startTime ? (time() - $startTime) : 0;
        $formattedDuration = $this->formatDuration($duration);
        $this->log("Recording duration: $formattedDuration ($duration seconds)");

        // Check if recording file exists and has content
        if (!empty($currentRecording) && file_exists($currentRecording)) {
            $fileSize = filesize($currentRecording);
            $this->log("Recording file exists. Size: " . $this->formatFileSize($fileSize));
        } else if (!empty($currentRecording)) {
            $this->log("Warning: Recording file not found: $currentRecording", 'warning');
        }

        // Use the main PID if available, otherwise use backup
        $pidToStop = !empty($pid) ? $pid : $backupPid;

        if (empty($pidToStop)) {
            $this->log("Error: No valid PID found to stop", 'error');

            // Clean up anyway
            $this->cleanupRecordingFiles();

            return [
                'success' => false,
                'message' => 'No valid recording process found to stop'
            ];
        }

        $this->log("Attempting to stop process $pidToStop");

        // Try graceful termination first with multiple signals
        $signals = [
            'TERM' => 15,  // Standard termination
            'INT' => 2,    // Interrupt (like Ctrl+C)
            'QUIT' => 3,   // Quit
            'KILL' => 9    // Kill (last resort)
        ];

        $terminated = false;

        foreach ($signals as $signalName => $signalNum) {
            if ($terminated) break;

            $this->log("Sending $signalName signal to process $pidToStop");
            $killResult = shell_exec("kill -$signalNum $pidToStop 2>&1");
            $this->log("Kill command result: " . ($killResult ? $killResult : 'no output (likely success)'));

            // Wait a moment for process to terminate
            sleep(1);

            // Check if process is still running
            $command = "ps -p $pidToStop -o pid= 2>/dev/null";
            $output = trim(shell_exec($command));

            if (empty($output)) {
                $this->log("Process $pidToStop successfully terminated with $signalName signal");
                $terminated = true;
            } else {
                $this->log("Process $pidToStop still running after $signalName signal", $signalName === 'KILL' ? 'error' : 'warning');
            }
        }

        if (!$terminated) {
            $this->log("Failed to terminate process $pidToStop after trying all signals", 'error');

            // Continue with cleanup anyway
            $this->log("Proceeding with cleanup despite process termination failure");
        }

        // Clean up files
        $cleanupResult = $this->cleanupRecordingFiles();
        $this->log("Control files cleanup: " . ($cleanupResult ? "success" : "failed"));

        // Add a delay to ensure any buffered data is written to the file
        $this->log("Waiting for file system to stabilize...");
        sleep(2);

        // Check if the recording file exists and log its final details
        if (!empty($currentRecording) && file_exists($currentRecording)) {
            $finalFileSize = filesize($currentRecording);
            $this->log("Final recording file size: " . $this->formatFileSize($finalFileSize));

            // Check if file size is very small, which might indicate a failed recording
            if ($finalFileSize < 1024 * 10) { // Less than 10 KB
                $this->log("Warning: Recording file is very small, might be incomplete", 'warning');
            }

            // Generate thumbnail in the background
            if (function_exists('exec')) {
                $thumbnailFile = $this->thumbnailsDir . '/' . pathinfo(basename($currentRecording), PATHINFO_FILENAME) . '.jpg';
                $this->log("Generating thumbnail in background: $thumbnailFile");
                $thumbCommand = "php -r \"include 'FFmpegService.php'; \$service = new FFmpegService(); \$service->generateThumbnail('$currentRecording', '$thumbnailFile');\" > /dev/null 2>&1 &";
                exec($thumbCommand);
            }

            // Log activity
            if ($activityLogger) {
                try {
                    $activityLogger->logActivity($username, 'stopped_recording', basename($currentRecording));
                    $this->log("Activity logged successfully for user: $username");
                } catch (Exception $e) {
                    $this->log("Warning: Failed to log activity: " . $e->getMessage(), 'warning');
                }
            }

            // Update change timestamp for all clients
            file_put_contents($this->lastChangeFile, time());

            $this->log("=== Stop Recording Process Complete ===\n");

            return [
                'success' => true,
                'message' => 'Recording stopped successfully',
                'filename' => basename($currentRecording),
                'full_path' => $currentRecording,
                'duration' => $duration,
                'formatted_duration' => $formattedDuration,
                'file_size' => $finalFileSize,
                'formatted_size' => $this->formatFileSize($finalFileSize)
            ];
        }

        $this->log("=== Stop Recording Process Complete with no recording file ===\n");

        return [
            'success' => true,
            'message' => 'Recording process stopped, but no recording file was found',
            'filename' => !empty($currentRecording) ? basename($currentRecording) : '',
            'duration' => $duration,
            'formatted_duration' => $formattedDuration
        ];
    }

    /**
     * Format duration in HH:MM:SS format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Format file size in human-readable format
     *
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Clean up recording control files with enhanced logging
     *
     * @return bool Success status
     */
    private function cleanupRecordingFiles(): bool
    {
        $this->log("Cleaning up recording control files");
        $success = true;

        $files = [
            'PID file' => $this->pidFile,
            'Backup PID file' => $this->backupPidFile,
            'Current recording file' => $this->currentRecordingFile,
            'Recording start file' => $this->recordingStartFile
        ];

        foreach ($files as $description => $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $this->log("$description content before deletion: " . $content);

                $unlinkResult = unlink($file);
                $this->log("$description cleanup: " . ($unlinkResult ? "success" : "failed"));

                if (!$unlinkResult) {
                    $error = error_get_last();
                    $errorMsg = $error ? $error['message'] : 'Unknown error';
                    $this->log("Failed to delete $description: $errorMsg", 'warning');
                    $success = false;

                    // Try again with different permissions
                    chmod($file, 0666);
                    $retryResult = unlink($file);
                    $this->log("Retry $description cleanup: " . ($retryResult ? "success" : "failed"));

                    if ($retryResult) {
                        $success = true;
                    }
                }
            } else {
                $this->log("$description not found, no cleanup needed");
            }
        }

        return $success;
    }

    /**
     * Delete a recorded video and its thumbnail with enhanced error handling
     *
     * @param string $filePath Path to the video file
     * @param string $username Username for logging purposes
     * @param object $activityLogger Logger for activity tracking
     * @return bool Success status
     */
    public function deleteRecording(string $filePath, string $username, $activityLogger): bool
    {
        $this->log("\n====================================================================");
        $this->log("=== Deleting Recording (" . date('Y-m-d H:i:s') . ") ===");
        $this->log("====================================================================");
        $this->log("File to delete: $filePath");
        $this->log("User initiating deletion: $username");

        // Make sure we're not trying to delete the current recording
        if ($this->isRecordingActive()) {
            $currentRecording = $this->getCurrentRecordingFile();
            if ($currentRecording && $currentRecording === $filePath) {
                $this->log("Cannot delete active recording file", 'error');
                return false;
            }
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->log("File does not exist: $filePath", 'warning');
            return false;
        }

        // Get file details before deletion
        $fileSize = filesize($filePath);
        $fileModTime = filemtime($filePath);
        $formattedFileSize = $this->formatFileSize($fileSize);
        $formattedModTime = date('Y-m-d H:i:s', $fileModTime);

        $this->log("File size: $formattedFileSize");
        $this->log("Last modified: $formattedModTime");

        // Check file permissions
        $permissions = fileperms($filePath);
        $formattedPermissions = substr(sprintf('%o', $permissions), -4);
        $this->log("File permissions: $formattedPermissions");

        // Check if file is writable
        if (!is_writable($filePath)) {
            $this->log("File is not writable: $filePath", 'warning');

            // Try to make it writable
            $chmodResult = chmod($filePath, 0666);
            $this->log("Chmod result: " . ($chmodResult ? "success" : "failed"));

            if (!$chmodResult) {
                $this->log("Failed to make file writable", 'error');
                return false;
            }
        }

        // Log activity before deletion
        if ($activityLogger) {
            try {
                $activityLogger->logActivity($username, 'deleted_file', basename($filePath));
                $this->log("Activity logged successfully for user: $username");
            } catch (Exception $e) {
                $this->log("Warning: Failed to log activity: " . $e->getMessage(), 'warning');
                // Continue with deletion anyway
            }
        }

        // Delete video file
        $videoDeleted = unlink($filePath);
        $this->log("Video deletion result: " . ($videoDeleted ? "success" : "failed"));

        if (!$videoDeleted) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            $this->log("Failed to delete video file: $errorMsg", 'error');

            // Try again with different approach
            if (function_exists('exec')) {
                $this->log("Attempting deletion via system command");
                exec("rm " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);
                $this->log("System deletion result: " . ($returnCode === 0 ? "success" : "failed"));
                $this->log("System deletion output: " . implode("\n", $output));

                $videoDeleted = ($returnCode === 0);
            }
        }

        // Delete thumbnail if it exists
        $thumbnailFile = $this->thumbnailsDir . '/' . pathinfo(basename($filePath), PATHINFO_FILENAME) . '.jpg';
        $thumbnailDeleted = true;

        if (file_exists($thumbnailFile)) {
            $thumbnailDeleted = unlink($thumbnailFile);
            $this->log("Thumbnail deletion result: " . ($thumbnailDeleted ? "success" : "failed"));

            if (!$thumbnailDeleted) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                $this->log("Failed to delete thumbnail file: $errorMsg", 'warning');

                // Try again with different approach
                if (function_exists('exec')) {
                    $this->log("Attempting thumbnail deletion via system command");
                    exec("rm " . escapeshellarg($thumbnailFile) . " 2>&1", $output, $returnCode);
                    $this->log("System thumbnail deletion result: " . ($returnCode === 0 ? "success" : "failed"));

                    $thumbnailDeleted = ($returnCode === 0);
                }
            }
        } else {
            $this->log("No thumbnail found for file: $thumbnailFile");
        }

        // Check if temporary thumbnails exist and delete them
        $tempThumbnailPattern = 'thumbnails/temp/' . pathinfo(basename($filePath), PATHINFO_FILENAME) . '_*.jpg';
        $tempThumbnails = glob($tempThumbnailPattern);

        if (!empty($tempThumbnails)) {
            $this->log("Found " . count($tempThumbnails) . " temporary thumbnails to delete");

            foreach ($tempThumbnails as $tempThumbnail) {
                $tempThumbDeleted = unlink($tempThumbnail);
                $this->log("Temporary thumbnail deletion: " . basename($tempThumbnail) . " - " .
                    ($tempThumbDeleted ? "success" : "failed"));
            }
        }

        // Update change timestamp for all clients
        $timestampUpdated = file_put_contents($this->lastChangeFile, time());
        $this->log("Change timestamp updated: " . ($timestampUpdated !== false ? "success" : "failed"));

        $finalResult = $videoDeleted && $thumbnailDeleted;
        $this->log("=== Recording Deletion " . ($finalResult ? "Successful" : "Failed") . " ===\n");

        return $finalResult;
    }

    /**
     * Generate a thumbnail for a video file with multiple attempts and enhanced error handling
     *
     * @param string $videoFile Path to the video file
     * @param string $thumbnailFile Path to save the thumbnail
     * @param int $quality JPEG quality (1-31, lower is better)
     * @param array $timestamps Array of timestamps to try (in seconds or HH:MM:SS format)
     * @return bool Success status
     */
    public function generateThumbnail(string $videoFile, string $thumbnailFile, int $quality = 2, array $timestamps = []): bool
    {
        $this->log("\n====================================================================");
        $this->log("=== Generating Thumbnail (" . date('Y-m-d H:i:s') . ") ===");
        $this->log("====================================================================");
        $this->log("Video file: $videoFile");
        $this->log("Thumbnail file: $thumbnailFile");
        $this->log("Quality setting: $quality");

        // Check if video file exists
        if (!file_exists($videoFile)) {
            $this->log("Video file does not exist: $videoFile", 'error');
            return false;
        }

        // Ensure the thumbnails directory exists
        $thumbnailDir = dirname($thumbnailFile);
        if (!$this->ensureDirectoryExists($thumbnailDir)) {
            $this->log("Failed to create thumbnail directory: $thumbnailDir", 'error');
            return false;
        }

        // Get video duration to help select good thumbnail positions
        $duration = $this->getVideoDurationSeconds($videoFile);
        $this->log("Video duration: " . ($duration > 0 ? $this->formatDuration($duration) . " ($duration seconds)" : "Unknown"));

        // Define timestamps to try if not provided
        if (empty($timestamps)) {
            // If we have a valid duration, use percentages of the duration
            if ($duration > 0) {
                $percentages = [5, 10, 15, 20, 30, 50]; // Percentage of video duration
                $timestamps = array_map(function($percent) use ($duration) {
                    return ceil($duration * $percent / 100);
                }, $percentages);
            } else {
                // Default timestamps if duration is unknown
                $timestamps = [5, 10, 15, 30, 60, 120, 180];
            }

            $this->log("Using automatic timestamps: " . implode(', ', $timestamps));
        } else {
            $this->log("Using provided timestamps: " . implode(', ', $timestamps));
        }

        // Try multiple timestamps
        $attempts = 0;
        $success = false;
        $errors = [];

        foreach ($timestamps as $timestamp) {
            $attempts++;
            $this->log("Attempt $attempts: Trying timestamp " . $this->formatTimestamp($timestamp));

            // Format timestamp for FFmpeg
            $formattedTimestamp = $this->formatTimestamp($timestamp);

            // Build FFmpeg command with explicit error output
            $errorLog = "logs/thumbnail_error_" . time() . "_" . $attempts . ".log";
            $command = "ffmpeg -i " . escapeshellarg($videoFile) .
                " -ss " . escapeshellarg($formattedTimestamp) .
                " -vframes 1 " .
                " -q:v " . intval($quality) . " " .
                escapeshellarg($thumbnailFile) .
                " 2> " . escapeshellarg($errorLog);

            $this->log("FFmpeg command: $command");

            // Execute command
            $startTime = microtime(true);
            $result = shell_exec($command);
            $executionTime = microtime(true) - $startTime;

            $this->log("Execution time: " . round($executionTime, 2) . " seconds");
            $this->log("Command result: " . ($result !== null ? $result : "null"));

            // Check for errors
            if (file_exists($errorLog) && filesize($errorLog) > 0) {
                $errorContent = file_get_contents($errorLog);
                $this->log("FFmpeg error output: $errorContent", 'warning');
                $errors[] = $errorContent;

                // Delete error log
                unlink($errorLog);
            } else if (file_exists($errorLog)) {
                // Delete empty error log
                unlink($errorLog);
            }

            // Verify thumbnail was created
            clearstatcache();
            if (file_exists($thumbnailFile) && filesize($thumbnailFile) > 0) {
                // Analyze the thumbnail
                $fileSize = filesize($thumbnailFile);
                $this->log("Thumbnail created successfully: $fileSize bytes");

                // Check for a completely black or corrupted image
                $checkCommand = "identify -format '%[mean]' " . escapeshellarg($thumbnailFile) . " 2>/dev/null";
                $meanValue = trim(shell_exec($checkCommand));

                if ($meanValue !== '' && is_numeric($meanValue) && floatval($meanValue) < 100) {
                    $this->log("Warning: Thumbnail appears to be very dark (mean value: $meanValue), trying another timestamp", 'warning');

                    // Keep thumbnail anyway but try another if we have more timestamps
                    if (count($timestamps) > $attempts) {
                        continue;
                    }
                }

                // Set file permissions to ensure web accessibility
                chmod($thumbnailFile, 0644);
                $this->log("Thumbnail permissions set to 644");

                $success = true;
                break;
            } else {
                $this->log("Failed to generate thumbnail at timestamp " . $this->formatTimestamp($timestamp), 'warning');
            }
        }

        // If all attempts failed, try a different approach as last resort
        if (!$success) {
            $this->log("All timestamp attempts failed, trying a different approach", 'warning');

            // Try without -ss (use beginning of video)
            $command = "ffmpeg -i " . escapeshellarg($videoFile) .
                " -vframes 1 " .
                " -q:v " . intval($quality) . " " .
                escapeshellarg($thumbnailFile) .
                " > /dev/null 2>&1";

            $this->log("Last resort command: $command");

            shell_exec($command);

            // Verify thumbnail was created
            clearstatcache();
            if (file_exists($thumbnailFile) && filesize($thumbnailFile) > 0) {
                chmod($thumbnailFile, 0644);
                $this->log("Last resort thumbnail generation successful");
                $success = true;
            } else {
                $this->log("Last resort thumbnail generation failed, using fallback image", 'error');

                // Use fallback image if available
                $fallbackImage = 'assets/imgs/recording.png';
                if (file_exists($fallbackImage)) {
                    $copyResult = copy($fallbackImage, $thumbnailFile);
                    $this->log("Fallback image copy result: " . ($copyResult ? "success" : "failed"));
                    $success = $copyResult;
                }
            }
        }

        // Log final status
        if ($success) {
            $this->log("=== Thumbnail Generation Successful ===\n");
        } else {
            $this->log("=== Thumbnail Generation Failed ===\n", 'error');
            $this->log("Error details: " . implode("\n", $errors), 'error');
        }

        return $success;
    }

    /**
     * Format timestamp for FFmpeg (convert seconds to HH:MM:SS if needed)
     *
     * @param mixed $timestamp Timestamp in seconds or HH:MM:SS format
     * @return string Formatted timestamp
     */
    private function formatTimestamp($timestamp): string
    {
        // If timestamp is already a string with colons, assume it's already formatted
        if (is_string($timestamp) && strpos($timestamp, ':') !== false) {
            return $timestamp;
        }

        // Convert numeric seconds to HH:MM:SS
        $seconds = (int)$timestamp;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Get the duration of a video file in seconds
     *
     * @param string $videoFile Path to the video file
     * @return int Duration in seconds or 0 if unknown
     */
    public function getVideoDurationSeconds(string $videoFile): int
    {
        $durationStr = $this->getVideoDuration($videoFile);

        if ($durationStr === "Unknown") {
            return 0;
        }

        // Parse HH:MM:SS.MS format to seconds
        $parts = explode(':', $durationStr);

        if (count($parts) === 3) {
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            // Handle potential fractional seconds
            $seconds = (float)$parts[2];

            return (int)($hours * 3600 + $minutes * 60 + $seconds);
        }

        return 0;
    }

    /**
     * Get the duration of a video file with enhanced error handling
     *
     * @param string $videoFile Path to the video file
     * @return string Duration in HH:MM:SS format or "Unknown"
     */
    public function getVideoDuration(string $videoFile): string
    {
        $this->log("Getting duration for video: $videoFile");

        if (!file_exists($videoFile)) {
            $this->log("Video file does not exist: $videoFile", 'error');
            return "Unknown";
        }

        // Use FFprobe for more reliable duration extraction
        $command = "ffprobe -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($videoFile) . " 2>/dev/null";

        $this->log("FFprobe command: $command");
        $output = trim(shell_exec($command));

        if ($output === '' || !is_numeric($output)) {
            $this->log("FFprobe failed, falling back to FFmpeg", 'warning');
            // Fallback to FFmpeg method
            $command = "ffmpeg -i " . escapeshellarg($videoFile) . " 2>&1";
            $output = shell_exec($command);

            if (preg_match("/Duration: (\d{2}:\d{2}:\d{2}\.\d+)/", $output, $matches)) {
                $this->log("Duration found via FFmpeg: " . $matches[1]);
                return $matches[1];
            }

            $this->log("Failed to determine video duration", 'error');
            return "Unknown";
        }

        // Convert float seconds to HH:MM:SS format
        $seconds = (float)$output;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $ms = round(($seconds - floor($seconds)) * 1000);

        $duration = sprintf("%02d:%02d:%02d.%03d", $hours, $minutes, $secs, $ms);
        $this->log("Duration found: $duration");
        return $duration;
    }

    /**
     * Clean up the FFmpeg log file, removing entries older than specified days
     *
     * @param int $days Number of days to keep logs for
     * @return bool Success status
     */
    public function cleanFFmpegLog(int $days = 14): bool
    {
        $this->log("Cleaning FFmpeg log file (keeping $days days)");

        if (!file_exists($this->logFile)) {
            $this->log("No log file exists to clean");
            return true;
        }

        $content = file_get_contents($this->logFile);
        if ($content === false) {
            $this->log("Unable to read FFmpeg log file", 'error');
            return false;
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
                return $lineDate >= $cutoffDate;
            }

            return true;
        });

        $newContent = implode("\n", $keptLines);
        $bytesWritten = file_put_contents($this->logFile, $newContent);

        if ($bytesWritten === false) {
            $this->log("Failed to write updated FFmpeg log file", 'error');
            return false;
        }

        $this->log("Log cleanup complete. Lines kept: " . count($keptLines));
        return true;
    }

    /**
     * Get the FFmpeg log content with enhanced features
     *
     * @param bool $reverse Whether to reverse the order (newest first)
     * @param int $limit Maximum number of lines to return (0 for all)
     * @return string Log content
     */
    public function getFFmpegLog(bool $reverse = true, int $limit = 0): string
    {
        $this->log("Retrieving FFmpeg log (reverse: " . ($reverse ? 'yes' : 'no') . ", limit: $limit)");

        if (!file_exists($this->logFile)) {
            $this->log("No FFmpeg log file exists");
            return "No FFmpeg log entries found.";
        }

        $this->cleanFFmpegLog();
        $content = file_get_contents($this->logFile);

        if ($content === false) {
            $this->log("Failed to read log file", 'error');
            return "Error reading FFmpeg log.";
        }

        $lines = explode("\n", $content);

        if ($reverse) {
            $lines = array_reverse($lines);
        }

        if ($limit > 0 && count($lines) > $limit) {
            $lines = array_slice($lines, 0, $limit);
            $this->log("Limited output to $limit lines");
        }

        $result = implode("\n", $lines);
        $this->log("Returning " . count($lines) . " log lines");
        return $result;
    }

    /**
     * Notify clients of a recording status change
     *
     * @return bool Success status
     */
    public function updateChangeTimestamp(): bool
    {
        $this->log("Updating change timestamp");

        $result = file_put_contents($this->lastChangeFile, time());

        if ($result === false) {
            $this->log("Failed to update change timestamp", 'warning');
            return false;
        }

        $this->log("Change timestamp updated successfully");
        return true;
    }

    /**
     * Check if FFmpeg is available on the system
     *
     * @return bool True if FFmpeg is available
     */
    public function isFFmpegAvailable(): bool
    {
        $command = 'which ffmpeg 2>/dev/null';
        $result = trim(shell_exec($command));

        $available = !empty($result);
        $this->log("FFmpeg availability check: " . ($available ? "Available at $result" : "Not found"));
        return $available;
    }

    /**
     * Check if FFmpeg supports SRT protocol
     *
     * @return bool True if SRT is supported
     */
    public function isSrtSupported(): bool
    {
        $command = 'ffmpeg -protocols 2>&1 | grep srt';
        $result = trim(shell_exec($command));

        $supported = !empty($result);
        $this->log("SRT support check: " . ($supported ? "Supported ($result)" : "Not supported"));
        return $supported;
    }

    /**
     * Get FFmpeg version information
     *
     * @return string Version information
     */
    public function getFFmpegVersion(): string
    {
        $command = 'ffmpeg -version 2>&1 | head -n 1';
        $result = trim(shell_exec($command));

        $version = $result ?: 'Unknown';
        $this->log("FFmpeg version retrieved: $version");
        return $version;
    }

    /**
     * Get recording health status
     *
     * @return array Health status data
     */
    public function getRecordingHealth(): array
    {
        $this->log("Retrieving recording health status");

        if (!file_exists($this->recordingHealthFile)) {
            $this->log("No health file exists");
            return [
                'status' => 'no_recording',
                'message' => 'No active recording'
            ];
        }

        $healthData = json_decode(file_get_contents($this->recordingHealthFile), true);

        if (!$healthData) {
            $this->log("Failed to parse health data", 'warning');
            return [
                'status' => 'error',
                'message' => 'Unable to read health data'
            ];
        }

        $this->log("Health status retrieved: " . json_encode($healthData));
        return $healthData;
    }

    /**
     * Validate current recording health and attempt recovery if needed
     *
     * @return bool True if recording is healthy or recovered
     */
    public function validateRecordingHealth(): bool
    {
        $this->log("Validating recording health");

        if (!$this->isRecordingActive()) {
            $this->log("No active recording to validate");
            return false;
        }

        $pid = trim(file_get_contents($this->pidFile));
        $recordingFile = $this->getCurrentRecordingFile();

        // Check if file is growing
        if ($recordingFile && file_exists($recordingFile)) {
            $initialSize = filesize($recordingFile);
            sleep(2);
            clearstatcache();
            $newSize = filesize($recordingFile);

            $this->log("File size check - Initial: $initialSize bytes, After 2s: $newSize bytes");

            if ($newSize <= $initialSize) {
                $this->log("Warning: Recording file not growing", 'warning');
                // Could implement recovery procedures here
            }
        }

        // Update health file
        $healthData = json_decode(file_get_contents($this->recordingHealthFile), true);
        if ($healthData) {
            $healthData['last_check'] = time();
            $healthData['checks'][] = [
                'timestamp' => time(),
                'file_size' => $recordingFile ? filesize($recordingFile) : 0,
                'process_running' => $this->isRecordingActive()
            ];
            file_put_contents($this->recordingHealthFile, json_encode($healthData, JSON_PRETTY_PRINT));
        }

        $this->log("Recording health validated");
        return true;
    }
}