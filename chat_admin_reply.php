<?php
header('Content-Type: application/json');
require_once 'connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$session_id = $input['session_id'] ?? '';
$message = $input['message'] ?? '';

if (empty($session_id) || empty($message)) {
    echo json_encode(['error' => 'Thiếu thông tin']);
    exit;
}

// Lưu tin nhắn admin
$stmt = $conn->prepare("INSERT INTO chat_messages (session_id, sender_type, message, created_at) VALUES (?, 'admin', ?, NOW())");
$stmt->execute([$session_id, $message]);

// Đánh dấu tin khách đã đọc
$stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE session_id = ? AND sender_type = 'customer'");
$stmt->execute([$session_id]);

echo json_encode(['success' => true]);
