<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'send_message') {
        $sessionId = $input['session_id'] ?? '';
        $customerName = $input['customer_name'] ?? 'Khách';
        $customerContact = $input['customer_contact'] ?? '';
        $message = trim($input['message'] ?? '');
        
        if (empty($sessionId) || empty($message)) {
            throw new Exception('Thiếu thông tin');
        }
        
        // Create or update session
        $checkStmt = $conn->prepare("SELECT id FROM chat_sessions WHERE session_id = ?");
        $checkStmt->execute([$sessionId]);
        
        if ($checkStmt->fetch()) {
            $stmt = $conn->prepare("
                UPDATE chat_sessions 
                SET last_message = ?, 
                    last_message_time = NOW(),
                    unread_count = unread_count + 1
                WHERE session_id = ?
            ");
            $stmt->execute([$message, $sessionId]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO chat_sessions 
                (session_id, customer_name, customer_contact, last_message, unread_count, status)
                VALUES (?, ?, ?, ?, 1, 'active')
            ");
            $stmt->execute([$sessionId, $customerName, $customerContact, $message]);
        }
        
        // Save message
        $stmt = $conn->prepare("
            INSERT INTO chat_messages 
            (session_id, customer_name, customer_email, message, is_from_admin)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$sessionId, $customerName, $customerContact, $message]);
        
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'load_messages') {
        $sessionId = $input['session_id'] ?? '';
        
        if (empty($sessionId)) {
            throw new Exception('Thiếu session ID');
        }
        
        $stmt = $conn->prepare("
            SELECT id, message, is_from_admin, created_at
            FROM chat_messages
            WHERE session_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sessionId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        
    } elseif ($action === 'check_new') {
        $sessionId = $input['session_id'] ?? '';
        $lastId = (int)($input['last_id'] ?? 0);
        
        if (empty($sessionId)) {
            throw new Exception('Thiếu session ID');
        }
        
        $stmt = $conn->prepare("
            SELECT id, message, is_from_admin, created_at
            FROM chat_messages
            WHERE session_id = ? AND id > ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$sessionId, $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        
    } else {
        throw new Exception('Action không hợp lệ');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
