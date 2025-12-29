<?php
require_once 'connect.php';

echo "<h2>Kiểm tra bảng danh_gia</h2>";

try {
    // Kiểm tra cấu trúc bảng
    $stmt = $conn->query("DESCRIBE danh_gia");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Cấu trúc bảng:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Đếm số đánh giá
    $count = $conn->query("SELECT COUNT(*) as total FROM danh_gia")->fetch();
    echo "<h3>Tổng số đánh giá: {$count['total']}</h3>";
    
    // Lấy 5 đánh giá gần nhất
    $reviews = $conn->query("SELECT * FROM danh_gia ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>5 đánh giá gần nhất:</h3>";
    echo "<pre>";
    print_r($reviews);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>
