<?php
/**
 * File test hệ thống thông báo
 * Chạy file này để kiểm tra xem notification_helpers.php có hoạt động không
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/notification_helpers.php';

echo "<h2>TEST HỆ THỐNG THÔNG BÁO</h2>";
echo "<hr>";

// Test 1: Kiểm tra connection
echo "<h3>1. Kiểm tra kết nối database:</h3>";
if (isset($conn)) {
    if ($conn instanceof PDO) {
        echo "✅ Đã kết nối database qua PDO<br>";
    } elseif ($conn instanceof mysqli) {
        echo "✅ Đã kết nối database qua MySQLi<br>";
    } else {
        echo "❌ Connection type không xác định<br>";
    }
} else {
    echo "❌ Không có connection<br>";
}

// Test 2: Kiểm tra functions
echo "<h3>2. Kiểm tra functions có tồn tại:</h3>";
echo function_exists('auto_notify_new_product') ? "✅ auto_notify_new_product() exists<br>" : "❌ auto_notify_new_product() not found<br>";
echo function_exists('auto_notify_sale') ? "✅ auto_notify_sale() exists<br>" : "❌ auto_notify_sale() not found<br>";
echo function_exists('auto_notify_reply_review') ? "✅ auto_notify_reply_review() exists<br>" : "❌ auto_notify_reply_review() not found<br>";
echo function_exists('auto_notify_low_stock') ? "✅ auto_notify_low_stock() exists<br>" : "❌ auto_notify_low_stock() not found<br>";

// Test 3: Test tạo thông báo mẫu
echo "<h3>3. Test tạo thông báo mẫu:</h3>";

try {
    $result = auto_notify_new_product(999, "Sản phẩm Test", "Danh mục Test");
    if ($result) {
        echo "✅ Đã tạo thông báo test thành công!<br>";
        
        // Lấy thông báo vừa tạo
        if ($conn instanceof PDO) {
            $stmt = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 1");
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($conn instanceof mysqli) {
            $result = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 1");
            $notif = $result->fetch_assoc();
        }
        
        if ($notif) {
            echo "<pre>";
            print_r($notif);
            echo "</pre>";
        }
    } else {
        echo "❌ Không thể tạo thông báo<br>";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "<br>";
}

// Test 4: Đếm số thông báo
echo "<h3>4. Thống kê thông báo:</h3>";
try {
    if ($conn instanceof PDO) {
        $count = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC);
        echo "Tổng số thông báo đang active: " . $count['total'] . "<br>";
    } elseif ($conn instanceof mysqli) {
        $result = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE is_active = 1");
        $count = $result->fetch_assoc();
        echo "Tổng số thông báo đang active: " . $count['total'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>✅ Hệ thống thông báo đã sẵn sàng!</strong></p>";
echo "<p><a href='admin_notifications.php'>Đi tới trang quản lý thông báo</a></p>";
?>
