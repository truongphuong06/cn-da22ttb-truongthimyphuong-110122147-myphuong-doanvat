<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/connect.php';

try {
    // Lấy danh sách voucher - điều kiện linh hoạt hơn
    $stmt = $conn->prepare("
        SELECT 
            ma_voucher,
            mo_ta,
            loai_giam,
            gia_tri_giam,
            ngay_bat_dau,
            ngay_ket_thuc,
            so_luong_con_lai,
            gia_tri_don_hang_toi_thieu,
            trang_thai
        FROM voucher 
        WHERE 1=1
        AND trang_thai = 'hoat_dong'
        AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= CURDATE())
        AND (so_luong_con_lai IS NULL OR so_luong_con_lai > 0 OR so_luong_con_lai = 0)
        ORDER BY gia_tri_giam DESC
    ");
    
    $stmt->execute();
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: log số lượng voucher tìm thấy
    error_log("Found " . count($vouchers) . " vouchers");
    
    // Format vouchers for display
    $formattedVouchers = [];
    foreach ($vouchers as $v) {
        $discount = '';
        if ($v['loai_giam'] === 'phan_tram') {
            $discount = $v['gia_tri_giam'] . '%';
        } else {
            $discount = number_format($v['gia_tri_giam'], 0, ',', '.') . '₫';
        }
        
        $minOrder = '';
        if (isset($v['gia_tri_don_hang_toi_thieu']) && $v['gia_tri_don_hang_toi_thieu'] > 0) {
            $minOrder = 'Đơn tối thiểu ' . number_format($v['gia_tri_don_hang_toi_thieu'], 0, ',', '.') . '₫';
        }
        
        $expiry = '';
        if (!empty($v['ngay_ket_thuc']) && $v['ngay_ket_thuc'] != '0000-00-00') {
            $expiry = date('d/m/Y', strtotime($v['ngay_ket_thuc']));
        }
        
        $quantity = '';
        if (isset($v['so_luong_con_lai']) && $v['so_luong_con_lai'] > 0) {
            $quantity = 'Còn ' . $v['so_luong_con_lai'] . ' mã';
        }
        
        $formattedVouchers[] = [
            'code' => $v['ma_voucher'],
            'description' => $v['mo_ta'] ?? 'Mã giảm giá',
            'discount' => $discount,
            'minOrder' => $minOrder,
            'expiry' => $expiry,
            'quantity' => $quantity
        ];
    }
    
    echo json_encode([
        'success' => true,
        'vouchers' => $formattedVouchers,
        'count' => count($formattedVouchers)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error getting vouchers: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Không thể tải danh sách voucher',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
