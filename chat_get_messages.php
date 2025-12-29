<?php
header('Content-Type: application/json');
require_once 'connect.php';

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode(['messages' => []]);
    exit;
}

// Lấy tin nhắn của session này
$stmt = $conn->prepare("SELECT sender_type, message, created_at FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC");
$stmt->execute([$session_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['messages' => $messages]);
