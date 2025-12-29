<?php
/**
 * Order Management Handler
 * Xử lý đơn hàng
 */

// Load database connection
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');

try {
    // $conn đã được load từ connect.php

    // Kiểm tra và tạo bảng nếu chưa có
    $conn->exec("CREATE TABLE IF NOT EXISTS `don_hang` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ma_don_hang` VARCHAR(50) UNIQUE,
        `nguoi_dung_id` INT NULL,
        `ten_khach_hang` VARCHAR(255),
        `so_dien_thoai` VARCHAR(20),
        `email` VARCHAR(255) NULL,
        `dia_chi` TEXT NULL,
        `thanh_pho` VARCHAR(100) NULL,
        `phuong_thuc_thanh_toan` VARCHAR(50) DEFAULT 'COD',
        `ghi_chu` TEXT NULL,
        `tong_tien` DECIMAL(15,2) DEFAULT 0,
        `phi_van_chuyen` DECIMAL(15,2) DEFAULT 0,
        `ma_voucher` VARCHAR(50) NULL,
        `giam_gia` DECIMAL(15,2) DEFAULT 0,
        `tong_thanh_toan` DECIMAL(15,2) DEFAULT 0,
        `trang_thai` VARCHAR(50) DEFAULT 'Chờ xác nhận',
        `ngay_dat` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `ngay_cap_nhat` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_nguoi_dung` (`nguoi_dung_id`),
        INDEX `idx_trang_thai` (`trang_thai`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Thêm cột voucher nếu chưa có
    try {
        $conn->exec("ALTER TABLE `don_hang` ADD COLUMN `ma_voucher` VARCHAR(50) NULL AFTER `phi_van_chuyen`");
    } catch (PDOException $e) {
        // Cột đã tồn tại, bỏ qua lỗi
    }
    
    try {
        $conn->exec("ALTER TABLE `don_hang` ADD COLUMN `giam_gia` DECIMAL(15,2) DEFAULT 0 AFTER `ma_voucher`");
    } catch (PDOException $e) {
        // Cột đã tồn tại, bỏ qua lỗi
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS `chi_tiet_don_hang` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `don_hang_id` INT NOT NULL,
        `san_pham_id` INT NOT NULL,
        `ten_san_pham` VARCHAR(255) NOT NULL,
        `gia` DECIMAL(15,2) NOT NULL,
        `so_luong` INT DEFAULT 1,
        `size` VARCHAR(10) NULL,
        `thanh_tien` DECIMAL(15,2) NOT NULL,
        INDEX `idx_don_hang` (`don_hang_id`),
        INDEX `idx_san_pham` (`san_pham_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Nhận dữ liệu JSON từ request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Dữ liệu không hợp lệ');
    }

    // Validate dữ liệu
    if (empty($data['customer']['fullname']) || empty($data['customer']['phone'])) {
        throw new Exception('Vui lòng điền đầy đủ thông tin');
    }

    if (empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('Giỏ hàng trống');
    }

    // Bắt đầu transaction
    $conn->beginTransaction();

    // Lấy user_id nếu đã đăng nhập
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Tạo mã đơn hàng
    $orderCode = 'DH' . date('YmdHis') . rand(100, 999);

    // Insert đơn hàng vào bảng don_hang
    $sqlOrder = "INSERT INTO don_hang (
        ma_don_hang,
        nguoi_dung_id,
        ten_khach_hang,
        so_dien_thoai,
        email,
        dia_chi,
        thanh_pho,
        phuong_thuc_thanh_toan,
        ghi_chu,
        tong_tien,
        phi_van_chuyen,
        ma_voucher,
        giam_gia,
        tong_thanh_toan,
        trang_thai,
        ngay_dat
    ) VALUES (
        :ma_don_hang,
        :nguoi_dung_id,
        :ten_khach_hang,
        :so_dien_thoai,
        :email,
        :dia_chi,
        :thanh_pho,
        :phuong_thuc_thanh_toan,
        :ghi_chu,
        :tong_tien,
        :phi_van_chuyen,
        :ma_voucher,
        :giam_gia,
        :tong_thanh_toan,
        'Chờ xác nhận',
        NOW()
    )";

    $stmtOrder = $conn->prepare($sqlOrder);
    $stmtOrder->execute([
        ':ma_don_hang' => $orderCode,
        ':nguoi_dung_id' => $userId,
        ':ten_khach_hang' => $data['customer']['fullname'],
        ':so_dien_thoai' => $data['customer']['phone'],
        ':email' => $data['customer']['email'] ?? '',
        ':dia_chi' => $data['customer']['address'] ?? '',
        ':thanh_pho' => $data['customer']['city'] ?? '',
        ':phuong_thuc_thanh_toan' => $data['customer']['payment'] ?? 'COD',
        ':ghi_chu' => $data['customer']['note'] ?? '',
        ':tong_tien' => $data['totals']['subtotal'] ?? 0,
        ':phi_van_chuyen' => $data['totals']['shipping'] ?? 0,
        ':ma_voucher' => $data['voucher']['code'] ?? null,
        ':giam_gia' => $data['voucher']['discount'] ?? 0,
        ':tong_thanh_toan' => $data['totals']['total'] ?? 0
    ]);

    $orderId = $conn->lastInsertId();

    // Insert chi tiết đơn hàng
    $sqlDetail = "INSERT INTO chi_tiet_don_hang (
        don_hang_id,
        san_pham_id,
        ten_san_pham,
        gia,
        so_luong,
        size,
        thanh_tien
    ) VALUES (
        :don_hang_id,
        :san_pham_id,
        :ten_san_pham,
        :gia,
        :so_luong,
        :size,
        :thanh_tien
    )";

    $stmtDetail = $conn->prepare($sqlDetail);

    foreach ($data['items'] as $item) {
        $productId = $item['id'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        
        // Kiểm tra số lượng tồn kho trước khi đặt hàng
        $checkStock = $conn->prepare("SELECT so_luong, ten_san_pham FROM san_pham WHERE id = ? FOR UPDATE");
        $checkStock->execute([$productId]);
        $product = $checkStock->fetch();
        
        if (!$product) {
            throw new Exception("Sản phẩm ID {$productId} không tồn tại");
        }
        
        $stockAvailable = (int)($product['so_luong'] ?? 0);
        if ($quantity > $stockAvailable) {
            throw new Exception("Sản phẩm '{$product['ten_san_pham']}' chỉ còn {$stockAvailable} trong kho");
        }
        
        // Insert chi tiết đơn hàng
        $stmtDetail->execute([
            ':don_hang_id' => $orderId,
            ':san_pham_id' => $productId,
            ':ten_san_pham' => $item['name'] ?? '',
            ':gia' => $item['price'] ?? 0,
            ':so_luong' => $quantity,
            ':size' => $item['size'] ?? 'M',
            ':thanh_tien' => ($item['price'] ?? 0) * $quantity
        ]);
        
        // Trừ số lượng sản phẩm trong kho
        $updateStock = $conn->prepare("UPDATE san_pham SET so_luong = so_luong - ? WHERE id = ?");
        $updateStock->execute([$quantity, $productId]);
        
        // Đảm bảo không bị âm
        $conn->exec("UPDATE san_pham SET so_luong = 0 WHERE so_luong < 0 AND id = {$productId}");
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Đặt hàng thành công!',
        'orderCode' => $orderCode,
        'orderId' => $orderId
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
