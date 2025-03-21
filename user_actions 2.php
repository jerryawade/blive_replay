<?php
session_start();
header('Content-Type: application/json');
require_once 'user_management.php';
require_once 'logging.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize activity logger
$activityLogger = new ActivityLogger();

// Log function for debugging
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - User Action Error: " . $message . "\n", 3, "logs/user_actions_error.log");
}

// Ensure user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$userManager = new UserManager();

// Get the requested action
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            if (!isset($_POST['new_username']) || !isset($_POST['new_password']) || !isset($_POST['new_role'])) {
                logError("Missing required fields: " . json_encode($_POST));
                throw new Exception('Missing required fields');
            }

            $result = $userManager->addUser(
                $_POST['new_username'],
                $_POST['new_password'],
                $_POST['new_role']
            );

            if ($result['success']) {
                $activityLogger->logActivity(
                    $_SESSION['username'],
                    'user_added',
                    "Added user {$_POST['new_username']} with role {$_POST['new_role']}"
                );
            } else {
                logError("Add user failed: " . $result['message']);
            }

            echo json_encode($result);
            break;

        case 'delete':
            if (!isset($_POST['username'])) {
                throw new Exception('Username not provided');
            }

            $result = $userManager->deleteUser($_POST['username']);

            if ($result['success']) {
                $activityLogger->logActivity(
                    $_SESSION['username'],
                    'user_deleted',
                    "Deleted user {$_POST['username']}"
                );
            }

            echo json_encode($result);
            break;

        case 'change_password':
            if (!isset($_POST['change_password_username']) || !isset($_POST['new_password'])) {
                throw new Exception('Missing required fields for password change');
            }

            $result = $userManager->changePassword(
                $_POST['change_password_username'],
                $_POST['new_password']
            );

            if ($result['success']) {
                $activityLogger->logActivity(
                    $_SESSION['username'],
                    'password_changed',
                    "Changed password for user {$_POST['change_password_username']}"
                );
            }

            echo json_encode($result);
            break;

        case 'change_role':
            if (!isset($_POST['username']) || !isset($_POST['new_role'])) {
                throw new Exception('Missing required fields for role change');
            }

            $result = $userManager->changeRole(
                $_POST['username'],
                $_POST['new_role']
            );

            if ($result['success']) {
                $activityLogger->logActivity(
                    $_SESSION['username'],
                    'role_changed',
                    "Changed role for user {$_POST['username']} to {$_POST['new_role']}"
                );
            }

            echo json_encode($result);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    logError($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
