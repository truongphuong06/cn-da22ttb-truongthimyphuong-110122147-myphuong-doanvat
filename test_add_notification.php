<?php
/**
 * Debug - Kiểm tra việc tạo thông báo khi thêm sản phẩm
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connect.php';
require_once 'notification_helpers.php';

echo "<h2>DEBUG - Test Tạo Thông Báo Sản Phẩm Mới</h2>";
echo "<hr>";

// Test 1: Kiểm tra connection type
echo "<h3>1. Kiểm tra connection:</h3>";
echo "Connection type: " . get_class($conn) . "<br>";
echo "Is PDO: " . ($conn instanceof PDO ? "✅ Yes" : "❌ No") . "<br>";
echo "Is MySQLi: " . ($conn instanceof mysqli ? "✅ Yes" : "❌ No") . "<br>";

// Test 2: Thử tạo thông báo test
echo "<h3>2. Test tạo thông báo (simulate admin thêm sản phẩm):</h3>";

$test_product_id = 9999;
$test_product_name = "Test Product " . date('H:i:s');
$test_category_name = "Test Category";

echo "Calling auto_notify_new_product($test_product_id, '$test_product_name', '$test_category_name')<br>";

$result = auto_notify_new_product($test_product_id, $test_product_name, $test_category_name);

echo "Result: " . ($result ? "✅ Success" : "❌ Failed") . "<br>";

// Test 3: Kiểm tra có insert vào database không
echo "<h3>3. Kiểm tra database:</h3>";

try {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE title LIKE ? ORDER BY id DESC LIMIT 1");
    
    if ($conn instanceof PDO) {
        $stmt->execute(["%$test_product_name%"]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($conn instanceof mysqli) {
        $search = "%$test_product_name%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        $notification = $result->fetch_assoc();
    }
    
    if ($notification) {
        echo "✅ Thông báo đã được insert vào database!<br>";
        echo "<pre>";
        print_r($notification);
        echo "</pre>";
    } else {
        echo "❌ KHÔNG tìm thấy thông báo trong database!<br>";
        echo "Có thể function không insert được.<br>";
    }
} catch (Exception $e) {
    echo "❌ Lỗi query: " . $e->getMessage() . "<br>";
}

// Test 4: Test với mysqli connection (giống admin_ajax.php)
echo "<h3>4. Test với mysqli connection (như admin_ajax.php):</h3>";

$mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli_conn->connect_error) {
    echo "❌ MySQLi connection failed<br>";
} else {
    echo "✅ MySQLi connected<br>";
    $mysqli_conn->set_charset("utf8mb4");
    
    // Override global $conn
    $old_conn = $conn;
    $conn = $mysqli_conn;
    
    echo "Testing with mysqli...<br>";
    $test_result = auto_notify_new_product(8888, "MySQLi Test Product", "Test Category");
    echo "Result: " . ($test_result ? "✅ Success" : "❌ Failed") . "<br>";
    
    // Restore
    $conn = $old_conn;
    $mysqli_conn->close();
}

// Test 5: List 10 thông báo mới nhất
echo "<h3>5. Danh sách 10 thông báo mới nhất:</h3>";
try {
    $stmt = $conn->query("SELECT id, type, title, created_at FROM notifications ORDER BY id DESC LIMIT 10");
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Type</th><th>Title</th><th>Created At</th></tr>";
    
    if ($conn instanceof PDO) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td>{$row['id']}</td><td>{$row['type']}</td><td>{$row['title']}</td><td>{$row['created_at']}</td></tr>";
        }
    } elseif ($conn instanceof mysqli) {
        while ($row = $stmt->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['type']}</td><td>{$row['title']}</td><td>{$row['created_at']}</td></tr>";
        }
    }
    
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<hr>";
echo "<h3>📝 Kết luận:</h3>";
echo "<p>Nếu thấy ❌ Failed ở test 2 hoặc 4, nghĩa là function không insert được vào database.</p>";
echo "<p>Check PHP error log: C:\\xampp\\apache\\logs\\error.log</p>";
?>
