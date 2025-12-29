<?php
/**
 * Checkout Page
 * Trang thanh toán
 */

// Load database connection
require_once __DIR__ . '/connect.php';

// Bắt buộc đăng nhập trước khi thanh toán
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'thanhtoan.php';
    redirect('dangnhap.php');
}

// Kiểm tra nếu không có sản phẩm trong giỏ hàng
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    redirect('giohang.php');
}

// Kết nối database mysqli (để tương thích với code cũ)
$mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli_conn->set_charset("utf8mb4");
$conn = $mysqli_conn; // Override for this file



// Tính tổng tiền giỏ hàng
$tongTien = 0;
foreach ($_SESSION['cart'] as $item) {
    $tongTien += $item['gia'] * $item['so_luong'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán - Shop - Thời trang</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }


        }
        
        h3 {
            color: #666;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="tel"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: transform 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .voucher-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .voucher-item {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .voucher-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        .voucher-item.selected {
            border-color: #28a745;
            background: linear-gradient(135deg, #f0f9f4, #e8f5e9);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .voucher-item.selected::before {
            content: '✓';
            position: absolute;
            top: -8px;
            right: -8px;
            background: #28a745;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        
        .voucher-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
            white-space: nowrap;
        }
        
        .voucher-info {
            flex: 1;
        }
        
        .voucher-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .voucher-desc {
            color: #28a745;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .voucher-condition {
            color: #666;
            font-size: 12px;
            margin-bottom: 2px;
        }
        
        .voucher-expire {
            color: #999;
            font-size: 11px;
        }
        
        .voucher-action {
            color: #ccc;
            font-size: 24px;
        }
        
        .voucher-item.selected .voucher-action {
            color: #28a745;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #EF476F;
        }
        
        .discount-row {
            color: #28a745;
            font-weight: 600;
        }
        
        .cart-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .cart-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            color: #666;
            font-size: 14px;
        }
        
        .voucher-message {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        .voucher-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .voucher-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="checkout-form">
        <div class="left-section">
            <h2><i class="fas fa-shopping-bag"></i> Thông Tin Thanh Toán</h2>
            <form method="POST" action="xu_ly_dat_hang.php" id="checkoutForm">
                <div class="form-group">
                    <label for="ten_khach_hang">Họ và tên:</label>
                    <input type="text" id="ten_khach_hang" name="ten_khach_hang" required>
                </div>

                <div class="form-group">
                    <label for="dia_chi">Địa chỉ:</label>
                    <input type="text" id="dia_chi" name="dia_chi" required>
                </div>

                <div class="form-group">
                    <label for="so_dien_thoai">Số điện thoại:</label>
                    <input type="tel" id="so_dien_thoai" name="so_dien_thoai" required>
                </div>

                <div class="form-group">
                    <label for="phuong_thuc_thanh_toan">Phương thức thanh toán:</label>
                    <select id="phuong_thuc_thanh_toan" name="phuong_thuc_thanh_toan" required>
                        <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                        <option value="Banking">Chuyển khoản ngân hàng</option>
                        <option value="MoMo">Ví MoMo</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-check-circle"></i> Xác Nhận Đặt Hàng
                </button>
            </form>
        </div>
        
        <div class="right-section">
            <h2><i class="fas fa-receipt"></i> Đơn Hàng Của Bạn</h2>
            
            <div class="cart-items">
                <?php foreach ($_SESSION['cart'] as $item): ?>
                <div class="cart-item">
                    <img src="uploads/<?= htmlspecialchars($item['hinh_anh']) ?>" alt="<?= htmlspecialchars($item['ten_san_pham']) ?>">
                    <div class="cart-item-info">
                        <div class="cart-item-name"><?= htmlspecialchars($item['ten_san_pham']) ?></div>
                        <div class="cart-item-price">
                            <?= number_format($item['gia']) ?>₫ x <?= $item['so_luong'] ?>
                        </div>
                    </div>
                    <div style="font-weight: 600; color: #EF476F;">
                        <?= number_format($item['gia'] * $item['so_luong']) ?>₫
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-summary">
                <h3>Tóm Tắt Đơn Hàng</h3>
                <div class="summary-row">
                    <span>Tạm tính:</span>
                    <span id="subtotal"><?= number_format($tongTien) ?>₫</span>
                </div>
                <!-- Đã xóa dòng giảm giá -->
                <div class="summary-row">
                    <span>Phí vận chuyển:</span>
                    <span>Miễn phí</span>
                </div>
                <div class="summary-row">
                    <span>Tổng cộng:</span>
                    <span id="total"><?= number_format($tongTien) ?>₫</span>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link rel="stylesheet" href="assets/chatbot.css">
<script src="assets/chatbot.js" defer></script>
</body>
</html> 