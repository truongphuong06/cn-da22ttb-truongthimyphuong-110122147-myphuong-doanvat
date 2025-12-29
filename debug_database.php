<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Database</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Debug Database - Kiểm tra bảng danh_muc</h1>
    
    <div class="box">
        <h2>1. Kết nối Database</h2>
        <?php
        try {
            require_once 'connect.php';
            echo "<p class='success'>✅ Kết nối thành công!</p>";
            echo "Connection type: " . get_class($conn) . "<br>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Lỗi kết nối: " . $e->getMessage() . "</p>";
            exit;
        }
        ?>
    </div>
    
    <div class="box">
        <h2>2. Kiểm tra bảng danh_muc</h2>
        <?php
        try {
            $check = $conn->query("SHOW TABLES LIKE 'danh_muc'");
            if ($check->rowCount() > 0) {
                echo "<p class='success'>✅ Bảng danh_muc tồn tại</p>";
            } else {
                echo "<p class='error'>❌ Bảng danh_muc KHÔNG tồn tại!</p>";
                exit;
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Lỗi: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>3. Cấu trúc bảng danh_muc</h2>
        <?php
        try {
            $columns = $conn->query("SHOW COLUMNS FROM danh_muc");
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Lỗi: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>4. Dữ liệu trong bảng danh_muc</h2>
        <?php
        try {
            // Tìm cột tên
            $columns = $conn->query("SHOW COLUMNS FROM danh_muc");
            $column_names = [];
            while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                $column_names[] = $col['Field'];
            }
            
            $name_col = 'ten_danh_muc';
            if (in_array('ten_danh_muc', $column_names)) {
                $name_col = 'ten_danh_muc';
            } elseif (in_array('name', $column_names)) {
                $name_col = 'name';
            } elseif (in_array('ten', $column_names)) {
                $name_col = 'ten';
            }
            
            echo "<p>Tên cột được dùng: <strong>$name_col</strong></p>";
            
            $stmt = $conn->query("SELECT * FROM danh_muc LIMIT 10");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($categories) > 0) {
                echo "<p class='success'>✅ Có " . count($categories) . " danh mục</p>";
                echo "<table>";
                echo "<tr><th>ID</th><th>$name_col</th><th>Các cột khác</th></tr>";
                foreach ($categories as $cat) {
                    echo "<tr>";
                    echo "<td>{$cat['id']}</td>";
                    echo "<td><strong>" . htmlspecialchars($cat[$name_col]) . "</strong></td>";
                    echo "<td><pre>" . print_r($cat, true) . "</pre></td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>❌ Không có dữ liệu trong bảng danh_muc!</p>";
                echo "<p>Bạn cần thêm danh mục trước trong admin.</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Lỗi query: " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>5. Kết luận</h2>
        <p>Nếu tất cả đều ✅ → database OK, có thể test thêm sản phẩm</p>
        <p>Nếu có ❌ → xem lỗi chi tiết ở trên</p>
        <br>
        <a href="test_add_product.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">
            ← Quay lại Test Add Product
        </a>
    </div>
</body>
</html>
