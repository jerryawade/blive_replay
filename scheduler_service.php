#!/usr/bin/php
<?php
/**
 * Recording Scheduler Service
 *
 * This script checks for scheduled recordings and starts/stops them as needed.
 * It should be executed by a cron job every minute.
 *
 * Example cron entry:
 * sudo crontab -e
 * * * * * * /usr/bin/php /var/www/replay/scheduler_service.php >> /var/www/replay/scheduler_log.txt 2>&1
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
chdir(dirname(__FILE__));

// Load required components
require_once 'FFmpegService.php';
require_once 'logging.php';
require_once 'settings.php';

class SchedulerService
{
    // File paths
    private $schedulesFile = 'recording_schedules.json';
    private $schedulerLogFile = 'scheduler.log';
    private $schedulerStateFile = 'scheduler_state.json';

    // Service dependencies
    private $ffmpegService;
    private $activityLogger;
    private $settingsManager;
    private $settings;

    // Active recording tracking
    private $activeRecording = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->log("Scheduler service starting");

        // Initialize components
        $this->ffmpegService = new FFmpegService();
        $this->activityLogger = new ActivityLogger();
        $this->settingsManager = new SettingsManager();
        $this->settings = $this->settingsManager->getSettings();

        // Set timezone from settings
        date_default_timezone_set($this->settings['timezone'] ?? 'America/Chicago');

        // Check if recording is currently active
        $this->activeRecording = $this->ffmpegService->isRecordingActive();

        $this->log("Initial status: " . ($this->activeRecording ? "Recording active" : "No active recording"));
    }

    /**
     * Run the scheduler check
     */
    public function run()
    {
        $this->log("Running scheduler check at " . date('Y-m-d H:i:s'));

        // Clean up log file if needed (every hour)
        if (date('i') === '00') {
            $this->cleanSchedulerLog();
        }

        // Get all schedules
        $schedules = $this->getSchedules();

        if (empty($schedules)) {
            $this->log("No schedules found");
            return;
        }

        $this->log("Found " . count($schedules) . " schedules");

        // Get current state
        $state = $this->getState();

        // Check if current recording was started by the scheduler or manually
        $startedByScheduler = !empty($state['current_schedule_id']);
        $this->log("Current recording started by: " . ($startedByScheduler ? "scheduler (schedule ID: " . $state['current_schedule_id'] . ")" : "manual operation"));

        // Check current time
        $now = new DateTime();
        $currentDay = (int)$now->format('w'); // 0 (Sunday) to 6 (Saturday)
        $currentDayOfMonth = (int)$now->format('j'); // 1 to 31
        $currentTime = $now->format('H:i');

        // Find active schedules for current time
        $activeScheduleFound = false;
        $activeScheduleId = null;
        $activeScheduleTitle = null;

        foreach ($schedules as $schedule) {
            // Skip disabled schedules
            if (!$schedule['enabled']) {
                continue;
            }

            // Check if schedule should be active now
            $shouldBeActive = $this->shouldScheduleBeActive($schedule, $now, $currentDay, $currentDayOfMonth, $currentTime);

            if ($shouldBeActive) {
                $activeScheduleFound = true;
                $activeScheduleId = $schedule['id'];
                $activeScheduleTitle = $schedule['title'];
                break;
            }
        }

        // Check if we need to start a new recording
        if ($activeScheduleFound && !$this->activeRecording) {
            $this->log("Starting scheduled recording for: " . $activeScheduleTitle);

            // Start recording
            $result = $this->ffmpegService->startRecording(
                $this->settings['srt_url'],
                'scheduler',
                $this->activityLogger
            );

            if ($result['success']) {
                $this->log("Recording started successfully");
                $this->activeRecording = true;

                // Update state
                $state['last_action'] = 'start';
                $state['last_action_time'] = time();
                $state['current_schedule_id'] = $activeScheduleId;
                $this->saveState($state);
            } else {
                $this->log("Failed to start recording: " . ($result['message'] ?? 'Unknown error'));
            }
        }
        // Check if we need to stop a recording started by the scheduler
        // Only if no active schedule is found AND the current recording was started by the scheduler
        else if (!$activeScheduleFound && $this->activeRecording && $startedByScheduler) {
            $this->log("Stopping scheduled recording as no active schedule found");

            // Stop recording
            $result = $this->ffmpegService->stopRecording(
                'scheduler',
                $this->activityLogger
            );

            if ($result['success']) {
                $this->log("Recording stopped successfully");
                $this->activeRecording = false;

                // Update state
                $state['last_action'] = 'stop';
                $state['last_action_time'] = time();
                $state['current_schedule_id'] = null;
                $this->saveState($state);
            } else {
                $this->log("Failed to stop recording: " . ($result['message'] ?? 'Unknown error'));
            }
        }
        // If already recording and should continue recording (under scheduler control)
        else if ($activeScheduleFound && $this->activeRecording && $startedByScheduler) {
            // Check if we switched to a different schedule
            if ($state['current_schedule_id'] !== $activeScheduleId) {
                $this->log("Changing active schedule from ID: " .
                    ($state['current_schedule_id'] ?? 'unknown') .
                    " to ID: " . $activeScheduleId);

                // Update state
                $state['current_schedule_id'] = $activeScheduleId;
                $this->saveState($state);
            }
        }
        // Manual recording is in progress - do nothing
        else if ($this->activeRecording && !$startedByScheduler) {
            $this->log("Manual recording in progress - scheduler will not interfere");
        }

        $this->log("Scheduler check completed");
    }

    /**
     * Clean up the scheduler log file, keeping only entries newer than specified days
     *
     * @param int $days Number of days to keep logs for
     * @return bool Success status
     */
    private function cleanSchedulerLog($days = 14)
    {
        if (!file_exists($this->schedulerLogFile)) {
            return true;
        }

        // Check file size
        $maxLogSize = 5242880; // 5MB (same as ActivityLogger)
        $fileSize = filesize($this->schedulerLogFile);

        // If file is under size limit and we're not forcing cleanup, skip
        if ($fileSize < $maxLogSize) {
            return true;
        }

        $this->log("Performing log rotation (current size: " . round($fileSize / 1024 / 1024, 2) . " MB)");

        // Read the entire log file content
        $content = file_get_contents($this->schedulerLogFile);
        if ($content === false) {
            $this->log("Unable to read scheduler log file for rotation");
            return false;
        }

        // Split content into individual lines
        $lines = explode("\n", $content);

        // Calculate timestamp for cutoff date
        $cutoffDate = strtotime("-{$days} days");

        // Filter lines, keeping only those newer than the cutoff date
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

            // Keep lines without timestamps
            return true;
        });

        // Join filtered lines back into a single string
        $newContent = implode("\n", $keptLines);

        // Backup the original file
        $backupFile = $this->schedulerLogFile . '.' . date('Y-m-d-H-i-s') . '.bak';
        if (!copy($this->schedulerLogFile, $backupFile)) {
            $this->log("Failed to create backup of scheduler log during rotation");
        }

        // Write the cleaned content back to the log file
        $writeResult = file_put_contents($this->schedulerLogFile, $newContent);
        if ($writeResult === false) {
            $this->log("Failed to write updated scheduler log file during rotation");
            return false;
        }

        $newSize = filesize($this->schedulerLogFile);
        $reducedBy = $fileSize - $newSize;
        $this->log("Log rotation complete. Reduced by " . round($reducedBy / 1024, 2) . " KB (new size: " . round($newSize / 1024, 2) . " KB)");

        return true;
    }

    /**
     * Determine if a schedule should be active at the current time
     *
     * @param array $schedule Schedule data
     * @param DateTime $now Current time
     * @param int $currentDay Current day of week (0-6)
     * @param int $currentDayOfMonth Current day of month (1-31)
     * @param string $currentTime Current time (HH:MM)
     * @return bool True if the schedule should be active
     */
    private function shouldScheduleBeActive($schedule, $now, $currentDay, $currentDayOfMonth, $currentTime)
    {
        // Convert times to minutes for precise comparison
        $currentMinutes = $this->timeToMinutes($currentTime);
        $startMinutes = $this->timeToMinutes($schedule['startTime']);
        $endMinutes = $this->timeToMinutes($schedule['endTime']);

        // Check time first - handle midnight crossover
        $isTimeValid = $endMinutes < $startMinutes
            ? ($currentMinutes >= $startMinutes || $currentMinutes < $endMinutes)
            : ($currentMinutes >= $startMinutes && $currentMinutes < $endMinutes);

        // If time is not valid, return false immediately
        if (!$isTimeValid) {
            return false;
        }

        // Check schedule type-specific conditions
        switch ($schedule['type']) {
            case 'daily':
                // Daily schedules are active every day
                return true;

            case 'weekly':
                // Weekly schedules are active on specific days of the week
                return in_array($currentDay, $schedule['weekdays']);

            case 'monthly':
                // Monthly schedules are active on specific days of the month
                return in_array($currentDayOfMonth, $schedule['monthdays']);

            case 'once':
                // One-time schedules are active on a specific date
                $scheduleDate = new DateTime($schedule['date']);
                return $scheduleDate->format('Y-m-d') === $now->format('Y-m-d');

            default:
                return false;
        }
    }

    /**
     * Convert time string (HH:MM) to minutes since midnight
     */
    private function timeToMinutes($timeStr)
    {
        $parts = explode(':', $timeStr);
        return (int)$parts[0] * 60 + (int)$parts[1];
    }

    /**
     * Get all recording schedules
     *
     * @return array Array of schedules
     */
    private function getSchedules()
    {
        if (!file_exists($this->schedulesFile)) {
            return [];
        }

        $content = file_get_contents($this->schedulesFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Get the current scheduler state
     *
     * @return array State data
     */
    private function getState()
    {
        if (!file_exists($this->schedulerStateFile)) {
            return [
                'last_action' => null,
                'last_action_time' => null,
                'current_schedule_id' => null
            ];
        }

        $content = file_get_contents($this->schedulerStateFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Save the scheduler state
     *
     * @param array $state State data
     */
    private function saveState($state)
    {
        file_put_contents($this->schedulerStateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    /**
     * Log a message to the scheduler log file
     *
     * @param string $message Message to log
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->schedulerLogFile, $logMessage, FILE_APPEND);
    }
}

// Run the scheduler
$scheduler = new SchedulerService();
$scheduler->run();
?>