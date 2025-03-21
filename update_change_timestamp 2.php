<?php
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Update the last_change.txt file with current timestamp
$result = file_put_contents('last_change.txt', time());

echo json_encode([
    'success' => ($result !== false),
    'timestamp' => time()
]);
exit;
?>
