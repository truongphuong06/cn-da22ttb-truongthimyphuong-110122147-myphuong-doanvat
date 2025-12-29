<?php
class DiscountController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function handleRequest($method, $id, $action) {
        switch ($method) {
            case 'GET':
                $this->getDiscounts();
                break;
                
            case 'POST':
                if ($action === 'apply') {
                    $this->applyDiscount();
                } else {
                    Response::error('Invalid action', 400);
                }
                break;
                
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function getDiscounts() {
        $sql = "SELECT * FROM voucher WHERE trang_thai = 'hoat_dong' AND ngay_bat_dau <= CURDATE() AND ngay_ket_thuc >= CURDATE()";
        $result = $this->conn->query($sql);
        
        $discounts = [];
        while ($row = $result->fetch_assoc()) {
            $discounts[] = $row;
        }
        
        Response::success($discounts);
    }
    
    private function applyDiscount() {
        $input = Auth::getInput();
        
        if (!isset($input['ma_giam_gia']) || !isset($input['tong_tien'])) {
            Response::error('Thiếu thông tin', 400);
            return;
        }
        
        $sql = "SELECT * FROM voucher WHERE ma_voucher = ? AND trang_thai = 'hoat_dong' 
                AND ngay_bat_dau <= CURDATE() AND ngay_ket_thuc >= CURDATE()";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $input['ma_giam_gia']);
        $stmt->execute();
        $discount = $stmt->get_result()->fetch_assoc();
        
        if (!$discount) {
            Response::error('Mã giảm giá không hợp lệ', 404);
            return;
        }
        
        // Kiểm tra đơn hàng tối thiểu
        if (isset($discount['gia_tri_don_hang_toi_thieu']) && $input['tong_tien'] < $discount['gia_tri_don_hang_toi_thieu']) {
            Response::error('Đơn hàng chưa đủ giá trị tối thiểu', 400);
            return;
        }
        
        // Kiểm tra số lượng còn lại
        if (isset($discount['so_luong_con_lai']) && $discount['so_luong_con_lai'] <= 0) {
            Response::error('Voucher đã hết', 400);
            return;
        }
        
        $giam = 0;
        if ($discount['loai_giam'] === 'phan_tram') {
            $giam = $input['tong_tien'] * ($discount['gia_tri_giam'] / 100);
        } else {
            $giam = $discount['gia_tri_giam'];
        }
        
        $tong_sau_giam = max(0, $input['tong_tien'] - $giam);
        
        Response::success([
            'tong_tien_goc' => $input['tong_tien'],
            'giam_gia' => $giam,
            'tong_sau_giam' => $tong_sau_giam,
            'ma_voucher' => $discount['ma_voucher'],
            'ten_voucher' => $discount['ten_voucher']
        ]);
    }
}
?>
