<?php
/**
 * check_messages.php
 * Checks for user messages and returns any that are relevant to the current user
 */

// Start session
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get current username
$username = $_SESSION['username'];

// Messages file path
$messagesFile = 'json/messages.json';

// Check if messages file exists
if (!file_exists($messagesFile)) {
    // Create empty messages file
    file_put_contents($messagesFile, json_encode([]));
    chmod($messagesFile, 0644);
    
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

// Get messages from file
$messages = json_decode(file_get_contents($messagesFile), true) ?: [];

// Filter messages for the current user
// A message is for a user if:
// 1. It's sent to all users (recipient is null)
// 2. It's sent specifically to this user
$userMessages = array_filter($messages, function($message) use ($username) {
    return $message['recipient'] === null || $message['recipient'] === $username;
});

// Sort messages by timestamp (newest first)
usort($userMessages, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Return messages to client
echo json_encode([
    'success' => true,
    'messages' => array_values($userMessages)
]);
