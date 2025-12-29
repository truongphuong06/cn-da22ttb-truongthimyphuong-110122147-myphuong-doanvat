<?php
/**
 * Cart Controller
 * Handles shopping cart operations
 */
class CartController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function handleRequest($method, $id, $action) {
        Auth::requireAuth();
        
        switch ($method) {
            case 'GET':
                if ($action === 'checkout') {
                    $this->getCheckoutInfo();
                } else {
                    $this->getCart();
                }
                break;
                
            case 'POST':
                if ($action === 'checkout') {
                    $this->checkout();
                } else {
                    $this->addToCart();
                }
                break;
                
            case 'PUT':
                $this->updateCartItem($id);
                break;
                
            case 'DELETE':
                $this->removeFromCart($id);
                break;
                
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function getCart() {
        $userId = Auth::getUser();
        
        $sql = "SELECT gc.*, sp.ten_san_pham, sp.gia, sp.hinh_anh, sp.so_luong as stock
                FROM gio_hang gc
                JOIN san_pham sp ON gc.san_pham_id = sp.id
                WHERE gc.nguoi_dung_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = 0;
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['so_luong'] = (int)$item['so_luong'];
            $item['gia'] = (float)$item['gia'];
            $item['subtotal'] = $item['gia'] * $item['so_luong'];
            $total += $item['subtotal'];
            
            $item['hinh_anh_url'] = $item['hinh_anh'] ? 
                'http://' . $_SERVER['HTTP_HOST'] . '/Web/uploads/' . $item['hinh_anh'] : null;
        }
        
        Response::success([
            'items' => $items,
            'total' => $total,
            'item_count' => count($items)
        ], 'Cart retrieved successfully');
    }
    
    private function addToCart() {
        $userId = Auth::getUser();
        $data = Auth::getInput();
        
        Auth::validateRequired($data, ['san_pham_id', 'so_luong']);
        
        // Check stock
        $stockStmt = $this->conn->prepare("SELECT so_luong, ten_san_pham FROM san_pham WHERE id = ?");
        $stockStmt->execute([$data['san_pham_id']]);
        $product = $stockStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            Response::error('Product not found', 404);
        }
        
        if ($product['so_luong'] < $data['so_luong']) {
            Response::error('Insufficient stock', 400);
        }
        
        // Check if already in cart
        $checkStmt = $this->conn->prepare("SELECT id, so_luong FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ?");
        $checkStmt->execute([$userId, $data['san_pham_id']]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update quantity
            $newQty = $existing['so_luong'] + $data['so_luong'];
            $updateStmt = $this->conn->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?");
            $updateStmt->execute([$newQty, $existing['id']]);
            $cartId = $existing['id'];
        } else {
            // Insert new
            $insertStmt = $this->conn->prepare("INSERT INTO gio_hang (nguoi_dung_id, san_pham_id, so_luong) VALUES (?, ?, ?)");
            $insertStmt->execute([$userId, $data['san_pham_id'], $data['so_luong']]);
            $cartId = $this->conn->lastInsertId();
        }
        
        Response::success(['cart_id' => $cartId], 'Product added to cart', 201);
    }
    
    private function updateCartItem($id) {
        if (!$id) {
            Response::error('Cart item ID is required');
        }
        
        $userId = Auth::getUser();
        $data = Auth::getInput();
        
        Auth::validateRequired($data, ['so_luong']);
        
        // Verify ownership
        $checkStmt = $this->conn->prepare("SELECT gc.*, sp.so_luong as stock FROM gio_hang gc JOIN san_pham sp ON gc.san_pham_id = sp.id WHERE gc.id = ? AND gc.nguoi_dung_id = ?");
        $checkStmt->execute([$id, $userId]);
        $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            Response::error('Cart item not found', 404);
        }
        
        if ($data['so_luong'] > $item['stock']) {
            Response::error('Insufficient stock', 400);
        }
        
        $stmt = $this->conn->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?");
        $stmt->execute([$data['so_luong'], $id]);
        
        Response::success(['id' => $id], 'Cart updated successfully');
    }
    
    private function removeFromCart($id) {
        if (!$id) {
            Response::error('Cart item ID is required');
        }
        
        $userId = Auth::getUser();
        
        $stmt = $this->conn->prepare("DELETE FROM gio_hang WHERE id = ? AND nguoi_dung_id = ?");
        $stmt->execute([$id, $userId]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(['id' => $id], 'Item removed from cart');
        } else {
            Response::error('Cart item not found', 404);
        }
    }
    
    private function checkout() {
        $userId = Auth::getUser();
        $data = Auth::getInput();
        
        Auth::validateRequired($data, ['dia_chi', 'sdt', 'phuong_thuc_thanh_toan']);
        
        // Get cart items
        $cartStmt = $this->conn->prepare("SELECT gc.*, sp.gia, sp.so_luong as stock FROM gio_hang gc JOIN san_pham sp ON gc.san_pham_id = sp.id WHERE gc.nguoi_dung_id = ?");
        $cartStmt->execute([$userId]);
        $items = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            Response::error('Cart is empty', 400);
        }
        
        // Verify stock
        foreach ($items as $item) {
            if ($item['so_luong'] > $item['stock']) {
                Response::error("Insufficient stock for product ID {$item['san_pham_id']}", 400);
            }
        }
        
        // Calculate total
        $total = array_sum(array_map(function($item) {
            return $item['gia'] * $item['so_luong'];
        }, $items));
        
        try {
            $this->conn->beginTransaction();
            
            // Create order
            $orderStmt = $this->conn->prepare("INSERT INTO don_hang (nguoi_dung_id, tong_tien, dia_chi, sdt, phuong_thuc_thanh_toan, trang_thai, ngay_dat) VALUES (?, ?, ?, ?, ?, 'Chờ xử lý', NOW())");
            $orderStmt->execute([$userId, $total, $data['dia_chi'], $data['sdt'], $data['phuong_thuc_thanh_toan']]);
            $orderId = $this->conn->lastInsertId();
            
            // Create order details and update stock
            foreach ($items as $item) {
                $detailStmt = $this->conn->prepare("INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, gia) VALUES (?, ?, ?, ?)");
                $detailStmt->execute([$orderId, $item['san_pham_id'], $item['so_luong'], $item['gia']]);
                
                $updateStockStmt = $this->conn->prepare("UPDATE san_pham SET so_luong = so_luong - ? WHERE id = ?");
                $updateStockStmt->execute([$item['so_luong'], $item['san_pham_id']]);
            }
            
            // Clear cart
            $clearCartStmt = $this->conn->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?");
            $clearCartStmt->execute([$userId]);
            
            $this->conn->commit();
            
            Response::success(['order_id' => $orderId, 'total' => $total], 'Order placed successfully', 201);
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            Response::error('Failed to process checkout: ' . $e->getMessage(), 500);
        }
    }
}
?>
