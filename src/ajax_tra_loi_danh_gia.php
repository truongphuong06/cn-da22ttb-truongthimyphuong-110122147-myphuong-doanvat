<?php
/**
 * Review Reply AJAX Handler
 * Xử lý trả lời đánh giá qua AJAX
 */

// Load database connection
require_once __DIR__ . '/connect.php';

// Chỉ xử lý AJAX
if (!isset($_POST['action']) || $_POST['action'] !== 'tra_loi_binh_luan') {
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Kết nối MySQLi (để tương thích)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database']);
    exit;
}

$binh_luan_id = (int)($_POST['binh_luan_id'] ?? 0);
$tra_loi = trim($_POST['tra_loi'] ?? '');

if ($binh_luan_id <= 0 || empty($tra_loi)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

// Update admin_reply
$stmt = $conn->prepare("UPDATE danh_gia SET admin_reply = ? WHERE id = ?");
$stmt->bind_param("si", $tra_loi, $binh_luan_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Trả lời bình luận thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
exit;
