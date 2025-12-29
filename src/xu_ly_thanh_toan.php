<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Lấy dữ liệu từ request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    // Validate required fields
    $required = ['product_id', 'product_name', 'price', 'quantity', 'fullname', 'phone', 'address', 'city', 'payment'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Thiếu thông tin: $field"]);
            exit;
        }
    }
    
    // Lấy thông tin user
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['email'] ?? $data['email'] ?? null;
    
    // Tính tổng tiền
    $subtotal = $data['price'] * $data['quantity'];
    $shipping = $subtotal < 50000 ? 5000 : 0;
    $total = $subtotal + $shipping;
    
    // Bắt đầu transaction
    $conn->beginTransaction();
    
    // Tạo mã đơn hàng
    $ma_don_hang = 'DH' . date('YmdHis') . rand(1000, 9999);
    
    // Insert đơn hàng
    $stmt = $conn->prepare("
        INSERT INTO don_hang (
            ma_don_hang, nguoi_dung_id, ten_khach_hang, email, so_dien_thoai, 
            dia_chi, thanh_pho, phuong_thuc_thanh_toan, 
            ghi_chu, tong_tien, phi_van_chuyen, tong_thanh_toan, trang_thai, ngay_dat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận', NOW())
    ");
    
    $stmt->execute([
        $ma_don_hang,
        $user_id,
        $data['fullname'],
        $user_email,
        $data['phone'],
        $data['address'],
        $data['city'],
        $data['payment'],
        $data['note'] ?? '',
        $subtotal,
        $shipping,
        $total
    ]);
    
    $order_id = $conn->lastInsertId();
    
    // Insert chi tiết đơn hàng
    $stmt = $conn->prepare("
        INSERT INTO chi_tiet_don_hang (
            don_hang_id, san_pham_id, so_luong, gia, thanh_tien
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $order_id,
        $data['product_id'],
        $data['quantity'],
        $data['price'],
        $subtotal
    ]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Đặt hàng thành công!',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?> 