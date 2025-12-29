<?php
/**
 * Test giống admin_ajax.php flow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Notification Flow (Giống admin_ajax.php)</h2>";
echo "<hr>";

// Step 1: Load connect.php (PDO)
echo "<h3>Step 1: Load connect.php</h3>";
require_once __DIR__ . '/connect.php';
echo "Connection after connect.php: " . get_class($conn) . "<br>";

// Step 2: Override với mysqli
echo "<h3>Step 2: Override với mysqli</h3>";
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed");
}
$conn->set_charset("utf8mb4");
echo "Connection after mysqli override: " . get_class($conn) . "<br>";

// Step 3: Load notification_helpers.php
echo "<h3>Step 3: Load notification_helpers.php</h3>";
require_once __DIR__ . '/notification_helpers.php';
echo "Functions loaded: ";
echo function_exists('auto_notify_new_product') ? "✅" : "❌";
echo "<br>";

// Step 4: Test tạo notification
echo "<h3>Step 4: Test tạo notification</h3>";
$test_id = 7777;
$test_name = "Test Product " . date('H:i:s');
$test_category = "Test Category";

echo "Calling auto_notify_new_product($test_id, '$test_name', '$test_category')<br>";

$result = auto_notify_new_product($test_id, $test_name, $test_category);

echo "<strong>Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "</strong><br>";

// Step 5: Verify trong database
echo "<h3>Step 5: Verify trong database</h3>";
$check_stmt = $conn->prepare("SELECT * FROM notifications WHERE title LIKE ? ORDER BY id DESC LIMIT 1");
$search = "%$test_name%";
$check_stmt->bind_param("s", $search);
$check_stmt->execute();
$found = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($found) {
    echo "✅ Thông báo đã được tạo trong database!<br>";
    echo "<pre>";
    print_r($found);
    echo "</pre>";
} else {
    echo "❌ KHÔNG tìm thấy thông báo trong database!<br>";
}

// Step 6: Check error log
echo "<h3>Step 6: Check PHP Error Log</h3>";
$error_log = 'C:\\xampp\\apache\\logs\\error.log';
if (file_exists($error_log)) {
    $lines = file($error_log);
    $last_20 = array_slice($lines, -20);
    echo "<pre style='background:#f8f9fa;padding:10px;'>";
    echo htmlspecialchars(implode('', $last_20));
    echo "</pre>";
} else {
    echo "Error log not found<br>";
}

echo "<hr>";
echo "<h3>Kết luận:</h3>";
if ($result && $found) {
    echo "<p style='color:green;font-size:18px;'>✅ HỆ THỐNG HOẠT ĐỘNG TỐT!</p>";
    echo "<p>Giờ thử thêm sản phẩm ở admin và kiểm tra thông báo.</p>";
} else {
    echo "<p style='color:red;font-size:18px;'>❌ VẪN CÒN LỖI!</p>";
    echo "<p>Check error log ở trên để xem chi tiết.</p>";
}
?>
