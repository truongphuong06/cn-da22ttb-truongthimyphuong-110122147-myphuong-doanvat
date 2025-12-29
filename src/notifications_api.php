<?php
/**
 * Notifications API
 * API lấy và quản lý thông báo cho user
 */

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'connect.php';

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    // Lấy danh sách thông báo
    if ($action === 'get_notifications') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, min(50, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // JOIN để lấy trạng thái đã đọc và loại bỏ thông báo đã ẩn
        $sql = "
            SELECT n.*, (unr.notification_id IS NOT NULL) AS is_read
            FROM notifications n
            LEFT JOIN user_notification_reads unr
                ON n.id = unr.notification_id AND unr.user_id = ?
            LEFT JOIN user_notification_hides unh
                ON n.id = unh.notification_id AND unh.user_id = ?
            WHERE n.is_active = 1
              AND unh.notification_id IS NULL
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $user_id, $limit, $offset]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Đếm số chưa đọc
        $unread_count = 0;
        foreach ($notifications as $n) {
            if (!$n['is_read']) $unread_count++;
        }

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count,
            'page' => $page,
            'limit' => $limit
        ]);
        exit;

    // Đánh dấu 1 thông báo đã đọc
    } elseif ($action === 'mark_read') {
        if ($user_id <= 0) {
            throw new Exception('Cần đăng nhập');
        }
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id <= 0) {
            throw new Exception('Thiếu notification_id');
        }
        
        // Đánh dấu đã đọc
        $stmt = $conn->prepare("INSERT IGNORE INTO user_notification_reads (user_id, notification_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $notification_id]);
        
        echo json_encode(['success' => true]);
        exit;

    // Đánh dấu tất cả đã đọc
    } elseif ($action === 'mark_all_read') {
        if ($user_id <= 0) {
            throw new Exception('Cần đăng nhập');
        }
        
        // Đánh dấu tất cả thông báo đang active là đã đọc
        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_notification_reads (user_id, notification_id)
            SELECT ?, id FROM notifications WHERE is_active = 1
        ");
        $stmt->execute([$user_id]);
        
        echo json_encode(['success' => true]);
        exit;

    // Ẩn 1 thông báo
    } elseif ($action === 'hide_notification') {
        if ($user_id <= 0) {
            throw new Exception('Cần đăng nhập');
        }
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id <= 0) {
            throw new Exception('Thiếu notification_id');
        }
        
        // Tạo bảng nếu chưa có
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_notification_hides (
                user_id INT NOT NULL,
                notification_id INT NOT NULL,
                hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, notification_id)
            )
        ");
        
        // Thêm vào danh sách ẩn
        $stmt = $conn->prepare("INSERT IGNORE INTO user_notification_hides (user_id, notification_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $notification_id]);
        
        echo json_encode(['success' => true]);
        exit;

    // Ẩn tất cả thông báo đã đọc
    } elseif ($action === 'hide_read_notifications') {
        if ($user_id <= 0) {
            throw new Exception('Cần đăng nhập');
        }
        
        // Tạo bảng nếu chưa có
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_notification_hides (
                user_id INT NOT NULL,
                notification_id INT NOT NULL,
                hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, notification_id)
            )
        ");
        
        // Ẩn tất cả thông báo đã đọc
        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_notification_hides (user_id, notification_id)
            SELECT user_id, notification_id 
            FROM user_notification_reads 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        echo json_encode(['success' => true]);
        exit;

    } else {
        throw new Exception('Action không hợp lệ');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
