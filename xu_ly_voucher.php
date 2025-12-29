<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Kiểm tra quyền admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit();
}

require_once __DIR__ . '/connect.php';

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save_voucher':
            $ma_voucher = strtoupper(trim($_POST['ma_voucher'] ?? ''));
            $ten_voucher = trim($_POST['ten_voucher'] ?? '');
            $mo_ta = trim($_POST['mo_ta'] ?? '');
            $gia_tri_giam = (int)($_POST['gia_tri_giam'] ?? 0);
            $loai_giam = $_POST['loai_giam'] ?? 'phan_tram';
            $gia_tri_don_hang_toi_thieu = (int)($_POST['gia_tri_don_hang_toi_thieu'] ?? 0);
            $so_luong_con_lai = $_POST['so_luong_con_lai'] ? (int)$_POST['so_luong_con_lai'] : null;
            $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? null;
            $ngay_ket_thuc = $_POST['ngay_ket_thuc'] ?? null;
            $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
            
            // Validate
            if (empty($ma_voucher) || empty($mo_ta) || $gia_tri_giam <= 0) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
                exit();
            }
            
            // Kiểm tra mã voucher đã tồn tại chưa
            $check = $conn->prepare("SELECT id FROM voucher WHERE ma_voucher = ?");
            $check->execute([$ma_voucher]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Mã voucher đã tồn tại']);
                exit();
            }
            
            // Thêm voucher mới (bỏ category_id)
            $stmt = $conn->prepare("
                INSERT INTO voucher (
                    ma_voucher,
                    ten_voucher, 
                    mo_ta, 
                    gia_tri_giam, 
                    loai_giam, 
                    gia_tri_don_hang_toi_thieu,
                    so_luong_con_lai,
                    ngay_bat_dau, 
                    ngay_ket_thuc, 
                    trang_thai
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ma_voucher,
                $ten_voucher,
                $mo_ta,
                $gia_tri_giam,
                $loai_giam,
                $gia_tri_don_hang_toi_thieu,
                $so_luong_con_lai,
                $ngay_bat_dau,
                $ngay_ket_thuc,
                $trang_thai
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Đã thêm voucher thành công',
                'id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'update_voucher':
            $id = (int)($_POST['id'] ?? 0);
            $ma_voucher = strtoupper(trim($_POST['ma_voucher'] ?? ''));
            $ten_voucher = trim($_POST['ten_voucher'] ?? '');
            $mo_ta = trim($_POST['mo_ta'] ?? '');
            $gia_tri_giam = (int)($_POST['gia_tri_giam'] ?? 0);
            $loai_giam = $_POST['loai_giam'] ?? 'phan_tram';
            $gia_tri_don_hang_toi_thieu = (int)($_POST['gia_tri_don_hang_toi_thieu'] ?? 0);
            $so_luong_con_lai = $_POST['so_luong_con_lai'] ? (int)$_POST['so_luong_con_lai'] : null;
            $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? null;
            $ngay_ket_thuc = $_POST['ngay_ket_thuc'] ?? null;
            $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
                // Removed category_id to avoid SQL column not found error
            
            // Kiểm tra mã voucher trùng với voucher khác
            $check = $conn->prepare("SELECT id FROM voucher WHERE ma_voucher = ? AND id != ?");
            $check->execute([$ma_voucher, $id]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Mã voucher đã tồn tại']);
                exit();
            }
            
            // Cập nhật voucher
            $stmt = $conn->prepare("
                UPDATE voucher SET 
                    ma_voucher = ?,
                    ten_voucher = ?, 
                    mo_ta = ?, 
                    gia_tri_giam = ?, 
                    loai_giam = ?, 
                    gia_tri_don_hang_toi_thieu = ?,
                    so_luong_con_lai = ?,
                    ngay_bat_dau = ?, 
                    ngay_ket_thuc = ?, 
                    trang_thai = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $ma_voucher,
                $ten_voucher,
                $mo_ta,
                $gia_tri_giam,
                $loai_giam,
                $gia_tri_don_hang_toi_thieu,
                $so_luong_con_lai,
                $ngay_bat_dau,
                $ngay_ket_thuc,
                $trang_thai,
                $id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Đã cập nhật voucher']);
            break;
            
        case 'delete_voucher':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM voucher WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Đã xóa voucher']);
            break;
            
        case 'get_voucher':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM voucher WHERE id = ?");
            $stmt->execute([$id]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($voucher) {
                echo json_encode(['success' => true, 'voucher' => $voucher]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy voucher']);
            }
            break;
            
        case 'load_vouchers':
            $stmt = $conn->prepare("SELECT * FROM voucher ORDER BY id DESC");
            $stmt->execute();
            $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'vouchers' => $vouchers]);
            break;
            
        case 'update_voucher_date':
            $id = (int)($_POST['id'] ?? 0);
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if (!in_array($field, ['ngay_bat_dau', 'ngay_ket_thuc'])) {
                echo json_encode(['success' => false, 'message' => 'Trường không hợp lệ']);
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE voucher SET $field = ? WHERE id = ?");
            $stmt->execute([$value, $id]);
            echo json_encode(['success' => true, 'message' => 'Đã cập nhật ngày']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
            break;
    }
} catch (Exception $e) {
    error_log("Error in xu_ly_voucher.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
    ]);
}
