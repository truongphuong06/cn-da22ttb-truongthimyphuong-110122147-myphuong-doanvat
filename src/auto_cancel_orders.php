<?php
/**
 * Script tự động hủy đơn hàng chuyển khoản sau 24h không thanh toán
 * Chạy script này định kỳ bằng cron job hoặc Windows Task Scheduler
 * Ví dụ: chạy mỗi giờ
 */

// Kết nối database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ban_hang";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Tìm các đơn hàng:
    // - Phương thức thanh toán = "Chuyển khoản"
    // - Trạng thái = "Chờ xử lý" (chưa xác nhận thanh toán)
    // - Đã quá 24 giờ kể từ ngày đặt
    $sql = "
        SELECT id, nguoi_dung_id, ngay_dat, tong_tien
        FROM don_hang
        WHERE phuong_thuc_thanh_toan = 'Chuyển khoản'
        AND trang_thai = 'Chờ xử lý'
        AND TIMESTAMPDIFF(HOUR, ngay_dat, NOW()) >= 24
    ";
    
    $stmt = $conn->query($sql);
    $expiredOrders = $stmt->fetchAll();
    
    if (empty($expiredOrders)) {
        echo date('Y-m-d H:i:s') . " - Không có đơn hàng nào cần hủy.\n";
        exit;
    }
    
    echo date('Y-m-d H:i:s') . " - Tìm thấy " . count($expiredOrders) . " đơn hàng cần hủy.\n";
    
    // Bắt đầu transaction
    $conn->beginTransaction();
    
    foreach ($expiredOrders as $order) {
        $orderId = $order['id'];
        
        // 1. Hoàn trả số lượng sản phẩm vào kho
        $detailStmt = $conn->prepare("
            SELECT san_pham_id, so_luong 
            FROM chi_tiet_don_hang 
            WHERE don_hang_id = ?
        ");
        $detailStmt->execute([$orderId]);
        $details = $detailStmt->fetchAll();
        
        foreach ($details as $detail) {
            $updateStockStmt = $conn->prepare("
                UPDATE san_pham 
                SET so_luong = so_luong + ? 
                WHERE id = ?
            ");
            $updateStockStmt->execute([
                $detail['so_luong'],
                $detail['san_pham_id']
            ]);
        }
        
        // 2. Cập nhật trạng thái đơn hàng thành "Đã hủy"
        $updateOrderStmt = $conn->prepare("
            UPDATE don_hang 
            SET trang_thai = 'Đã hủy',
                ghi_chu = CONCAT(IFNULL(ghi_chu, ''), '\nTự động hủy: Quá 24h không chuyển khoản (', NOW(), ')')
            WHERE id = ?
        ");
        $updateOrderStmt->execute([$orderId]);
        
        echo "  ✓ Đã hủy đơn hàng #$orderId\n";
    }
    
    $conn->commit();
    echo date('Y-m-d H:i:s') . " - Hoàn thành hủy " . count($expiredOrders) . " đơn hàng.\n";
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Auto cancel orders error: " . $e->getMessage());
}
?>
