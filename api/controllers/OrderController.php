<?php
/**
 * Order Controller
 * Handles order management operations
 */

class OrderController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function handleRequest($method, $id, $action) {
        switch ($method) {
            case 'GET':
                if ($id && $action === 'status') {
                    $this->getOrderStatus($id);
                } elseif ($id) {
                    $this->getOrder($id);
                } else {
                    $this->getOrders();
                }
                break;
                
            case 'POST':
                $this->createOrder();
                break;
                
            case 'PUT':
                if ($id && $action === 'status') {
                    $this->updateOrderStatus($id);
                } else {
                    Response::error('Invalid endpoint', 400);
                }
                break;
                
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    /**
     * Get user's orders with pagination
     */
    private function getOrders() {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM don_hang WHERE nguoi_dung_id = ?";
        $stmt = $this->conn->prepare($count_sql);
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        
        // Get orders
        $sql = "SELECT id, ma_don_hang, tong_tien, trang_thai, dia_chi, sdt, 
                phuong_thuc_thanh_toan, ghi_chu, ngay_tao, ngay_cap_nhat
                FROM don_hang 
                WHERE nguoi_dung_id = ?
                ORDER BY ngay_tao DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iii', $user['id'], $per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        Response::paginated($orders, $total, $page, $per_page);
    }
    
    /**
     * Get order details by ID
     */
    private function getOrder($id) {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        // Get order
        $sql = "SELECT * FROM don_hang WHERE id = ? AND nguoi_dung_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $id, $user['id']);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            Response::error('Không tìm thấy đơn hàng', 404);
            return;
        }
        
        // Get order items
        $sql = "SELECT ct.*, sp.ten_san_pham, sp.hinh_anh
                FROM chi_tiet_don_hang ct
                JOIN san_pham sp ON ct.san_pham_id = sp.id
                WHERE ct.don_hang_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $order['items'] = $items;
        
        Response::success($order);
    }
    
    /**
     * Create new order (alternative to cart checkout)
     */
    private function createOrder() {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        $input = Auth::getInput();
        $required = ['items', 'dia_chi', 'sdt', 'phuong_thuc_thanh_toan'];
        
        if (!Auth::validateRequired($input, $required)) {
            Response::error('Thiếu thông tin bắt buộc', 400);
            return;
        }
        
        $this->conn->begin_transaction();
        
        try {
            // Calculate total
            $tong_tien = 0;
            foreach ($input['items'] as $item) {
                $sql = "SELECT gia, so_luong FROM san_pham WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('i', $item['san_pham_id']);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                if (!$product || $product['so_luong'] < $item['so_luong']) {
                    throw new Exception('Sản phẩm không đủ số lượng');
                }
                
                $tong_tien += $product['gia'] * $item['so_luong'];
            }
            
            // Generate order code
            $ma_don_hang = 'DH' . date('Ymd') . rand(1000, 9999);
            
            // Create order
            $sql = "INSERT INTO don_hang (nguoi_dung_id, ma_don_hang, tong_tien, trang_thai, 
                    dia_chi, sdt, phuong_thuc_thanh_toan, ghi_chu)
                    VALUES (?, ?, ?, 'Chờ xác nhận', ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $ghi_chu = $input['ghi_chu'] ?? '';
            $stmt->bind_param('isdssss', 
                $user['id'], 
                $ma_don_hang, 
                $tong_tien, 
                $input['dia_chi'], 
                $input['sdt'], 
                $input['phuong_thuc_thanh_toan'],
                $ghi_chu
            );
            $stmt->execute();
            $order_id = $this->conn->insert_id;
            
            // Add order items and update stock
            foreach ($input['items'] as $item) {
                $sql = "SELECT gia FROM san_pham WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('i', $item['san_pham_id']);
                $stmt->execute();
                $gia = $stmt->get_result()->fetch_assoc()['gia'];
                
                // Insert order item
                $sql = "INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, gia)
                        VALUES (?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('iiid', $order_id, $item['san_pham_id'], $item['so_luong'], $gia);
                $stmt->execute();
                
                // Update stock
                $sql = "UPDATE san_pham SET so_luong = so_luong - ? WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('ii', $item['so_luong'], $item['san_pham_id']);
                $stmt->execute();
            }
            
            $this->conn->commit();
            
            Response::success([
                'don_hang_id' => $order_id,
                'ma_don_hang' => $ma_don_hang,
                'tong_tien' => $tong_tien
            ], 'Đặt hàng thành công');
            
        } catch (Exception $e) {
            $this->conn->rollback();
            Response::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Update order status (Admin only)
     */
    private function updateOrderStatus($id) {
        if (!Auth::requireAdmin($this->conn)) {
            return;
        }
        
        $input = Auth::getInput();
        
        if (!isset($input['trang_thai'])) {
            Response::error('Thiếu trạng thái', 400);
            return;
        }
        
        $valid_statuses = ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao', 'Đã giao', 'Đã hủy'];
        
        if (!in_array($input['trang_thai'], $valid_statuses)) {
            Response::error('Trạng thái không hợp lệ', 400);
            return;
        }
        
        $sql = "UPDATE don_hang SET trang_thai = ?, ngay_cap_nhat = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $input['trang_thai'], $id);
        
        if ($stmt->execute()) {
            Response::success(['id' => $id, 'trang_thai' => $input['trang_thai']], 'Cập nhật thành công');
        } else {
            Response::error('Cập nhật thất bại', 500);
        }
    }
    
    /**
     * Get order status
     */
    private function getOrderStatus($id) {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        $sql = "SELECT trang_thai, ngay_tao, ngay_cap_nhat FROM don_hang WHERE id = ? AND nguoi_dung_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            Response::error('Không tìm thấy đơn hàng', 404);
            return;
        }
        
        Response::success($result);
    }
}
?>
