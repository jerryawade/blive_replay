<?php
/**
 * scheduler_status.php
 * Returns the current status of the recording scheduler
 */

session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include required files
require_once 'settings.php';
require_once 'FFmpegService.php';

// File paths
$schedulerStateFile = 'scheduler_state.json';
$schedulesFile = 'recording_schedules.json';

// Initialize services
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();
$ffmpegService = new FFmpegService();

// Set timezone from settings
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

// Check if recording is active
$recordingActive = $ffmpegService->isRecordingActive();
$recordingStartTime = $ffmpegService->getRecordingStartTime();

// Get scheduler state
$state = [];
if (file_exists($schedulerStateFile)) {
    $state = json_decode(file_get_contents($schedulerStateFile), true) ?: [];
}

// Get current schedule info if applicable
$currentSchedule = null;
if (!empty($state['current_schedule_id']) && file_exists($schedulesFile)) {
    $schedules = json_decode(file_get_contents($schedulesFile), true) ?: [];
    
    foreach ($schedules as $schedule) {
        if ($schedule['id'] === $state['current_schedule_id']) {
            $currentSchedule = $schedule;
            break;
        }
    }
}

// Get next upcoming scheduled recording
$nextSchedule = null;
if (file_exists($schedulesFile)) {
    $schedules = json_decode(file_get_contents($schedulesFile), true) ?: [];
    $now = new DateTime();
    
    // Convert to timestamp for easier comparison
    $nowTimestamp = $now->getTimestamp();
    $currentTime = $now->format('H:i');
    $currentDay = (int)$now->format('w'); // 0 (Sunday) to 6 (Saturday)
    $currentDayOfMonth = (int)$now->format('j'); // 1 to 31
    $currentMonth = (int)$now->format('n'); // 1 to 12
    $currentYear = (int)$now->format('Y');
    
    $closestStart = PHP_INT_MAX;
    
    foreach ($schedules as $schedule) {
        // Skip disabled schedules
        if (!$schedule['enabled']) {
            continue;
        }
        
        // Skip current active schedule
        if ($currentSchedule && $schedule['id'] === $currentSchedule['id']) {
            continue;
        }
        
        $nextStartTime = null;
        
        // Calculate next start time based on schedule type
        switch ($schedule['type']) {
            case 'daily':
                // If today's start time is in the future, use that
                if ($schedule['startTime'] > $currentTime) {
                    $startDate = clone $now;
                    $startDate->setTime(
                        (int)substr($schedule['startTime'], 0, 2),
                        (int)substr($schedule['startTime'], 3, 2)
                    );
                    $nextStartTime = $startDate->getTimestamp();
                } 
                // Otherwise, use tomorrow's start time
                else {
                    $startDate = clone $now;
                    $startDate->modify('+1 day');
                    $startDate->setTime(
                        (int)substr($schedule['startTime'], 0, 2),
                        (int)substr($schedule['startTime'], 3, 2)
                    );
                    $nextStartTime = $startDate->getTimestamp();
                }
                break;
                
            case 'weekly':
                // Find the next day of the week that matches
                $nextDay = null;
                $daysToCheck = range(0, 6); // 0 (Sunday) to 6 (Saturday)
                
                // Reorder array to start from current day
                $daysToCheck = array_merge(
                    array_slice($daysToCheck, $currentDay),
                    array_slice($daysToCheck, 0, $currentDay)
                );
                
                foreach ($daysToCheck as $day) {
                    // If today and time is in future, or a future day
                    if (in_array($day, $schedule['weekdays'])) {
                        $daysToAdd = ($day < $currentDay) ? 7 - ($currentDay - $day) : $day - $currentDay;
                        
                        // If same day, check if time is in future
                        if ($daysToAdd === 0 && $schedule['startTime'] <= $currentTime) {
                            // Time already passed today, check next week
                            $daysToAdd = 7;
                        }
                        
                        $nextStartDate = clone $now;
                        $nextStartDate->modify("+$daysToAdd day");
                        $nextStartDate->setTime(
                            (int)substr($schedule['startTime'], 0, 2),
                            (int)substr($schedule['startTime'], 3, 2)
                        );
                        
                        $nextStartTime = $nextStartDate->getTimestamp();
                        break;
                    }
                }
                break;
                
            case 'monthly':
                // Check if any days remain this month
                $foundThisMonth = false;
                
                foreach ($schedule['monthdays'] as $day) {
                    if ($day > $currentDayOfMonth || 
                        ($day === $currentDayOfMonth && $schedule['startTime'] > $currentTime)) {
                        // This day is still coming up this month
                        $nextStartDate = new DateTime("$currentYear-$currentMonth-$day {$schedule['startTime']}");
                        $nextStartTime = $nextStartDate->getTimestamp();
                        $foundThisMonth = true;
                        break;
                    }
                }
                
                // If not found this month, check next month
                if (!$foundThisMonth) {
                    // Use the first available day next month
                    sort($schedule['monthdays']);
                    $nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
                    $nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;
                    
                    $nextStartDate = new DateTime("$nextYear-$nextMonth-{$schedule['monthdays'][0]} {$schedule['startTime']}");
                    $nextStartTime = $nextStartDate->getTimestamp();
                }
                break;
                
            case 'once':
                // Parse the date and time
                $startDate = new DateTime("{$schedule['date']} {$schedule['startTime']}");
                $startTimestamp = $startDate->getTimestamp();
                
                // Only include if it's in the future
                if ($startTimestamp > $nowTimestamp) {
                    $nextStartTime = $startTimestamp;
                }
                break;
        }
        
        // If we found a valid next start time and it's closer than previous candidates
        if ($nextStartTime !== null && $nextStartTime < $closestStart) {
            $closestStart = $nextStartTime;
            $nextSchedule = [
                'id' => $schedule['id'],
                'title' => $schedule['title'],
                'type' => $schedule['type'],
                'startTime' => $schedule['startTime'],
                'endTime' => $schedule['endTime'],
                'next_run' => date('Y-m-d H:i:s', $nextStartTime)
            ];
        }
    }
}

// Return status
echo json_encode([
    'success' => true,
    'scheduler_enabled' => ($settings['enable_scheduler'] ?? false),
    'recording_active' => $recordingActive,
    'recording_start_time' => $recordingActive ? date('Y-m-d H:i:s', $recordingStartTime) : null,
    'current_schedule' => $currentSchedule,
    'next_schedule' => $nextSchedule,
    'last_action' => $state['last_action'] ?? null,
    'last_action_time' => isset($state['last_action_time']) ? date('Y-m-d H:i:s', $state['last_action_time']) : null,
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => $settings['timezone'] ?? 'America/Chicago'
]);
?>
