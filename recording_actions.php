<?php
/**
 * Schedule Actions Handler
 * Handles all AJAX requests for recording schedule management
 *
 * Fixed to properly handle one-time recording dates without off-by-one errors
 */

// Start session and include required files
session_start();

// Renew the session to prevent timeout issues
$_SESSION['LAST_ACTIVITY'] = time();

require_once 'logging.php';
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Schedule Manager Class
 * Handles CRUD operations for recording schedules
 */
class ScheduleManager {
    // File to store schedules
    private $schedulesFile = 'recording_schedules.json';
    private $activityLogger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->activityLogger = new ActivityLogger();

        // Create schedules file if it doesn't exist
        if (!file_exists($this->schedulesFile)) {
            file_put_contents($this->schedulesFile, json_encode([]));
            chmod($this->schedulesFile, 0644); // Read/write for owner, read for others
        }
    }

    /**
     * Get all schedules
     *
     * @return array Array of schedules
     */
    public function getSchedules() {
        if (!file_exists($this->schedulesFile)) {
            return [];
        }

        $content = file_get_contents($this->schedulesFile);
        $schedules = json_decode($content, true) ?: [];

        // Check and auto-disable past one-time schedules
        $updated = false;
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        foreach ($schedules as &$schedule) {
            if ($schedule['type'] === 'once' && $schedule['enabled']) {
                try {
                    $scheduleDateTime = new DateTime($schedule['date'] . ' ' . $schedule['startTime']);

                    if ($scheduleDateTime < $now) {
                        $schedule['enabled'] = false;
                        $updated = true;

                        // Log the auto-disable
                        $this->activityLogger->logActivity(
                            'system',
                            'schedule_auto_disabled',
                            "Auto-disabled past one-time schedule: {$schedule['title']}"
                        );
                    }
                } catch (Exception $e) {
                    // If date parsing fails, log it but don't disable
                    error_log("Failed to parse schedule date: {$schedule['date']} - {$e->getMessage()}");
                }
            }
        }

        // Save updated schedules if any were changed
        if ($updated) {
            file_put_contents($this->schedulesFile, json_encode($schedules, JSON_PRETTY_PRINT));
        }

        return $schedules;
    }

    /**
     * Get a specific schedule by ID
     *
     * @param string $id Schedule ID
     * @return array|null Schedule data or null if not found
     */
    public function getSchedule($id) {
        $schedules = $this->getSchedules();

        foreach ($schedules as $schedule) {
            if ($schedule['id'] === $id) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * Add a new schedule
     *
     * @param array $scheduleData Schedule data
     * @return array Result with success status and message
     */
    public function addSchedule($scheduleData, $username) {
        // Basic validation first
        if (empty($scheduleData['id']) || empty($scheduleData['title']) ||
            empty($scheduleData['type']) || empty($scheduleData['startTime']) ||
            empty($scheduleData['endTime'])) {
            return ['success' => false, 'message' => 'Missing required fields'];
        }

        // Validate schedule type-specific fields
        switch ($scheduleData['type']) {
            case 'once':
                if (empty($scheduleData['date'])) {
                    return ['success' => false, 'message' => 'Date is required for one-time schedules'];
                }

                // FIX: Validate date format but allow past dates for editing purposes
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleData['date'])) {
                    return ['success' => false, 'message' => 'Date must be in YYYY-MM-DD format'];
                }

                // Log the date we're processing for debugging
                error_log("Processing one-time schedule with date: {$scheduleData['date']}");
                break;

            case 'weekly':
                if (empty($scheduleData['weekdays']) || !is_array($scheduleData['weekdays'])) {
                    return ['success' => false, 'message' => 'Weekdays are required for weekly schedules'];
                }
                break;

            case 'monthly':
                if (empty($scheduleData['monthdays']) || !is_array($scheduleData['monthdays'])) {
                    return ['success' => false, 'message' => 'Month days are required for monthly schedules'];
                }
                break;
        }

        // Get existing schedules
        $schedules = $this->getSchedules();

        // Check for ID collision (just in case)
        foreach ($schedules as $schedule) {
            if ($schedule['id'] === $scheduleData['id']) {
                return ['success' => false, 'message' => 'Schedule ID already exists'];
            }
        }

        // Add the new schedule
        $schedules[] = $scheduleData;

        // Save updated schedules
        if (file_put_contents($this->schedulesFile, json_encode($schedules, JSON_PRETTY_PRINT))) {
            // Log the activity
            $this->activityLogger->logActivity(
                $username,
                'schedule_added',
                "Added schedule: {$scheduleData['title']}"
            );

            return ['success' => true, 'message' => 'Schedule added successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to save schedule'];
        }
    }

    /**
     * Update an existing schedule
     *
     * @param array $scheduleData Updated schedule data
     * @return array Result with success status and message
     */
    public function updateSchedule($scheduleData, $username) {
        // Basic validation first
        if (empty($scheduleData['id']) || empty($scheduleData['title']) ||
            empty($scheduleData['type']) || empty($scheduleData['startTime']) ||
            empty($scheduleData['endTime'])) {
            return ['success' => false, 'message' => 'Missing required fields'];
        }

        // Validate type-specific fields
        switch ($scheduleData['type']) {
            case 'once':
                if (empty($scheduleData['date'])) {
                    return ['success' => false, 'message' => 'Date is required for one-time schedules'];
                }

                // FIX: Validate date format but allow past dates for editing purposes
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleData['date'])) {
                    return ['success' => false, 'message' => 'Date must be in YYYY-MM-DD format'];
                }
                break;

            case 'weekly':
                if (empty($scheduleData['weekdays']) || !is_array($scheduleData['weekdays'])) {
                    return ['success' => false, 'message' => 'Weekdays are required for weekly schedules'];
                }
                break;

            case 'monthly':
                if (empty($scheduleData['monthdays']) || !is_array($scheduleData['monthdays'])) {
                    return ['success' => false, 'message' => 'Month days are required for monthly schedules'];
                }
                break;
        }

        // Get existing schedules
        $schedules = $this->getSchedules();
        $found = false;
        $updated = [];

        // Find and update the schedule
        foreach ($schedules as $schedule) {
            if ($schedule['id'] === $scheduleData['id']) {
                $found = true;
                $updated[] = $scheduleData;
            } else {
                $updated[] = $schedule;
            }
        }

        if (!$found) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        // Save updated schedules
        if (file_put_contents($this->schedulesFile, json_encode($updated, JSON_PRETTY_PRINT))) {
            // Log the activity
            $this->activityLogger->logActivity(
                $username,
                'schedule_updated',
                "Updated schedule: {$scheduleData['title']}"
            );

            return ['success' => true, 'message' => 'Schedule updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update schedule'];
        }
    }

    /**
     * Delete a schedule
     *
     * @param string $id Schedule ID to delete
     * @return array Result with success status and message
     */
    public function deleteSchedule($id, $username) {
        $schedules = $this->getSchedules();
        $found = false;
        $scheduleTitle = '';

        // Filter out the schedule to delete
        $updated = array_filter($schedules, function($schedule) use ($id, &$found, &$scheduleTitle) {
            if ($schedule['id'] === $id) {
                $found = true;
                $scheduleTitle = $schedule['title'];
                return false;
            }
            return true;
        });

        if (!$found) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        // Re-index array
        $updated = array_values($updated);

        // Save updated schedules
        if (file_put_contents($this->schedulesFile, json_encode($updated, JSON_PRETTY_PRINT))) {
            // Log the activity
            $this->activityLogger->logActivity(
                $username,
                'schedule_deleted',
                "Deleted schedule: {$scheduleTitle}"
            );

            return ['success' => true, 'message' => 'Schedule deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete schedule'];
        }
    }
}

// Initialize schedule manager
$scheduleManager = new ScheduleManager();

// Get the requested action
$action = $_GET['action'] ?? '';

// Process based on action
try {
    switch ($action) {
        case 'list':
            // Start output buffering to ensure proper error handling
            ob_start();

            try {
                // Set a reasonable execution time limit
                set_time_limit(30);

                // Return all schedules
                $schedules = $scheduleManager->getSchedules();
                echo json_encode([
                    'success' => true,
                    'schedules' => $schedules,
                    'server_time' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                // Clear any already buffered output
                ob_clean();

                echo json_encode([
                    'success' => false,
                    'message' => 'Error retrieving schedules: ' . $e->getMessage()
                ]);
            }

            // End output buffering and send response
            ob_end_flush();
            break;

        case 'get':
            // Get a specific schedule
            if (empty($_GET['id'])) {
                throw new Exception('Schedule ID is required');
            }

            $schedule = $scheduleManager->getSchedule($_GET['id']);

            if ($schedule) {
                echo json_encode([
                    'success' => true,
                    'schedule' => $schedule
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Schedule not found'
                ]);
            }
            break;

        case 'add':
            // Add a new schedule
            $requestData = json_decode(file_get_contents('php://input'), true);

            if (empty($requestData)) {
                throw new Exception('Invalid request data');
            }

            $result = $scheduleManager->addSchedule($requestData, $_SESSION['username']);
            echo json_encode($result);
            break;

        case 'update':
            // Get raw input data
            $rawData = file_get_contents('php://input');
            error_log("Raw update data: " . $rawData);

            $requestData = json_decode($rawData, true);

            if (empty($requestData)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid request data: Unable to parse JSON',
                    'raw_data' => $rawData
                ]);
                exit;
            }

            // Check for required fields
            $requiredFields = ['id', 'title', 'type', 'startTime', 'endTime'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (!isset($requestData[$field]) || trim($requestData[$field]) === '') {
                    $missingFields[] = $field;
                }
            }

            // Check type-specific fields
            if ($requestData['type'] === 'once' && (!isset($requestData['date']) || trim($requestData['date']) === '')) {
                $missingFields[] = 'date';
            }

            if ($requestData['type'] === 'weekly' && (!isset($requestData['weekdays']) || !is_array($requestData['weekdays']) || count($requestData['weekdays']) === 0)) {
                $missingFields[] = 'weekdays';
            }

            if ($requestData['type'] === 'monthly' && (!isset($requestData['monthdays']) || !is_array($requestData['monthdays']) || count($requestData['monthdays']) === 0)) {
                $missingFields[] = 'monthdays';
            }

            if (!empty($missingFields)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: ' . implode(', ', $missingFields),
                    'received_data' => $requestData
                ]);
                exit;
            }

            $result = $scheduleManager->updateSchedule($requestData, $_SESSION['username']);
            echo json_encode($result);
            break;

        case 'delete':
            // Delete a schedule
            $requestData = json_decode(file_get_contents('php://input'), true);

            if (empty($requestData) || empty($requestData['id'])) {
                throw new Exception('Schedule ID is required');
            }

            $result = $scheduleManager->deleteSchedule($requestData['id'], $_SESSION['username']);
            echo json_encode($result);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
