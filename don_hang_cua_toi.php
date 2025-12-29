
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connect.php';

// AJAX handler for cancel order
if (isset($_POST['cancel_order'])) {
    header('Content-Type: application/json');
    $order_id = (int)$_POST['order_id'];
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['email'] ?? null;
    
    try {
        // Kiểm tra đơn hàng có thuộc về user không
        if ($user_id) {
            $stmt = $conn->prepare("SELECT trang_thai FROM don_hang WHERE id = ? AND nguoi_dung_id = ?");
            $stmt->execute([$order_id, $user_id]);
        } elseif ($user_email) {
            $stmt = $conn->prepare("SELECT trang_thai FROM don_hang WHERE id = ? AND email = ?");
            $stmt->execute([$order_id, $user_email]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
            exit;
        }
        
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }
        
        // Chỉ cho phép hủy đơn hàng có trạng thái "Chờ xác nhận"
        $status = trim($order['trang_thai']);
        if ($status !== 'Chờ xác nhận') {
            echo json_encode(['success' => false, 'message' => 'Không thể hủy đơn hàng đã được xác nhận']);
            exit;
        }
        
        // Cập nhật trạng thái thành "Đã hủy"
        $updateStmt = $conn->prepare("UPDATE don_hang SET trang_thai = 'Đã hủy' WHERE id = ?");
        $updateStmt->execute([$order_id]);
        
        echo json_encode(['success' => true, 'message' => 'Đã hủy đơn hàng thành công']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// AJAX handler for order details (phải xử lý trước khi output HTML)
if (isset($_GET['get_order_details'])) {
    header('Content-Type: application/json');
    $order_id = (int)$_GET['get_order_details'];
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['email'] ?? null;
    
    try {
        // Lấy thông tin đơn hàng
        if ($user_id) {
            $stmt = $conn->prepare("SELECT * FROM don_hang WHERE id = ? AND nguoi_dung_id = ?");
            $stmt->execute([$order_id, $user_id]);
        } elseif ($user_email) {
            $stmt = $conn->prepare("SELECT * FROM don_hang WHERE id = ? AND email = ?");
            $stmt->execute([$order_id, $user_email]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
            exit;
        }
        
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }
        
        // Lấy chi tiết sản phẩm
        $stmt = $conn->prepare("
            SELECT cd.*, sp.ten_san_pham, sp.gia, sp.hinh_anh
            FROM chi_tiet_don_hang cd
            LEFT JOIN san_pham sp ON cd.san_pham_id = sp.id
            WHERE cd.don_hang_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
        
        // Lấy đánh giá cho từng sản phẩm (nếu có)
        $reviews = [];
        if ($user_id || $user_email) {
            foreach ($items as $item) {
                $reviewStmt = $conn->prepare("
                    SELECT rating, comment, admin_reply, created_at
                    FROM danh_gia 
                    WHERE san_pham_id = ? 
                    AND (user_id = ? OR user_email = ?)
                    LIMIT 1
                ");
                $reviewStmt->execute([$item['san_pham_id'], $user_id, $user_email]);
                $review = $reviewStmt->fetch();
                if ($review) {
                    $reviews[$item['san_pham_id']] = $review;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'order' => $order,
            'items' => $items,
            'reviews' => $reviews
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}


// Lấy thông tin user
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;

// Lấy danh sách đơn hàng (CHỈ KHÁCH HÀNG)
$orders = [];

try {
    $table_check = $conn->query("SHOW TABLES LIKE 'don_hang'");
    if ($table_check->rowCount() > 0) {
        // Chỉ lấy đơn hàng đúng của user hiện tại
        if ($user_id) {
            $stmt = $conn->prepare("SELECT * FROM don_hang WHERE nguoi_dung_id = ? ORDER BY ngay_dat DESC");
            $stmt->execute([$user_id]);
        } elseif ($user_email) {
            $stmt = $conn->prepare("SELECT * FROM don_hang WHERE email = ? ORDER BY ngay_dat DESC");
            $stmt->execute([$user_email]);
        } else {
            $stmt = null;
        }
        if ($stmt) {
            $orders = $stmt->fetchAll();
            // Log debug: xuất ra danh sách đơn hàng đang load cho user
            $debug_orders = array_map(function($o) {
                return $o['id'] . '|' . $o['ma_don_hang'] . '|' . $o['nguoi_dung_id'] . '|' . $o['email'] . '|' . $o['trang_thai'];
            }, $orders);
            error_log('[DON_HANG_CUA_TOI] UserID=' . ($user_id ?? 'null') . ', Email=' . ($user_email ?? 'null') . ', Orders=' . implode(',', $debug_orders));
        }
    }
} catch (PDOException $e) {
    error_log("Error loading orders: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi - My Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --indigo: #3D4457;
            --berry: #A64674;
            --coral: #F25D63;
            --peach: #FFA177;
            --beige-1: #D4C5B0;
            --beige-2: #E8DCC8;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--indigo);
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 700;
        }

        .page-title .icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--berry), var(--coral));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--berry), var(--coral));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(166, 70, 116, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--indigo);
            border: 2px solid var(--indigo);
        }

        .btn-secondary:hover {
            background: var(--indigo);
            color: white;
        }

        .user-info {
            background: linear-gradient(135deg, var(--beige-1), var(--beige-2));
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info i {
            font-size: 1.5rem;
            color: var(--indigo);
        }

        .user-info .info {
            flex: 1;
        }

        .user-info .username {
            font-weight: 700;
            color: var(--indigo);
            font-size: 1.1rem;
        }

        .user-info .email {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--coral);
            margin-bottom: 0.5rem;
        }

        .stat-card .label {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Order Tabs */
        .order-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .tab-btn {
            flex: 1;
            padding: 1rem 1.5rem;
            background: #f3f4f6;
            border: 2px solid transparent;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-btn i {
            font-size: 1.1rem;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--indigo) 0%, var(--purple) 100%);
            color: white;
            border-color: var(--indigo);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .tab-btn:hover:not(.active) {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        .tab-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 700;
        }

        .tab-btn.active .tab-count {
            background: rgba(255, 255, 255, 0.25);
        }

        .orders-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--indigo);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .order-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }

        .order-card:hover {
            border-color: var(--berry);
            box-shadow: 0 4px 12px rgba(166, 70, 116, 0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-code {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--indigo);
        }

        .order-code i {
            margin-right: 0.5rem;
            color: var(--berry);
        }

        .order-date {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .order-date i {
            margin-right: 0.5rem;
        }

        .order-body {
            margin-bottom: 1rem;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .info-item i {
            color: var(--berry);
            margin-top: 0.25rem;
        }

        .info-item .value {
            flex: 1;
            color: var(--indigo);
        }

        .info-item .label {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.confirmed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.shipping {
            background: #e0e7ff;
            color: #4338ca;
        }

        .status-badge.delivered {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .order-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--coral);
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-outline {
            background: white;
            color: var(--berry);
            border: 2px solid var(--berry);
        }

        .btn-outline:hover {
            background: var(--berry);
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--indigo);
        }

        .close-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: #f3f4f6;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: var(--coral);
            color: white;
        }

        .order-items {
            margin-top: 1.5rem;
        }

        .item-card {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: var(--indigo);
            margin-bottom: 0.25rem;
        }

        .item-details {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .item-price {
            font-weight: 700;
            color: var(--coral);
            white-space: nowrap;
        }

        .order-summary {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--beige-1), var(--beige-2));
            border-radius: 12px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            color: var(--indigo);
        }

        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            border-top: 2px solid rgba(61, 68, 87, 0.2);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }

        @media (max-width: 768px) {
            .page-header {
                text-align: center;
            }

            .page-title {
                flex-direction: column;
            }

            .order-header,
            .order-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-actions {
                width: 100%;
            }

            .order-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h1>Đơn Hàng Của Tôi</h1>
            </div>
            <div class="header-actions">
                <a href="trangchu.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Trang Chủ
                </a>
                <a href="logout.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-sign-out-alt"></i>
                    Đăng Xuất
                </a>
            </div>
        </div>

        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div class="info">
                <div class="username">
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Khách hàng'); ?>
                </div>
                <?php if ($user_email): ?>
                    <div class="email"><?php echo htmlspecialchars($user_email); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="number"><?php echo count($orders); ?></div>
                <div class="label">Tổng đơn hàng</div>
            </div>
            <div class="stat-card">
                <div class="number">
                    <?php 
                    $pending = array_filter($orders, function($o) { return $o['trang_thai'] === 'Chờ xác nhận'; });
                    echo count($pending);
                    ?>
                </div>
                <div class="label">Chờ xác nhận</div>
            </div>
            <div class="stat-card">
                <div class="number">
                    <?php 
                    $shipping = array_filter($orders, function($o) { return $o['trang_thai'] === 'Đang giao'; });
                    echo count($shipping);
                    ?>
                </div>
                <div class="label">Đang giao hàng</div>
            </div>
            <div class="stat-card">
                <div class="number">
                    <?php 
                    $delivered = array_filter($orders, function($o) { return $o['trang_thai'] === 'Đã giao'; });
                    echo count($delivered);
                    ?>
                </div>
                <div class="label">Đã hoàn thành</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="order-tabs">
            <button class="tab-btn active" onclick="switchTab('active')">
                <i class="fas fa-shopping-cart"></i>
                Đơn Hàng Đang Xử Lý
                <span class="tab-count">
                    <?php 
                    $active = array_filter($orders, function($o) { 
                        return in_array($o['trang_thai'], ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao']); 
                    });
                    echo count($active);
                    ?>
                </span>
            </button>
            <button class="tab-btn" onclick="switchTab('history')">
                <i class="fas fa-history"></i>
                Lịch Sử Đơn Hàng
                <span class="tab-count">
                    <?php 
                    $history = array_filter($orders, function($o) { 
                        return in_array($o['trang_thai'], ['Đã giao', 'Đã hủy']); 
                    });
                    echo count($history);
                    ?>
                </span>
            </button>
        </div>

        <!-- Active Orders -->
        <div class="orders-section" id="active-orders">
            <div class="section-title">
                <i class="fas fa-list"></i>
                Đơn Hàng Đang Xử Lý
            </div>

            <?php 
            $activeOrders = array_filter($orders, function($o) { 
                return in_array($o['trang_thai'], ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao']); 
            });
            ?>

            <?php if (empty($activeOrders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Không có đơn hàng đang xử lý</h3>
                    <p>Tất cả đơn hàng của bạn đã hoàn thành hoặc đã hủy.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activeOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-code">
                                    <i class="fas fa-receipt"></i>
                                    <?php echo htmlspecialchars($order['ma_don_hang']); ?>
                                </div>
                                <div class="order-date">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-info">
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="value">
                                        <div class="label">Địa chỉ giao hàng</div>
                                        <?php echo htmlspecialchars($order['dia_chi'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-credit-card"></i>
                                    <div class="value">
                                        <div class="label">Thanh toán</div>
                                        <?php echo htmlspecialchars($order['phuong_thuc_thanh_toan']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-footer">
                            <div>
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                switch ($order['trang_thai']) {
                                    case 'Chờ xác nhận':
                                        $statusClass = 'pending';
                                        $statusIcon = 'fa-clock';
                                        break;
                                    case 'Đã xác nhận':
                                        $statusClass = 'confirmed';
                                        $statusIcon = 'fa-check-circle';
                                        break;
                                    case 'Đang giao':
                                        $statusClass = 'shipping';
                                        $statusIcon = 'fa-shipping-fast';
                                        break;
                                    case 'Đã giao':
                                        $statusClass = 'delivered';
                                        $statusIcon = 'fa-check-double';
                                        break;
                                    case 'Đã hủy':
                                        $statusClass = 'cancelled';
                                        $statusIcon = 'fa-times-circle';
                                        break;
                                }
                                ?>
                                <div class="status-badge <?php echo $statusClass; ?>">
                                    <i class="fas <?php echo $statusIcon; ?>"></i>
                                    <?php echo htmlspecialchars($order['trang_thai']); ?>
                                </div>
                                <div class="order-total">
                                    <?php echo number_format($order['tong_thanh_toan'], 0, ',', '.'); ?>₫
                                </div>
                            </div>
                            <div class="order-actions">
                                <button class="btn btn-outline btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    Chi Tiết
                                </button>
                                <?php if (trim($order['trang_thai']) === 'Chờ xác nhận'): ?>
                                <button class="btn btn-outline btn-sm" style="border-color: #ef4444; color: #ef4444;" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-times"></i>
                                    Hủy Đơn
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- History Orders -->
        <div class="orders-section" id="history-orders" style="display: none;">
            <div class="section-title">
                <i class="fas fa-history"></i>
                Lịch Sử Đơn Hàng
            </div>

            <?php 
            $historyOrders = array_filter($orders, function($o) { 
                return in_array($o['trang_thai'], ['Đã giao', 'Đã hủy']); 
            });
            ?>

            <?php if (empty($historyOrders)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>Chưa có lịch sử đơn hàng</h3>
                    <p>Bạn chưa có đơn hàng nào hoàn thành hoặc bị hủy.</p>
                </div>
            <?php else: ?>
                <?php foreach ($historyOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-code">
                                    <i class="fas fa-receipt"></i>
                                    <?php echo htmlspecialchars($order['ma_don_hang']); ?>
                                </div>
                                <div class="order-date">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-info">
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="value">
                                        <div class="label">Địa chỉ giao hàng</div>
                                        <?php echo htmlspecialchars($order['dia_chi'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-credit-card"></i>
                                    <div class="value">
                                        <div class="label">Thanh toán</div>
                                        <?php echo htmlspecialchars($order['phuong_thuc_thanh_toan']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-footer">
                            <div>
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                switch ($order['trang_thai']) {
                                    case 'Chờ xác nhận':
                                        $statusClass = 'pending';
                                        $statusIcon = 'fa-clock';
                                        break;
                                    case 'Đã xác nhận':
                                        $statusClass = 'confirmed';
                                        $statusIcon = 'fa-check-circle';
                                        break;
                                    case 'Đang giao':
                                        $statusClass = 'shipping';
                                        $statusIcon = 'fa-shipping-fast';
                                        break;
                                    case 'Đã giao':
                                        $statusClass = 'delivered';
                                        $statusIcon = 'fa-check-double';
                                        break;
                                    case 'Đã hủy':
                                        $statusClass = 'cancelled';
                                        $statusIcon = 'fa-times-circle';
                                        break;
                                }
                                ?>
                                <div class="status-badge <?php echo $statusClass; ?>">
                                    <i class="fas <?php echo $statusIcon; ?>"></i>
                                    <?php echo htmlspecialchars($order['trang_thai']); ?>
                                </div>
                                <div class="order-total">
                                    <?php echo number_format($order['tong_thanh_toan'], 0, ',', '.'); ?>₫
                                </div>
                            </div>
                            <div class="order-actions">
                                <button class="btn btn-outline btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    Chi Tiết
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="orders-section" style="display:none;">
            <div class="section-title">
                <i class="fas fa-list"></i>
                Danh Sách Đơn Hàng
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Chưa có đơn hàng nào</h3>
                    <p>Bạn chưa có đơn hàng nào. Hãy bắt đầu mua sắm ngay!</p>
                    <br>
                    <a href="trangchu.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        Mua Sắm Ngay
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-code">
                                    <i class="fas fa-receipt"></i>
                                    <?php echo htmlspecialchars($order['ma_don_hang']); ?>
                                </div>
                                <div class="order-date">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-info">
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="value">
                                        <div class="label">Địa chỉ giao hàng</div>
                                        <?php echo htmlspecialchars($order['dia_chi'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-credit-card"></i>
                                    <div class="value">
                                        <div class="label">Thanh toán</div>
                                        <?php echo htmlspecialchars($order['phuong_thuc_thanh_toan']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-footer">
                            <div>
                                <?php
                                $statusClass = '';
                                $statusIcon = '';
                                switch ($order['trang_thai']) {
                                    case 'Chờ xác nhận':
                                        $statusClass = 'pending';
                                        $statusIcon = 'fa-clock';
                                        break;
                                    case 'Đã xác nhận':
                                        $statusClass = 'confirmed';
                                        $statusIcon = 'fa-check-circle';
                                        break;
                                    case 'Đang giao':
                                        $statusClass = 'shipping';
                                        $statusIcon = 'fa-shipping-fast';
                                        break;
                                    case 'Đã giao':
                                        $statusClass = 'delivered';
                                        $statusIcon = 'fa-check-double';
                                        break;
                                    case 'Đã hủy':
                                        $statusClass = 'cancelled';
                                        $statusIcon = 'fa-times-circle';
                                        break;
                                }
                                ?>
                                <div class="status-badge <?php echo $statusClass; ?>">
                                    <i class="fas <?php echo $statusIcon; ?>"></i>
                                    <?php echo htmlspecialchars($order['trang_thai']); ?>
                                </div>
                                <div class="order-total">
                                    <?php echo number_format($order['tong_thanh_toan'], 0, ',', '.'); ?>₫
                                </div>
                            </div>
                            <div class="order-actions">
                                <button class="btn btn-outline btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    Chi Tiết
                                </button>
                                <?php if (trim($order['trang_thai']) === 'Chờ xác nhận'): ?>
                                <button class="btn btn-outline btn-sm" style="border-color: #ef4444; color: #ef4444;" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-times"></i>
                                    Hủy Đơn
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Chi Tiết Đơn Hàng -->
    <div class="modal" id="orderDetailModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalOrderCode">Chi Tiết Đơn Hàng</div>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function cancelOrder(orderId) {
            if (!confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('cancel_order', '1');
            formData.append('order_id', orderId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi hủy đơn hàng');
            });
        }
        
        function viewOrderDetails(orderId) {
            fetch('?get_order_details=' + orderId)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Có lỗi xảy ra khi lấy chi tiết đơn hàng');
                        return;
                    }
                    displayOrderDetails(data.order, data.items, data.reviews || {});
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra: ' + error.message);
                });
        }

        function displayOrderDetails(order, items, reviews) {
            document.getElementById('modalOrderCode').textContent = 'Đơn Hàng: ' + order.ma_don_hang;
            
            let html = `
                <div class="order-info">
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <div class="value">
                            <div class="label">Khách hàng</div>
                            ${order.ten_khach_hang}
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div class="value">
                            <div class="label">Số điện thoại</div>
                            ${order.so_dien_thoai}
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div class="value">
                            <div class="label">Email</div>
                            ${order.email || 'N/A'}
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="value">
                            <div class="label">Địa chỉ</div>
                            ${order.dia_chi || 'N/A'}
                        </div>
                    </div>
                </div>
            `;

            if (order.ghi_chu) {
                html += `
                    <div class="info-item" style="margin-top: 1rem;">
                        <i class="fas fa-sticky-note"></i>
                        <div class="value">
                            <div class="label">Ghi chú</div>
                            ${order.ghi_chu}
                        </div>
                    </div>
                `;
            }

            html += '<div class="order-items"><div class="section-title"><i class="fas fa-box"></i> Sản phẩm</div>';
            
            // Chỉ cho phép đánh giá khi đơn hàng đã giao
            const statusTrimmed = (order.trang_thai || '').trim();
            const orderCanReview = statusTrimmed === 'Đã giao' || statusTrimmed === 'Da giao' || statusTrimmed === 'Đã Giao';
            
            console.log('📦 Order ID:', order.id);
            console.log('📦 Order Status:', statusTrimmed);
            console.log('✅ Can Review:', orderCanReview);
            
            items.forEach(item => {
                const review = reviews[item.san_pham_id];
                const imageUrl = item.hinh_anh ? `uploads/${item.hinh_anh}` : 'images/default-product.png';
                
                html += `
                    <div class="item-card">
                        <div style="display:flex;gap:15px;align-items:center;flex:1;">
                            <img src="${imageUrl}" alt="${item.ten_san_pham}" 
                                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;"
                                 onerror="this.src='images/default-product.png'">
                            <div class="item-info" style="flex:1;">
                                <div class="item-name">${item.ten_san_pham}</div>
                                <div class="item-details">
                                    ${item.size ? 'Size: ' + item.size + ' | ' : ''}
                                    Số lượng: ${item.so_luong} | 
                                    Đơn giá: ${new Intl.NumberFormat('vi-VN').format(item.gia)}₫
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:0.5rem;align-items:flex-end;">
                            <div class="item-price">
                                ${new Intl.NumberFormat('vi-VN').format(item.thanh_tien)}₫
                            </div>
                            ${orderCanReview && !review ? `
                                <button class="btn btn-outline btn-sm" onclick="openReviewModal(${item.san_pham_id}, '${item.ten_san_pham.replace(/'/g, "\\'")}', ${order.id})">
                                    <i class="fas fa-star"></i> Đánh giá
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
                        // ===== MUA LẠI SẢN PHẨM =====
                        function reorderProduct(productId, quantity) {
                            if (!productId) return;
                            // Chuyển hướng sang trang chi tiết sản phẩm và tự động thêm vào giỏ hàng, sau đó chuyển sang giỏ hàng
                            window.location.href = 'chitiet_san_pham.php?id=' + productId + '&reorder=1&qty=' + (quantity || 1);
                        }
                
                // Hiển thị đánh giá và phản hồi (nếu có)
                if (review) {
                    html += `
                        <div style="background:#f8f9fa;border-left:4px solid #f59e0b;padding:15px;margin:10px 0;border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <div style="color:#f59e0b;font-size:20px;">
                                    ${'★'.repeat(review.rating)}${'☆'.repeat(5-review.rating)}
                                </div>
                                <small style="color:#666;">${new Date(review.created_at).toLocaleDateString('vi-VN')}</small>
                            </div>
                            <div style="color:#333;line-height:1.6;margin-bottom:10px;">
                                <strong>Đánh giá của bạn:</strong><br>
                                ${review.comment}
                            </div>
                            ${review.admin_reply ? `
                                <div style="background:#e8f4f8;border-left:3px solid #3498db;padding:10px;border-radius:6px;margin-top:10px;">
                                    <div style="color:#2980b9;font-weight:600;margin-bottom:5px;">
                                        <i class="fas fa-reply"></i> Phản hồi từ Shop:
                                    </div>
                                    <div style="color:#555;line-height:1.6;">
                                        ${review.admin_reply}
                                    </div>
                                </div>
                            ` : '<div style="color:#999;font-style:italic;font-size:13px;"><i class="fas fa-clock"></i> Đang chờ shop phản hồi...</div>'}
                        </div>
                    `;
                }
            });

            html += '</div>';

            html += `
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Tạm tính:</span>
                        <span>${new Intl.NumberFormat('vi-VN').format(order.tong_tien)}₫</span>
                    </div>
                    <div class="summary-row">
                        <span>Phí vận chuyển:</span>
                        <span>${order.phi_van_chuyen == 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN').format(order.phi_van_chuyen) + '₫'}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Tổng cộng:</span>
                        <span>${new Intl.NumberFormat('vi-VN').format(order.tong_thanh_toan)}₫</span>
                    </div>
                </div>
            `;

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('orderDetailModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('orderDetailModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('orderDetailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ===== ĐÁNH GIÁ SẢN PHẨM =====
        function openReviewModal(productId, productName, orderId) {
            console.log('🌟 Opening review modal');
            console.log('  Product ID:', productId);
            console.log('  Product Name:', productName);
            console.log('  Order ID:', orderId);
            
            // Mở modal
            const modal = document.getElementById('reviewModal');
            if (!modal) {
                console.error('❌ Review modal element not found!');
                alert('Lỗi: Không tìm thấy form đánh giá!');
                return;
            }
            
            // Set values
            document.getElementById('reviewProductId').value = productId;
            document.getElementById('reviewProductName').textContent = productName;
            
            // Reset form
            document.getElementById('reviewForm').reset();
            document.getElementById('reviewRating').value = 5;
            updateReviewStars(5);
            
            // Show modal
            modal.classList.add('active');
            console.log('✅ Review modal opened successfully');
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').classList.remove('active');
            document.getElementById('reviewForm').reset();
            updateReviewStars(5);
        }

        function updateReviewStars(rating) {
            document.getElementById('reviewRating').value = rating;
            const stars = document.querySelectorAll('#reviewStars i');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.remove('far');
                    star.classList.add('fas');
                    star.style.color = '#f59e0b';
                } else {
                    star.classList.remove('fas');
                    star.classList.add('far');
                    star.style.color = '#d1d5db';
                }
            });
        }

        // ===== XỬ LÝ GỬI ĐÁNH GIÁ =====
        // Đợi DOM load xong
        document.addEventListener('DOMContentLoaded', function() {
            const reviewForm = document.getElementById('reviewForm');
            
            if (!reviewForm) {
                console.error('❌ Review form not found!');
                return;
            }
            
            console.log('✅ Review form found, attaching submit handler');
            
            reviewForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                console.log('🔍 REVIEW FORM SUBMIT EVENT TRIGGERED');
                
                const formData = new FormData(this);
                
                // Debug log - chi tiết form data
                console.log('📋 Form Data:');
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}: ${value}`);
                }
                
                console.log('🚀 Sending to xu_ly_danh_gia.php...');
                
                fetch('xu_ly_danh_gia.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('📥 Response status:', response.status);
                    console.log('📥 Response OK:', response.ok);
                    
                    // Kiểm tra response text trước
                    return response.text().then(text => {
                        console.log('📄 Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('❌ JSON parse error:', e);
                            console.error('❌ Response was:', text);
                            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log('✅ Parsed data:', data);
                    closeReviewModal();
                    
                    if (data.success) {
                        // Chỉ hiển thị thông báo thành công, KHÔNG reload
                        alert('✅ Cảm ơn bạn đã đánh giá sản phẩm!\n\nĐánh giá của bạn đã được gửi đến quản trị viên.');
                    } else {
                        alert('❌ ' + (data.message || 'Có lỗi xảy ra khi gửi đánh giá'));
                    }
                })
                .catch(error => {
                    console.error('❌ Error:', error);
                    alert('❌ Có lỗi xảy ra khi gửi đánh giá: ' + error.message);
                });
            });
            
            console.log('✅ Submit handler attached successfully');
        });

        // Close review modal when clicking outside
        document.getElementById('reviewModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });

        // Tab switching function
        function switchTab(tab) {
            const tabs = document.querySelectorAll('.tab-btn');
            const activeOrders = document.getElementById('active-orders');
            const historyOrders = document.getElementById('history-orders');

            tabs.forEach(t => t.classList.remove('active'));

            if (tab === 'active') {
                tabs[0].classList.add('active');
                activeOrders.style.display = 'block';
                historyOrders.style.display = 'none';
            } else {
                tabs[1].classList.add('active');
                activeOrders.style.display = 'none';
                historyOrders.style.display = 'block';
            }
        }
    </script>

    <!-- Modal Đánh Giá Sản Phẩm -->
    <div class="modal" id="reviewModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-star" style="color: #f59e0b;"></i>
                    Đánh Giá Sản Phẩm
                </div>
                <button class="close-btn" onclick="closeReviewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="background: linear-gradient(135deg, var(--beige-1), var(--beige-2)); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="font-weight: 600; color: var(--indigo);" id="reviewProductName"></div>
                </div>

                <form id="reviewForm">
                    <input type="hidden" name="san_pham_id" id="reviewProductId">
                    <input type="hidden" name="rating" id="reviewRating" value="5">
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 600; color: var(--indigo); margin-bottom: 0.75rem;">
                            <i class="fas fa-star" style="color: #f59e0b;"></i> Đánh giá của bạn:
                        </label>
                        <div id="reviewStars" style="font-size: 2rem; cursor: pointer;">
                            <i class="fas fa-star" onclick="updateReviewStars(1)" style="color: #f59e0b;"></i>
                            <i class="fas fa-star" onclick="updateReviewStars(2)" style="color: #f59e0b;"></i>
                            <i class="fas fa-star" onclick="updateReviewStars(3)" style="color: #f59e0b;"></i>
                            <i class="fas fa-star" onclick="updateReviewStars(4)" style="color: #f59e0b;"></i>
                            <i class="fas fa-star" onclick="updateReviewStars(5)" style="color: #f59e0b;"></i>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 600; color: var(--indigo); margin-bottom: 0.5rem;">
                            <i class="fas fa-comment"></i> Nhận xét:
                        </label>
                        <textarea 
                            name="comment" 
                            rows="4" 
                            required
                            placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..."
                            style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-family: inherit; resize: vertical;"
                        ></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-paper-plane"></i>
                            Gửi Đánh Giá
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">
                            <i class="fas fa-times"></i>
                            Hủy
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Thông Báo -->
    <div class="modal" id="notificationModal" style="display:none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;">
                <div class="modal-title">
                    <i class="fas fa-bell"></i> Thông Báo
                </div>
                <button class="close-btn" onclick="closeNotifications()" style="color:white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding:1rem;">
                <div style="margin-bottom:1rem;text-align:right;">
                    <button onclick="markAllRead()" class="btn btn-outline btn-sm">
                        <i class="fas fa-check-double"></i> Đánh dấu tất cả đã đọc
                    </button>
                </div>
                <div id="notificationList" style="max-height:400px;overflow-y:auto;">
                    <div style="text-align:center;padding:2rem;color:#999;">
                        <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                        <p>Đang tải...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load notifications
        function loadNotifications() {
            fetch('xu_ly_thong_bao.php?action=get_notifications')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(data.unread_count);
                        displayNotifications(data.notifications);
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }

        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function displayNotifications(notifications) {
            const list = document.getElementById('notificationList');
            
            if (notifications.length === 0) {
                list.innerHTML = `
                    <div style="text-align:center;padding:3rem;color:#999;">
                        <i class="fas fa-bell-slash" style="font-size:3rem;margin-bottom:1rem;"></i>
                        <p>Chưa có thông báo nào</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = notifications.map(notif => {
                const icon = notif.type === 'review_reply' ? '💬' : notif.type === 'order_delivered' ? '📦' : '🔔';
                const bgColor = notif.is_read == 0 ? '#f0f9ff' : '#fff';
                const borderColor = notif.is_read == 0 ? '#3b82f6' : '#e5e7eb';
                
                return `
                    <div style="border:1px solid ${borderColor};background:${bgColor};padding:1rem;margin-bottom:0.5rem;border-radius:8px;cursor:pointer;" 
                         onclick="handleNotificationClick(${notif.id}, '${notif.link}')">
                        <div style="display:flex;align-items:start;gap:0.75rem;">
                            <div style="font-size:1.5rem;">${icon}</div>
                            <div style="flex:1;">
                                <div style="font-weight:600;color:#1f2937;margin-bottom:0.25rem;">${notif.title}</div>
                                <div style="color:#6b7280;font-size:0.875rem;margin-bottom:0.5rem;">${notif.message}</div>
                                <div style="color:#9ca3af;font-size:0.75rem;">
                                    <i class="fas fa-clock"></i> ${new Date(notif.created_at).toLocaleString('vi-VN')}
                                </div>
                            </div>
                            ${notif.is_read == 0 ? '<div style="width:8px;height:8px;background:#3b82f6;border-radius:50%;"></div>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function toggleNotifications() {
            const modal = document.getElementById('notificationModal');
            if (modal.style.display === 'none' || !modal.style.display) {
                modal.style.display = 'block';
                loadNotifications();
            } else {
                modal.style.display = 'none';
            }
        }

        function closeNotifications() {
            document.getElementById('notificationModal').style.display = 'none';
        }

        function handleNotificationClick(id, link) {
            // Mark as read
            fetch('xu_ly_thong_bao.php?action=mark_read', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `notification_id=${id}`
            }).then(() => {
                if (link) {
                    window.location.href = link;
                } else {
                    loadNotifications();
                }
            });
        }

        function markAllRead() {
            fetch('xu_ly_thong_bao.php?action=mark_all_read', {
                method: 'POST'
            }).then(() => {
                loadNotifications();
            });
        }

        // Load notifications on page load and every 30 seconds
        loadNotifications();
        setInterval(loadNotifications, 30000);
    </script>
    <link rel="stylesheet" href="assets/chatbot.css">
    <link rel="stylesheet" href="assets/notifications.css">
    <?php include 'assets/chatbot_session.php'; ?>
    <script src="assets/notification_bell.js" defer></script>
    <script src="assets/chatbot.js" defer></script>
</body>
</html>
