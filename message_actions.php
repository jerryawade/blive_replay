<?php
/**
 * message_actions.php
 * Handles actions for the user messaging system
 */

session_start();
chdir(dirname(__FILE__));

require_once 'logging.php';

$messagesFile = 'json/messages.json';

if (!is_dir('json')) {
    mkdir('json', 0777, true);
}

if (!file_exists($messagesFile)) {
    file_put_contents($messagesFile, json_encode([]));
    chmod($messagesFile, 0644);
}

function cleanupOldMessages() {
    global $messagesFile;
    
    // Log the cleanup for debugging
    file_put_contents('logs/cleanup_debug.log', "Running full cleanup at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    
    if (file_exists($messagesFile)) {
        $messages = json_decode(file_get_contents($messagesFile), true) ?: [];
        file_put_contents('logs/cleanup_debug.log', "Found " . count($messages) . " messages before cleanup\n", FILE_APPEND);
        
        // Clear all messages by saving an empty array
        $result = file_put_contents($messagesFile, json_encode([], JSON_PRETTY_PRINT));
        file_put_contents('logs/cleanup_debug.log', "After cleanup: " . ($result !== false ? 'Success - all messages removed' : 'Failed to write') . "\n", FILE_APPEND);
    } else {
        file_put_contents('logs/cleanup_debug.log', "No messages file found\n", FILE_APPEND);
    }
}

function getMessages() {
    global $messagesFile;
    if (file_exists($messagesFile)) {
        return json_decode(file_get_contents($messagesFile), true) ?: [];
    }
    return [];
}

function saveMessages($messages) {
    global $messagesFile;
    return file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT)) !== false;
}

function generateUniqueId() {
    return 'msg_' . time() . '_' . bin2hex(random_bytes(4));
}

if (php_sapi_name() === 'cli' || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['SCRIPT_FILENAME'] === __FILE__)) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $action = $_GET['action'] ?? '';
    $activityLogger = new ActivityLogger();

    try {
        switch ($action) {
            case 'send':
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Only administrators can send messages']);
                    exit;
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data || !isset($data['subject']) || !isset($data['body'])) {
                    throw new Exception('Invalid message data');
                }
                
                $message = [
                    'id' => generateUniqueId(),
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'sender' => $_SESSION['username'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'recipient' => $data['recipient'] ?? null,
                    'read_by' => []
                ];
                
                $messages = getMessages();
                $messages[] = $message;
                
                if (saveMessages($messages)) {
                    $recipientText = $message['recipient'] ?: 'all users';
                    $activityLogger->logActivity(
                        $_SESSION['username'],
                        'sent_message',
                        "Sent message to {$recipientText}: {$message['subject']}"
                    );
                    echo json_encode([
                        'success' => true,
                        'message' => 'Message sent successfully',
                        'messageId' => $message['id']
                    ]);
                } else {
                    throw new Exception('Failed to save message');
                }
                break;
                
            case 'mark_read':
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data || !isset($data['messageId'])) {
                    file_put_contents('logs/message_debug.log', "Invalid data: " . json_encode($data) . "\n", FILE_APPEND);
                    throw new Exception('Invalid request data');
                }
                
                $username = $_SESSION['username'];
                file_put_contents('logs/message_debug.log', "Session username: " . $username . "\n", FILE_APPEND);
                
                $messages = getMessages();
                $messageData = null;
                $newMessages = [];
                $found = false;

                foreach ($messages as $message) {
                    if ($message['id'] === $data['messageId']) {
                        $found = true;
                        $messageData = $message;
                        if ($message['recipient'] !== null) {
                            continue;
                        } else {
                            if (!isset($message['read_by']) || !is_array($message['read_by'])) {
                                $message['read_by'] = [];
                            }
                            if (!in_array($username, $message['read_by'])) {
                                $message['read_by'][] = $username;
                            }
                            $newMessages[] = $message;
                        }
                    } else {
                        $newMessages[] = $message;
                    }
                }

                if ($found && $messageData) {
                    saveMessages($newMessages);
                    
                    $activityLogger->logActivity(
                        $username,
                        'read_message',
                        $messageData['subject']
                    );
                    
                    if (rand(1, 10) === 1) {
                        cleanupOldMessages();
                    }
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'list':
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Only administrators can list all messages']);
                    exit;
                }
                
                $messages = getMessages();
                echo json_encode(['success' => true, 'messages' => $messages]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
