<?php
/**
 * Check Voucher API
 * Kiểm tra voucher
 */

// Load database connection
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');

// Kết nối mysqli (để tương thích)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Kết nối thất bại']));
}
$conn->set_charset("utf8mb4");

$ma_voucher = strtoupper(trim($_POST['ma_voucher'] ?? ''));

if (empty($ma_voucher)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã voucher']);
    exit();
}

// Kiểm tra voucher
$stmt = $conn->prepare("SELECT * FROM voucher WHERE ma_voucher = ? AND trang_thai = 'hoat_dong'");
$stmt->bind_param("s", $ma_voucher);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Mã voucher không tồn tại hoặc đã vô hiệu hóa']);
    exit();
}

$voucher = $result->fetch_assoc();

// Kiểm tra thời hạn
$now = date('Y-m-d');
if (!empty($voucher['ngay_bat_dau']) && $now < $voucher['ngay_bat_dau']) {
    echo json_encode(['success' => false, 'message' => 'Voucher chưa đến ngày sử dụng']);
    exit();
}

if (!empty($voucher['ngay_ket_thuc']) && $now > $voucher['ngay_ket_thuc']) {
    echo json_encode(['success' => false, 'message' => 'Voucher đã hết hạn']);
    exit();
}


// Kiểm tra số lượng còn lại
if (isset($voucher['so_luong_con_lai']) && $voucher['so_luong_con_lai'] !== null && $voucher['so_luong_con_lai'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Voucher đã hết lượt sử dụng']);
    exit();
}

// Kiểm tra giá trị đơn hàng tối thiểu
$tong_tien = isset($_POST['tong_tien']) ? (int)$_POST['tong_tien'] : 0;
if (isset($voucher['gia_tri_don_hang_toi_thieu']) && $voucher['gia_tri_don_hang_toi_thieu'] > 0) {
    if ($tong_tien < $voucher['gia_tri_don_hang_toi_thieu']) {
        echo json_encode([
            'success' => false,
            'message' => 'Đơn hàng chưa đủ điều kiện áp dụng mã này. Vui lòng mua tối thiểu ' . number_format($voucher['gia_tri_don_hang_toi_thieu'], 0, ',', '.') . '₫.'
        ]);
        exit();
    }
}

// Lưu voucher vào session (dùng mo_ta thay vì ten_voucher)
// Trừ số lượng còn lại nếu có
if (isset($voucher['so_luong_con_lai']) && $voucher['so_luong_con_lai'] !== null && $voucher['so_luong_con_lai'] > 0) {
    $updateStmt = $conn->prepare("UPDATE voucher SET so_luong_con_lai = so_luong_con_lai - 1 WHERE id = ? AND so_luong_con_lai > 0");
    $updateStmt->bind_param("i", $voucher['id']);
    $updateStmt->execute();
}
$_SESSION['voucher'] = [
    'ma_voucher' => $voucher['ma_voucher'],
    'mo_ta' => $voucher['mo_ta'] ?? '',
    'gia_tri_giam' => $voucher['gia_tri_giam'],
    'loai_giam' => $voucher['loai_giam']
];

echo json_encode([
    'success' => true,
    'message' => 'Áp dụng voucher thành công!',
    'voucher' => [
        'ma_voucher' => $voucher['ma_voucher'],
        'mo_ta' => $voucher['mo_ta'] ?? '',
        'gia_tri_giam' => $voucher['gia_tri_giam'],
        'loai_giam' => $voucher['loai_giam']
    ]
]);
?>
