<?php
header('Content-Type: application/json');
require_once 'connect.php';

// Lấy danh sách session có tin nhắn
$stmt = $conn->query("
    SELECT 
        session_id,
        MAX(created_at) as last_message,
        SUM(CASE WHEN sender_type = 'customer' AND is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM chat_messages 
    GROUP BY session_id 
    ORDER BY last_message DESC
");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['sessions' => $sessions]);
