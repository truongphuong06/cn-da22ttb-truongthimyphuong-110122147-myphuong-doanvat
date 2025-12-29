<?php
/**
 * User Management Handler
 * Xử lý quản lý người dùng (Admin only)
 */

// Load database connection
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ cho phép admin thực hiện
if (!isAdmin() && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

// Kết nối database mysqli (để tương thích với code cũ)
$mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli_conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Kết nối database thất bại']);
    exit;
}
$mysqli_conn->set_charset('utf8mb4');
$conn = $mysqli_conn; // Override for this file

// Ensure 'khoa' (locked) column exists for account lock feature
$res = $conn->query("SHOW COLUMNS FROM `nguoi_dung` LIKE 'khoa'");
if ($res && $res->num_rows === 0) {
    // add column if missing
    $conn->query("ALTER TABLE `nguoi_dung` ADD COLUMN `khoa` TINYINT(1) NOT NULL DEFAULT 0");
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$action || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu tham số']);
    exit;
}

// Không cho phép thao tác với tài khoản admin gốc nếu muốn có thể chặn theo id cụ thể

try {
    if ($action === 'xoa') {
        // Không xóa chính mình
        if ($userId == $_SESSION['admin_id']) {
            throw new Exception('Không thể xóa tài khoản của chính bạn');
        }
        
        $check = $conn->prepare('SELECT quyen FROM nguoi_dung WHERE id = ?');
        $check->bind_param('i', $userId);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        if (!$res) throw new Exception('Người dùng không tồn tại');

        $stmt = $conn->prepare('DELETE FROM nguoi_dung WHERE id = ?');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception('Xóa thất bại');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'thang_cap') {
        $stmt = $conn->prepare("UPDATE nguoi_dung SET quyen = 'admin' WHERE id = ?");
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception('Cập nhật vai trò thất bại');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'giam_cap') {
        $stmt = $conn->prepare("UPDATE nguoi_dung SET quyen = 'user' WHERE id = ?");
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception('Cập nhật vai trò thất bại');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reset_mat_khau') {
        // Reset về mật khẩu mặc định 123456 (hash)
        $default = password_hash('123456', PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE nguoi_dung SET mat_khau = ? WHERE id = ?');
        $stmt->bind_param('si', $default, $userId);
        if (!$stmt->execute()) throw new Exception('Reset mật khẩu thất bại');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'khoa') {
        // Không khóa chính mình
        if ($userId == $_SESSION['admin_id']) {
            throw new Exception('Không thể khóa tài khoản của chính bạn');
        }
        
        $check = $conn->prepare('SELECT quyen FROM nguoi_dung WHERE id = ?');
        $check->bind_param('i', $userId);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        if (!$res) throw new Exception('Người dùng không tồn tại');

        $stmt = $conn->prepare('UPDATE nguoi_dung SET khoa = 1 WHERE id = ?');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception('Khóa tài khoản thất bại');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'mo_khoa') {
        // unlock account (set khoa = 0)
        $stmt = $conn->prepare('UPDATE nguoi_dung SET khoa = 0 WHERE id = ?');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception('Mở khóa tài khoản thất bại');
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Hành động không hợp lệ');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}

// xu_ly_binh_luan.php: Processing review submission
// POST data: ...
// SESSION data: ...
// Review data: product_id=..., user_id=..., ...
// Checking if user has purchased product...
// Has purchased: YES/NO
// Purchase check passed or user is admin
// Checking for duplicate review...
// No duplicate found, creating table if not exists...
// Table created/verified, inserting review...
// Review inserted successfully! Insert ID: XXX
// Comment insert failed: [Thông báo lỗi]
// Stack trace: [Chi tiết lỗi]
?>


