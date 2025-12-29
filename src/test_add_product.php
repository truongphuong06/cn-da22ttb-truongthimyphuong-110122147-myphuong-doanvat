<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for admin check
session_start();

// Check if admin logged in
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Add Product & Notification</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input, select, textarea { width: 100%; padding: 10px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { padding: 12px 24px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 16px; }
        button:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        #result { margin-top: 20px; padding: 15px; border-radius: 4px; display: none; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        label { font-weight: bold; display: block; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🧪 Test Thêm Sản Phẩm + Notification</h2>
        
        <?php if (!$is_admin): ?>
            <div class="warning">
                ⚠️ <strong>Chưa đăng nhập admin!</strong><br>
                Bạn cần đăng nhập admin trước để test.<br><br>
                <a href="dangnhap.php" style="color: #007bff;">→ Đăng nhập admin tại đây</a>
            </div>
        <?php else: ?>
            <div class="success" style="display: block; margin-bottom: 20px;">
                ✅ Đã đăng nhập admin. Có thể test thêm sản phẩm!
            </div>
        <?php endif; ?>
        
        <form id="testForm">
            <label>Mã sản phẩm:</label>
            <input type="text" name="ma_san_pham" value="TEST<?php echo rand(100,999); ?>" required>
            
            <label>Tên sản phẩm:</label>
            <input type="text" name="ten_san_pham" value="Test Product <?php echo date('H:i:s'); ?>" required>
            
            <label>Danh mục:</label>
            <select name="danh_muc_id" required>
                <option value="">-- Chọn danh mục --</option>
                <?php
                try {
                    require_once 'connect.php';
                    // Cột tên danh mục là ten_san_pham
                    $stmt = $conn->query("SELECT id, ten_san_pham FROM danh_muc ORDER BY id LIMIT 20");
                    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($categories) > 0) {
                        foreach ($categories as $cat) {
                            echo "<option value='{$cat['id']}'>" . htmlspecialchars($cat['ten_san_pham']) . "</option>";
                        }
                    } else {
                        echo "<option value=''>⚠️ Không có danh mục trong database</option>";
                    }
                } catch (Exception $e) {
                    echo "<option value=''>❌ Lỗi: " . htmlspecialchars($e->getMessage()) . "</option>";
                }
                ?>
            </select>
            <small style="color: #666;">Nếu không có danh mục, hãy thêm danh mục trước trong admin</small>
            
            <label>Giá:</label>
            <input type="number" name="gia" value="100000" required>
            
            <label>Số lượng:</label>
            <input type="number" name="so_luong" value="10" required>
            
            <label>Mô tả:</label>
            <textarea name="mo_ta" rows="3">Sản phẩm test thông báo</textarea>
            
            <input type="hidden" name="action" value="save_product">
            
            <br><br>
            <button type="submit">➕ Thêm Sản Phẩm</button>
        </form>
        
        <div id="result"></div>
        
        <hr>
        <h3>Kiểm tra thông báo:</h3>
        <button onclick="checkNotifications()">Lấy Thông Báo Mới Nhất</button>
        <div id="notifications"></div>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = 'Đang thêm sản phẩm...';
            resultDiv.className = '';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('admin_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.className = 'success';
                    resultDiv.innerHTML = '✅ ' + data.message + '<br><br>Đợi 2 giây rồi kiểm tra thông báo...';
                    
                    // Auto check notifications after 2 seconds
                    setTimeout(checkNotifications, 2000);
                } else {
                    resultDiv.className = 'error';
                    resultDiv.innerHTML = '❌ ' + data.message;
                }
            } catch (error) {
                resultDiv.className = 'error';
                resultDiv.innerHTML = '❌ Lỗi: ' + error.message;
            }
        });
        
        async function checkNotifications() {
            const notifDiv = document.getElementById('notifications');
            notifDiv.innerHTML = 'Đang tải...';
            
            try {
                const response = await fetch('notifications_api.php?action=get_notifications');
                const data = await response.json();
                
                if (data.success && data.notifications.length > 0) {
                    notifDiv.innerHTML = '<h4>Thông báo mới nhất:</h4>';
                    data.notifications.slice(0, 3).forEach(n => {
                        notifDiv.innerHTML += `
                            <div style="padding:10px; border:1px solid #ddd; margin:5px 0; border-radius:4px;">
                                <strong>${n.title}</strong><br>
                                <small>${n.message}</small><br>
                                <small style="color:#999;">${n.created_at}</small>
                            </div>
                        `;
                    });
                } else {
                    notifDiv.innerHTML = '❌ Không có thông báo';
                }
            } catch (error) {
                notifDiv.innerHTML = '❌ Lỗi: ' + error.message;
            }
        }
    </script>
</body>
</html>
