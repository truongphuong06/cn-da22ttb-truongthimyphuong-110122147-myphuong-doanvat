<?php
/**
 * Debug file - Kiểm tra lỗi qtvtrangchu.php
 */

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>DEBUG - Kiểm tra qtvtrangchu.php</h2>";
echo "<hr>";

// Test 1: Require connect.php
echo "<h3>1. Kiểm tra connect.php:</h3>";
try {
    require_once __DIR__ . '/connect.php';
    echo "✅ connect.php loaded successfully<br>";
    echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Not active") . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 2: Kiểm tra connection
echo "<h3>2. Kiểm tra database connection:</h3>";
if (isset($conn)) {
    echo "✅ Connection exists<br>";
    echo "Type: " . get_class($conn) . "<br>";
} else {
    echo "❌ No connection found<br>";
}

// Test 3: Test mysqli connection
echo "<h3>3. Kiểm tra mysqli connection:</h3>";
try {
    $mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli_conn->connect_error) {
        echo "❌ MySQLi Error: " . $mysqli_conn->connect_error . "<br>";
    } else {
        echo "✅ MySQLi connected successfully<br>";
        $mysqli_conn->set_charset("utf8mb4");
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Test 4: Kiểm tra bảng notifications
echo "<h3>4. Kiểm tra bảng notifications:</h3>";
try {
    $result = $mysqli_conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result->num_rows > 0) {
        echo "✅ Bảng notifications tồn tại<br>";
        
        // Kiểm tra cấu trúc
        $cols = $mysqli_conn->query("SHOW COLUMNS FROM notifications");
        echo "Columns: <ul>";
        while ($col = $cols->fetch_assoc()) {
            echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "❌ Bảng notifications không tồn tại<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 5: Test require notification_helpers.php
echo "<h3>5. Kiểm tra notification_helpers.php:</h3>";
try {
    require_once __DIR__ . '/notification_helpers.php';
    echo "✅ notification_helpers.php loaded<br>";
    
    // Check functions
    echo "Functions available:<ul>";
    if (function_exists('auto_notify_new_product')) echo "<li>✅ auto_notify_new_product</li>";
    if (function_exists('auto_notify_sale')) echo "<li>✅ auto_notify_sale</li>";
    if (function_exists('auto_notify_reply_review')) echo "<li>✅ auto_notify_reply_review</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 6: Simulate admin check
echo "<h3>6. Kiểm tra session admin:</h3>";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo "✅ Admin logged in<br>";
} else {
    echo "⚠️ Admin not logged in (Đây là lý do redirect)<br>";
    echo "Để test, set session: \$_SESSION['admin_logged_in'] = true;<br>";
}

echo "<hr>";
echo "<h3>✅ Debug completed!</h3>";
echo "<p><a href='qtvtrangchu.php'>Thử truy cập qtvtrangchu.php</a></p>";
?>
