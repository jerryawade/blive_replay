<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'settings.php';
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();

date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');

$schedulesFile = 'json/recording_schedules.json';

if (!file_exists($schedulesFile)) {
    echo json_encode([
        'success' => true,
        'next_schedule' => null,
        'file_timestamp' => 0
    ]);
    exit;
}

$schedules = json_decode(file_get_contents($schedulesFile), true) ?: [];
$fileTimestamp = filemtime($schedulesFile); // Get last modified time
$now = new DateTime();
$nextSchedule = null;
$closestStart = PHP_INT_MAX;

foreach ($schedules as $schedule) {
    if (!$schedule['enabled']) continue;

    $startDateTime = null;

    switch ($schedule['type']) {
        case 'once':
            if (!isset($schedule['date'])) continue;
            $startDateTime = new DateTime("{$schedule['date']} {$schedule['startTime']}");
            break;
        case 'daily':
            $todayStart = new DateTime("today {$schedule['startTime']}");
            $startDateTime = $todayStart > $now ? $todayStart : new DateTime("tomorrow {$schedule['startTime']}");
            break;
        case 'weekly':
            $currentDay = (int)$now->format('w');
            $daysAhead = 0;
            foreach (range(0, 6) as $offset) {
                $dayToCheck = ($currentDay + $offset) % 7;
                if (in_array($dayToCheck, $schedule['weekdays'] ?? [])) {
                    $daysAhead = $offset;
                    if ($offset === 0 && $now->format('H:i') >= $schedule['startTime']) {
                        $daysAhead = 7;
                    }
                    break;
                }
            }
            $startDateTime = clone $now;
            $startDateTime->modify("+$daysAhead days");
            $startDateTime->setTime((int)substr($schedule['startTime'], 0, 2), (int)substr($schedule['startTime'], 3, 2));
            break;
        case 'monthly':
            $currentDayOfMonth = (int)$now->format('j');
            $currentMonth = (int)$now->format('n');
            $currentYear = (int)$now->format('Y');
            $foundThisMonth = false;
            foreach ($schedule['monthdays'] ?? [] as $day) {
                if ($day > $currentDayOfMonth ||
                    ($day === $currentDayOfMonth && $schedule['startTime'] > $now->format('H:i'))) {
                    $startDateTime = new DateTime("$currentYear-$currentMonth-$day {$schedule['startTime']}");
                    $foundThisMonth = true;
                    break;
                }
            }
            if (!$foundThisMonth) {
                $nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
                $nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;
                $firstDay = min($schedule['monthdays'] ?? [1]);
                $startDateTime = new DateTime("$nextYear-$nextMonth-$firstDay {$schedule['startTime']}");
            }
            break;
        default:
            continue;
    }

    if ($startDateTime && $startDateTime > $now) {
        $startTimestamp = $startDateTime->getTimestamp();
        if ($startTimestamp < $closestStart) {
            $closestStart = $startTimestamp;
            $nextSchedule = [
                'title' => $schedule['title'],
                'next_run' => $startDateTime->format('Y-m-d H:i:s'),
                'startTime' => $schedule['startTime'],
                'endTime' => $schedule['endTime']
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'next_schedule' => $nextSchedule,
    'file_timestamp' => $fileTimestamp
]);
?>