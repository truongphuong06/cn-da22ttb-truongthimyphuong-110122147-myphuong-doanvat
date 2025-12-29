<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user_id'] = 1; // Test với user_id = 1
session_write_close(); // QUAN TRỌNG: Đóng session trước khi gọi curl để tránh lock!

// Test gọi API
echo "<h3>Test Notifications API</h3>";

// Gọi API get_notifications
echo "<h4>1. Test get_notifications</h4>";
$url = 'http://localhost/WebCN/notifications_api.php?action=get_notifications';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode === 500) {
    echo "<hr><h4>Checking database...</h4>";
    require_once 'connect.php';
    
    // Kiểm tra bảng user_notification_hides có tồn tại không
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'user_notification_hides'");
        $exists = $stmt->fetch();
        
        if (!$exists) {
            echo "<p style='color:orange;'>⚠️ Bảng 'user_notification_hides' CHƯA TỒN TẠI</p>";
            echo "<p>Đang tạo bảng...</p>";
            
            $conn->exec("
                CREATE TABLE IF NOT EXISTS user_notification_hides (
                    user_id INT NOT NULL,
                    notification_id INT NOT NULL,
                    hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, notification_id)
                )
            ");
            
            echo "<p style='color:green;'>✅ Đã tạo bảng user_notification_hides</p>";
            echo "<p><a href='test_notifications_api.php'>Reload để test lại</a></p>";
        } else {
            echo "<p style='color:green;'>✅ Bảng 'user_notification_hides' đã tồn tại</p>";
            
            // Kiểm tra cấu trúc bảng
            $stmt = $conn->query("DESCRIBE user_notification_hides");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($columns);
            echo "</pre>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
    }
}
?>
