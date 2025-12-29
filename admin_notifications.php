<?php
// Admin: Quản lý thông báo
session_start();
require_once 'connect.php';

// Kiểm tra admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: dangnhap.php');
    exit;
}

// Xử lý thêm thông báo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $type = $_POST['type'];
        $title = $_POST['title'];
        $message = $_POST['message'];
        $link = $_POST['link'] ?? null;
        $image_url = $_POST['image_url'] ?? null;
        $expires_days = intval($_POST['expires_days'] ?? 0);
        
        $expires_at = null;
        if ($expires_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
        }
        
        $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, image_url, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $title, $message, $link, $image_url, $expires_at]);
        
        $success = "Đã thêm thông báo thành công!";
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Đã xóa thông báo!";
    } elseif ($_POST['action'] === 'toggle') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE notifications SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Đã cập nhật trạng thái!";
    }
}

// Lấy danh sách thông báo
$notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Thông Báo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 20px; color: #333; }
        .success { padding: 12px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; }
        .card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        textarea { resize: vertical; min-height: 80px; }
        button { padding: 12px 24px; background: #00aaff; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }
        button:hover { background: #0088cc; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .badge-type { background: #e7f3ff; color: #004085; }
        .actions { display: flex; gap: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Quản Lý Thông Báo</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Form thêm thông báo -->
        <div class="card">
            <h2>Thêm Thông Báo Mới</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Loại thông báo</label>
                    <select name="type" required>
                        <option value="new_product">Sản phẩm mới</option>
                        <option value="sale">Giảm giá</option>
                        <option value="promotion">Khuyến mãi</option>
                        <option value="announcement">Thông báo chung</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tiêu đề</label>
                    <input type="text" name="title" required placeholder="Ví dụ: Sản phẩm mới về">
                </div>
                
                <div class="form-group">
                    <label>Nội dung</label>
                    <textarea name="message" required placeholder="Nội dung chi tiết thông báo..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Link (tùy chọn)</label>
                    <input type="text" name="link" placeholder="san-pham.php hoặc URL đầy đủ">
                </div>
                
                <div class="form-group">
                    <label>URL hình ảnh (tùy chọn)</label>
                    <input type="text" name="image_url" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label>Hết hạn sau (ngày, 0 = không giới hạn)</label>
                    <input type="number" name="expires_days" value="7" min="0">
                </div>
                
                <button type="submit"><i class="fas fa-plus"></i> Thêm Thông Báo</button>
            </form>
        </div>
        
        <!-- Danh sách thông báo -->
        <div class="card">
            <h2>Danh Sách Thông Báo</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Loại</th>
                        <th>Tiêu đề</th>
                        <th>Nội dung</th>
                        <th>Ngày tạo</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notif): ?>
                        <tr>
                            <td><?= $notif['id'] ?></td>
                            <td><span class="badge badge-type"><?= $notif['type'] ?></span></td>
                            <td><?= htmlspecialchars($notif['title']) ?></td>
                            <td><?= htmlspecialchars(substr($notif['message'], 0, 60)) ?>...</td>
                            <td><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></td>
                            <td>
                                <?php if ($notif['is_active']): ?>
                                    <span class="badge badge-active">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Đã tắt</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn-warning" title="Bật/Tắt">
                                            <i class="fas fa-toggle-on"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Xác nhận xóa?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn-danger" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <a href="qtvtrangchu.php" style="display:inline-block;margin-top:20px;color:#00aaff">
            <i class="fas fa-arrow-left"></i> Quay lại trang quản trị
        </a>
    </div>
</body>
</html>
