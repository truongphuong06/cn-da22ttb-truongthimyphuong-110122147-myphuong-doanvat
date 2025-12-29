<?php
/**
 * Process Order Handler
 * Xử lý đơn hàng
 */

// Load database connection
require_once __DIR__ . '/connect.php';

try {
    // Kiểm tra đăng nhập
    if (!isLoggedIn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đặt hàng']);
        exit();
    }

    // $conn đã được load từ connect.php

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('Dữ liệu đơn hàng không hợp lệ');
    }

    // Tổng tiền phải được tính từ server để tránh gian lận
    $total = 0;
    foreach ($data['items'] as $it) {
        if (!isset($it['id']) || !isset($it['quantity'])) {
            throw new Exception('Mỗi sản phẩm cần id và quantity');
        }
    }

    // Bắt đầu transaction
    $conn->beginTransaction();

    // Lấy và kiểm tra tồn kho, cộng tổng tiền
    $selectStmt = $conn->prepare("SELECT id, gia, so_luong FROM san_pham WHERE id = ? FOR UPDATE");
    foreach ($data['items'] as $it) {
        $pid = (int)$it['id'];
        $qty = (int)$it['quantity'];
        if ($qty <= 0) throw new Exception("Số lượng không hợp lệ cho sản phẩm ID $pid");

        $selectStmt->execute([$pid]);
        $row = $selectStmt->fetch();
        if (!$row) throw new Exception("Sản phẩm ID $pid không tồn tại");
        if ((int)$row['so_luong'] < $qty) {
            throw new Exception("Sản phẩm ID $pid không đủ tồn kho (còn " . (int)$row['so_luong'] . ")");
        }
        $total += (float)$row['gia'] * $qty;
    }

    // Insert don_hang
    $insertOrder = $conn->prepare("
        INSERT INTO don_hang (nguoi_dung_id, ngay_dat, tong_tien, trang_thai, phuong_thuc_thanh_toan, dia_chi, sdt)
        VALUES (:nguoi_dung_id, NOW(), :tong_tien, 'Chờ xử lý', :phuong_thuc, :dia_chi, :sdt)
    ");
    $insertOrder->execute([
        ':nguoi_dung_id' => $_SESSION['user_id'],
        ':tong_tien' => $total,
        ':phuong_thuc' => $data['paymentMethod'] ?? 'unknown',
        ':dia_chi' => $data['address'] ?? '',
        ':sdt' => $data['phone'] ?? ''
    ]);
    $orderId = $conn->lastInsertId();

    // Prepare statements
    $insertDetail = $conn->prepare("
        INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, gia_ban)
        VALUES (:don_hang_id, :san_pham_id, :so_luong, :gia_ban)
    ");
    $updateStock = $conn->prepare("UPDATE san_pham SET so_luong = so_luong - :qty WHERE id = :id");

    // Thêm chi tiết và giảm tồn kho
    $priceStmt = $conn->prepare("SELECT gia FROM san_pham WHERE id = ?"); // lấy giá chính xác
    foreach ($data['items'] as $it) {
        $pid = (int)$it['id'];
        $qty = (int)$it['quantity'];

        $priceStmt->execute([$pid]);
        $p = $priceStmt->fetch();
        $gia_ban = $p ? (float)$p['gia'] : 0;

        $insertDetail->execute([
            ':don_hang_id' => $orderId,
            ':san_pham_id' => $pid,
            ':so_luong' => $qty,
            ':gia_ban' => $gia_ban
        ]);

        $updateStock->execute([':qty' => $qty, ':id' => $pid]);
        $affected = $updateStock->rowCount();
        if ($affected === 0) {
            // log chi tiết để debug
            error_log("KHO UPDATE FAILED — product_id={$pid}, qty={$qty}, order_id={$orderId}");
            throw new Exception("Không cập nhật được kho cho sản phẩm ID {$pid}");
        }
    }

    $conn->commit();

    // Trả về kết quả
    echo json_encode(['success' => true, 'message' => 'Đặt hàng thành công', 'order_id' => $orderId]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    error_log("Process order error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>