<?php
session_start();
require_once 'connect.php';

// Function: Tạo thông báo mới
function createNotification($conn, $user_id, $user_email, $type, $title, $message, $link = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO thong_bao (user_id, user_email, type, title, message, link)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $user_email, $type, $title, $message, $link]);
        error_log("Notification created: type=$type, user_id=$user_id, email=$user_email");
        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

// Lấy thông báo cho user hiện tại
if (isset($_GET['action']) && $_GET['action'] === 'get_notifications') {
    header('Content-Type: application/json');
    
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['email'] ?? null;
    
    if (!$user_id && !$user_email) {
        echo json_encode(['success' => false, 'notifications' => []]);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT id, type, title, message, link, is_read, created_at
            FROM thong_bao
            WHERE (user_id = ? OR user_email = ?)
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id, $user_email]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Đếm số thông báo chưa đọc
        $unreadStmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM thong_bao
            WHERE (user_id = ? OR user_email = ?)
            AND is_read = 0
        ");
        $unreadStmt->execute([$user_id, $user_email]);
        $unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Đánh dấu đã đọc
if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    header('Content-Type: application/json');
    
    $notification_id = $_POST['notification_id'] ?? 0;
    
    try {
        $stmt = $conn->prepare("UPDATE thong_bao SET is_read = 1 WHERE id = ?");
        $stmt->execute([$notification_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Đánh dấu tất cả đã đọc
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    header('Content-Type: application/json');
    
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['email'] ?? null;
    
    try {
        $stmt = $conn->prepare("
            UPDATE thong_bao 
            SET is_read = 1 
            WHERE (user_id = ? OR user_email = ?)
        ");
        $stmt->execute([$user_id, $user_email]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
