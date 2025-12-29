<?php
/**
 * Order Processing Handler
 * Xử lý đặt hàng
 */

// Load database connection
require_once __DIR__ . '/connect.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    redirect('dangnhap.php');
}

// Kiểm tra giỏ hàng
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    redirect('giohang.php');
}

// Kết nối database mysqli (để tương thích với code cũ)
$mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli_conn->set_charset("utf8mb4");
$conn = $mysqli_conn; // Override for this file

// Lấy thông tin từ form
$ten_khach_hang = trim($_POST['ten_khach_hang'] ?? '');
$dia_chi = trim($_POST['dia_chi'] ?? '');
$so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
$phuong_thuc_thanh_toan = $_POST['phuong_thuc_thanh_toan'] ?? 'COD';
$ma_voucher = strtoupper(trim($_POST['ma_voucher'] ?? ''));
$giam_gia = (int)($_POST['giam_gia'] ?? 0);

// Validate
if (empty($ten_khach_hang) || empty($dia_chi) || empty($so_dien_thoai)) {
    $_SESSION['error'] = 'Vui lòng điền đầy đủ thông tin';
    header('Location: thanhtoan.php');
    exit();
}

// Tính tổng tiền
$tong_tien = 0;
foreach ($_SESSION['cart'] as $item) {
    $tong_tien += $item['gia'] * $item['so_luong'];
}

// Tính tổng sau giảm giá
$tong_sau_giam = $tong_tien - $giam_gia;
if ($tong_sau_giam < 0) {
    $tong_sau_giam = 0;
}

// Bắt đầu transaction
$conn->begin_transaction();

try {
    // Tạo đơn hàng
    $stmt = $conn->prepare("INSERT INTO don_hang (nguoi_dung_id, ten_khach_hang, dia_chi, so_dien_thoai, tong_tien, ma_voucher, giam_gia, phuong_thuc_thanh_toan, trang_thai) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xử lý')");
    $stmt->bind_param("isssisis", $_SESSION['user_id'], $ten_khach_hang, $dia_chi, $so_dien_thoai, $tong_sau_giam, $ma_voucher, $giam_gia, $phuong_thuc_thanh_toan);
    $stmt->execute();
    
    $don_hang_id = $conn->insert_id;
    
    // Thêm chi tiết đơn hàng
    $stmt = $conn->prepare("INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, gia) VALUES (?, ?, ?, ?)");
    
    foreach ($_SESSION['cart'] as $item) {
        // Kiểm tra số lượng tồn kho trước khi đặt
        $check_stock = $conn->prepare("SELECT so_luong FROM san_pham WHERE id = ? FOR UPDATE");
        $check_stock->bind_param("i", $item['id']);
        $check_stock->execute();
        $result_stock = $check_stock->get_result();
        $row_stock = $result_stock->fetch_assoc();
        $so_luong_con = (int)($row_stock['so_luong'] ?? 0);
        if ($item['so_luong'] > $so_luong_con) {
            $conn->rollback();
            $_SESSION['error'] = 'Sản phẩm "' . htmlspecialchars($item['id']) . '" chỉ còn ' . $so_luong_con . ' sản phẩm trong kho. Vui lòng giảm số lượng.';
            header('Location: giohang.php');
            exit();
        }
        // Thêm chi tiết đơn hàng
        $stmt->bind_param("iiii", $don_hang_id, $item['id'], $item['so_luong'], $item['gia']);
        $stmt->execute();
        // Giảm số lượng sản phẩm trong kho
        $update_stock = $conn->prepare("UPDATE san_pham SET so_luong = so_luong - ? WHERE id = ?");
        $update_stock->bind_param("ii", $item['so_luong'], $item['id']);
        if (!$update_stock->execute()) {
            file_put_contents(__DIR__ . '/log_update_stock.txt', date('Y-m-d H:i:s') . " - Lỗi giảm số lượng cho sản phẩm ID " . $item['id'] . ": " . $update_stock->error . "\n", FILE_APPEND);
            throw new Exception('Lỗi giảm số lượng sản phẩm: ' . $update_stock->error);
        }
        // Đảm bảo không bị âm
        $conn->query("UPDATE san_pham SET so_luong = 0 WHERE so_luong < 0 AND id = " . (int)$item['id']);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Xóa giỏ hàng và voucher session
    unset($_SESSION['cart']);
    unset($_SESSION['voucher']);
    
    // Lưu thông tin đơn hàng vào session để hiển thị
    $_SESSION['order_success'] = [
        'don_hang_id' => $don_hang_id,
        'ten_khach_hang' => $ten_khach_hang,
        'tong_tien' => $tong_tien,
        'giam_gia' => $giam_gia,
        'tong_sau_giam' => $tong_sau_giam,
        'ma_voucher' => $ma_voucher
    ];
    
    header('Location: thanhtoan_thanhcong.php');
    exit();
    
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollback();
    $_SESSION['error'] = 'Có lỗi xảy ra: ' . $e->getMessage();
    header('Location: thanhtoan.php');
    exit();
}
?>
