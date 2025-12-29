<?php
/**
 * Trang Thống kê Quản Trị
 * Trang quản trị viên
 */

// Tắt hiển thị lỗi trên production (chỉ log lỗi)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ===== XỬ LÝ AJAX THỐNG KÊ NGÀY (PHẢI ĐẶT LÊN ĐẦU FILE) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'date_stats') {
    require_once __DIR__ . '/connect.php';
    $mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli_conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Kết nối DB thất bại: ' . $mysqli_conn->connect_error]);
        exit;
    }
    $mysqli_conn->set_charset('utf8mb4');
    $conn = $mysqli_conn;
    header('Content-Type: application/json');
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $start = preg_replace('/[^0-9\-]/', '', $start);
    $end = preg_replace('/[^0-9\-]/', '', $end);
    if (!$start || !$end) {
        echo json_encode(['error' => 'Thiếu ngày bắt đầu hoặc kết thúc']);
        exit;
    }
    $start .= ' 00:00:00';
    $end .= ' 23:59:59';
    // Lấy tổng số sản phẩm bán ra và tổng doanh thu, danh sách tên sản phẩm
    $sql = "SELECT sp.ten_san_pham, SUM(ct.so_luong) as total_qty, SUM(ct.so_luong * ct.gia) as total_revenue FROM chi_tiet_don_hang ct JOIN san_pham sp ON ct.san_pham_id = sp.id JOIN don_hang dh ON ct.don_hang_id = dh.id WHERE dh.ngay_dat >= ? AND dh.ngay_dat <= ? AND dh.trang_thai != 'Đã hủy' GROUP BY sp.id, sp.ten_san_pham ORDER BY total_qty DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $products = [];
    $total_qty = 0;
    $total_revenue = 0;
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $products[] = $row['ten_san_pham'];
        $total_qty += (int)$row['total_qty'];
        $total_revenue += (float)$row['total_revenue'];
    }
    echo json_encode([
        'total_qty' => $total_qty,
        'total_revenue' => $total_revenue,
        'products' => $products
    ]);
    exit;
}
// ===== HẾT XỬ LÝ AJAX THỐNG KÊ NGÀY =====
// Load database connection
require_once __DIR__ . '/connect.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

// Kết nối database mysqli (để tương thích với code cũ)
$mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli_conn->connect_error) {
    die("Kết nối thất bại: " . $mysqli_conn->connect_error);
}
$mysqli_conn->set_charset("utf8mb4");
$conn = $mysqli_conn; // Override for this file

// Tìm tên cột danh mục
$dm_cols = [];
$res = $conn->query("SHOW COLUMNS FROM danh_muc");
// Đặt giới hạn số lượng sản phẩm hiển thị (mặc định 50)
$limit = 50;
while ($r = $res->fetch_assoc()) {
    $dm_cols[] = $r['Field'];
}
$dm_name_col = 'ten_danh_muc';
foreach (['ten_danh_muc', 'ten_san_pham', 'ten', 'name'] as $candidate) {
    if (in_array($candidate, $dm_cols)) {
        $dm_name_col = $candidate;
        break;
    }
}

// Lấy danh sách sản phẩm
$san_pham_list = [];
$result = $conn->query("SELECT sp.*, dm.`$dm_name_col` as ten_danh_muc FROM san_pham sp LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id ORDER BY sp.id DESC LIMIT $limit");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $san_pham_list[] = $row;
    }
}

// Lấy danh sách danh mục
$danh_muc_list = [];
$result = $conn->query("SELECT id, `$dm_name_col` as ten_danh_muc, mo_ta FROM danh_muc ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $danh_muc_list[] = $row;
    }
}

// Tự động phát hiện tên cột người dùng
$nd_cols = [];
$res = $conn->query("SHOW COLUMNS FROM nguoi_dung");
while ($r = $res->fetch_assoc()) {
    $nd_cols[] = $r['Field'];
}
$tendangnhap_col = 'tendangnhap';
$hoten_col = 'hoten';
foreach (['tendangnhap', 'username', 'ten_dang_nhap'] as $c) {
    if (in_array($c, $nd_cols)) { $tendangnhap_col = $c; break; }
}
foreach (['hoten', 'ho_ten', 'name', 'full_name'] as $c) {
    if (in_array($c, $nd_cols)) { $hoten_col = $c; break; }
}

// Lấy danh sách đơn hàng
$don_hang_list = [];
$result = $conn->query("
    SELECT dh.*, nd.`$tendangnhap_col` as tendangnhap_kh, nd.`$hoten_col` as hoten_kh 
    FROM don_hang dh 
    LEFT JOIN nguoi_dung nd ON dh.nguoi_dung_id = nd.id 
    ORDER BY dh.id DESC 
    LIMIT 50
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $don_hang_list[] = $row;
    }
}
$don_hang_list = [];
$result = $conn->query("
    SELECT dh.*, nd.`$tendangnhap_col` as tendangnhap_kh, nd.`$hoten_col` as hoten_kh 
    FROM don_hang dh 
    LEFT JOIN nguoi_dung nd ON dh.nguoi_dung_id = nd.id 
    ORDER BY dh.id DESC 
    LIMIT 50
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Thêm thông tin user_id và email vào đơn hàng để admin dễ kiểm tra
        $row['debug_user'] = 'ID: ' . $row['nguoi_dung_id'] . ' | Email: ' . $row['email'];
        $don_hang_list[] = $row;
    }
}

// Lấy danh sách người dùng
$nguoi_dung_list = [];

$result = $conn->query("SELECT *, `$tendangnhap_col` as tendangnhap, `$hoten_col` as hoten FROM nguoi_dung ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $nguoi_dung_list[] = $row;
    }
}

// Lấy danh sách đánh giá
$danh_gia_list = [];
$result = $conn->query("
    SELECT dg.*, sp.ten_san_pham, dg.user_name
    FROM danh_gia dg 
    LEFT JOIN san_pham sp ON dg.san_pham_id = sp.id 
    ORDER BY dg.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $danh_gia_list[] = $row;
    }
}

// Lấy danh sách voucher
$voucher_list = [];
$result = $conn->query("SELECT * FROM voucher ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $voucher_list[] = $row;
    }
}

// Thống kê
$kho_total_products = count($san_pham_list);
$totalProducts = $kho_total_products;
$totalOrders = $conn->query("SELECT COUNT(*) as total FROM don_hang")->fetch_assoc()['total'];
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM nguoi_dung")->fetch_assoc()['total'];
$totalRevenue = $conn->query("SELECT SUM(COALESCE(tong_thanh_toan, tong_tien)) as total FROM don_hang WHERE trang_thai != 'Đã hủy'")->fetch_assoc()['total'] ?? 0;

// Doanh thu theo tháng trong năm hiện tại
$year = date('Y');
$monthlyRevenue = array_fill(1, 12, 0);
$result = $conn->query("SELECT MONTH(ngay_dat) as m, SUM(COALESCE(tong_thanh_toan, tong_tien)) as total FROM don_hang WHERE trang_thai != 'Đã hủy' AND YEAR(ngay_dat) = $year GROUP BY m");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monthlyRevenue[(int)$row['m']] = (float)$row['total'];
    }
}

// Đếm số tin nhắn mới từ khách (chưa có reply từ admin)
$unreadChats = 0;
$chatCountResult = $conn->query("SELECT SUM(unread_count) as total FROM chat_sessions WHERE status = 'active'");
if ($chatCountResult) {
    $unreadChats = $chatCountResult->fetch_assoc()['total'] ?? 0;
}

// Top 5 sản phẩm bán chạy nhất (theo số lượng và doanh thu)
$topProductLabels = [];
$topProductData = [];
$topProductRevenue = [];
$topProductNames = [];
$result = $conn->query("SELECT sp.ten_san_pham, SUM(ct.so_luong) as total_qty, SUM(ct.so_luong * ct.gia) as total_revenue FROM chi_tiet_don_hang ct JOIN san_pham sp ON ct.san_pham_id = sp.id JOIN don_hang dh ON ct.don_hang_id = dh.id WHERE dh.trang_thai != 'Đã hủy' GROUP BY sp.id, sp.ten_san_pham ORDER BY total_qty DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topProductLabels[] = $row['ten_san_pham'];
        $topProductData[] = (int)$row['total_qty'];
        $topProductRevenue[] = (float)$row['total_revenue'];
        $topProductNames[] = $row['ten_san_pham'];
    }
}
$top1Name = $topProductNames[0] ?? '';
$top1Revenue = $topProductRevenue[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị - Đơn hàng</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
        
        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #5A9FA3 0%, #4a8f93 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            background: rgba(255,255,255,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 12px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #5A9FA3;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .sidebar-header h2 {
            font-size: 20px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .menu-item {
            padding: 0;
            margin: 5px 0;
        }
        .menu-item a {
            display: flex;
            align-items: center;
            padding: 14px 25px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
        }
        .menu-item a:hover,
        .menu-item a.active {
            background: rgba(255,255,255,0.15);
            color: #FFD166;
            padding-left: 35px;
        }
        .menu-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 16px;
        }
        .menu-item {
            position: relative;
        }
        .menu-badge {
            position: absolute;
            top: 8px;
            right: 20px;
            background: #EF476F;
            color: white;
            border-radius: 10px;
            padding: 2px 7px;
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(239, 71, 111, 0.4);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px;
            width: calc(100% - 280px);
        }
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .top-bar h1 {
            font-size: 26px;
            color: #333;
            font-weight: 600;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #EF476F;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: #df375f;
            transform: translateY(-2px);
        }
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }
        .stat-card.blue { border-left-color: #5A9FA3; }
        .stat-card.green { border-left-color: #06D6A0; }
        .stat-card.orange { border-left-color: #FFD166; }
        .stat-card.red { border-left-color: #EF476F; }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }
        
        /* SECTION */
        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
        }
        .section h2 {
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }
        .section h2 i {
            margin-right: 10px;
            color: #5A9FA3;
        }
        
        /* BULK ACTIONS */
        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .bulk-actions label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #555;
            cursor: pointer;
        }
        .bulk-actions input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .item-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* BUTTONS */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #5A9FA3;
            color: white;
        }
        .btn-primary:hover {
            background: #4a8f93;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90,159,163,0.3);
        }
        .btn-warning {
            background: #FFD166;
            color: #333;
        }
        .btn-warning:hover {
            background: #f0c156;
        }
        .btn-danger {
            background: #EF476F;
            color: white;
        }
        .btn-danger:hover {
            background: #df375f;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        /* TABLE */
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #f0f2f5;
        }
        th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            overflow-y: auto;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            max-width: 600px;
            margin: 50px auto;
            padding: 35px;
            border-radius: 16px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-title {
            font-size: 22px;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #5A9FA3;
            font-weight: 600;
        }
        .close-modal {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 32px;
            cursor: pointer;
            color: #999;
            transition: all 0.3s;
        }
        .close-modal:hover {
            color: #333;
            transform: rotate(90deg);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #5A9FA3;
            outline: none;
            box-shadow: 0 0 0 3px rgba(90,159,163,0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d4f4dd; color: #0e6027; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        /* Promo Tabs */
        .promo-tab {
            padding: 12px 24px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .promo-tab:hover {
            border-color: #5A9FA3;
            background: #f0f7f7;
        }
        .promo-tab.active {
            background: linear-gradient(135deg, #5A9FA3, #4DD4D6);
            color: white;
            border-color: transparent;
        }
        .promo-tab i { margin-right: 8px; }
        
        /* Product locked state */
        .product-locked {
            opacity: 0.6;
            background: #f8f8f8 !important;
        }
        .product-locked:hover {
            background: #f0f0f0 !important;
        }
        
        .order-status-select {
            cursor: pointer;
            transition: all 0.3s;
        }
        .order-status-select:hover {
            box-shadow: 0 0 0 3px rgba(90,159,163,0.2);
        }
        .order-status-select option {
            padding: 8px;
        }
        
        .user-role-select {
            cursor: pointer;
            transition: all 0.3s;
        }
        .user-role-select:hover {
            box-shadow: 0 0 0 3px rgba(90,159,163,0.2);
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo"><img src="images/logo.png" alt="Logo" style="width: 80px; height: 80px; object-fit: contain; border-radius: 12px;"></div>
            <h2>Mỹ Phương</h2>
            <p>Quản Trị Viên</p>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-item">
                <a href="#dashboard" class="active" onclick="showSection('dashboard')">
                    <i class="fas fa-tachometer-alt"></i> Thống kê
                </a>
            </div>
            <div class="menu-item">
                <a href="#products" onclick="showSection('products')">
                    <i class="fas fa-box"></i> Sản Phẩm
                </a>
            </div>
            <div class="menu-item">
                <a href="#categories" onclick="showSection('categories')">
                    <i class="fas fa-tags"></i> Danh Mục
                </a>
            </div>
            <div class="menu-item">
                <a href="#orders" onclick="showSection('orders')">
                    <i class="fas fa-shopping-cart"></i> Đơn Hàng
                </a>
            </div>
            <div class="menu-item">
                <a href="#users" onclick="showSection('users')">
                    <i class="fas fa-users"></i> Khách Hàng
                </a>
            </div>
            <div class="menu-item">
                <a href="#reviews" onclick="showSection('reviews')">
                    <i class="fas fa-star"></i> Đánh Giá
                </a>
            </div>
            <div class="menu-item">
                <a href="#chat" onclick="showSection('chat')">
                    <i class="fas fa-comments"></i> Chat Khách Hàng
                </a>
                <?php if ($unreadChats > 0): ?>
                    <span class="menu-badge"><?= $unreadChats ?></span>
                <?php endif; ?>
            </div>
            <div class="menu-item">
                <a href="#warehouse" onclick="showSection('warehouse')">
                    <i class="fas fa-warehouse"></i> Kho Hàng
                </a>
            </div>

            <div class="menu-item">
                <a href="#promotions" onclick="showSection('promotions')">
                    <i class="fas fa-percent"></i> Khuyến Mãi
                </a>
            </div>
            <div class="menu-item">
                <a href="trangchu.php">
                    <i class="fas fa-home"></i> Về Trang Chủ
                </a>
            </div>
        </nav>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <h1 id="pageTitle">Thống kê</h1>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Đăng Xuất
            </button>
        </div>

        <!-- Thống kê SECTION -->
        <div id="dashboard-section" class="content-section">
            <!-- Thống kê theo khoảng ngày -->
            <div class="section" style="margin-bottom: 25px;">
                <h2><i class="fas fa-calendar-alt"></i> Thống kê theo khoảng ngày</h2>
                <form id="dateRangeForm" style="display:flex;gap:15px;align-items:center;margin:15px 0;">
                    <label> Từ ngày:
                        <input type="date" id="startDate" name="startDate" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
                    </label>
                    <label> Đến ngày:
                        <input type="date" id="endDate" name="endDate" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;">
                    </label>
                    <button type="submit" class="btn btn-primary">Xem thống kê</button>
                    <button type="button" id="clearStatsBtn" class="btn btn-danger" style="margin-left:10px;">Xóa kết quả</button>
                </form>
                <div id="dateStatsResult" style="margin-top:18px;"></div>
            </div>
            <div class="stats-grid">
                
                <div class="stat-card blue">
                    <h3>Tổng Sản Phẩm</h3>
                    <div class="value"><?= $totalProducts ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Tổng Đơn Hàng</h3>
                    <div class="value"><?= $totalOrders ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>Khách Hàng</h3>
                    <div class="value"><?= $totalUsers ?></div>
                </div>
                <div class="stat-card red">
                    <h3>Doanh Thu</h3>
                    <div class="value"><?= number_format($totalRevenue, 0, ',', '.') ?>₫</div>
                </div>
                <?php
                $totalDelivered = $conn->query("SELECT COUNT(*) as total FROM don_hang WHERE trang_thai = 'Đã giao'")->fetch_assoc()['total'] ?? 0;
                $totalOrderForPercent = $conn->query("SELECT COUNT(*) as total FROM don_hang")->fetch_assoc()['total'] ?? 0;
                $deliveryPercent = $totalOrderForPercent > 0 ? round($totalDelivered / $totalOrderForPercent * 100, 1) : 0;
                ?>
                <div class="stat-card" style="border-left-color:#118AB2;">
                    <h3>% Giao Hàng Thành Công</h3>
                    <div class="value" style="color:#118AB2;">
                        <?= $deliveryPercent ?>%
                    </div>
                </div>
            </div>
            
            <div class="section">
                                <h2><i class="fas fa-chart-bar"></i> Doanh Thu Theo Tháng (<?php echo $year; ?>)</h2>
                                <canvas id="revenueChart" height="80"></canvas>
                                <script>
                                        // Thống kê theo khoảng ngày
                                        $('#dateRangeForm').on('submit', function(e) {
                                            e.preventDefault();
                                            const start = $('#startDate').val();
                                            const end = $('#endDate').val();
                                            if (!start || !end) {
                                                toastr.error('Vui lòng chọn đủ ngày bắt đầu và kết thúc');
                                                return;
                                            }
                                            $('#dateStatsResult').html('<i>Đang tải...</i>');
                                                                                    // Nút xóa kết quả thống kê
                                                                                    $('#clearStatsBtn').on('click', function() {
                                                                                        $('#dateStatsResult').html('');
                                                                                    });
                                            $.post('', { action: 'date_stats', start: start, end: end }, function(res) {
                                                if (res.error) {
                                                    toastr.error(res.error);
                                                    $('#dateStatsResult').html('');
                                                    return;
                                                }
                                                let html = `<div style="margin-bottom:12px;font-size:1.1rem;">
                                                    <b>Tổng sản phẩm bán ra:</b> ${res.total_qty} &nbsp; | &nbsp; <b>Tổng tiền:</b> ${parseInt(res.total_revenue).toLocaleString('vi-VN')}đ
                                                </div>`;
                                                if (res.products && res.products.length > 0) {
                                                    html += '<div><b>Tên sản phẩm:</b> ' + res.products.map(p => `<span style=\"color:#118AB2;\">${p}</span>`).join(', ') + '</div>';
                                                } else {
                                                    html += '<div>Không có sản phẩm nào được bán trong khoảng này.</div>';
                                                }
                                                $('#dateStatsResult').html(html);
                                            }, 'json').fail(function(xhr) {
                                                toastr.error('Lỗi kết nối server');
                                                $('#dateStatsResult').html('');
                                            });
                                        });
                                document.addEventListener('DOMContentLoaded', function() {
                                    var ctx = document.getElementById('revenueChart').getContext('2d');
                                    var revenueChart = new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
                                            datasets: [{
                                                label: 'Doanh thu (₫)',
                                                data: <?php echo json_encode(array_values($monthlyRevenue)); ?>,
                                                backgroundColor: 'rgba(90, 159, 163, 0.7)',
                                                borderColor: 'rgba(90, 159, 163, 1)',
                                                borderWidth: 2,
                                                borderRadius: 6,
                                                maxBarThickness: 40
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            plugins: {
                                                legend: { display: false },
                                                title: { display: false }
                                            },
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    ticks: {
                                                        callback: function(value) {
                                                            return value.toLocaleString('vi-VN') + '₫';
                                                        },
                                                        color: '#444',
                                                        font: { size: 13 }
                                                    },
                                                    grid: { color: '#eee' }
                                                },
                                                x: {
                                                    ticks: { color: '#444', font: { size: 13 } },
                                                    grid: { display: false }
                                                }
                                            }
                                        }
                                    });
                                });
                                </script>
            </div>
            <div class="section">
                <h2><i class="fas fa-chart-pie"></i> Top 5 Sản Phẩm Bán Chạy</h2>
                <div style="max-width:340px;margin:auto;">
                    <canvas id="topProductChart" width="320" height="220"></canvas>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var ctx2 = document.getElementById('topProductChart').getContext('2d');
                    var topProductChart = new Chart(ctx2, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode($topProductLabels); ?>,
                            datasets: [{
                                label: 'Số lượng bán',
                                data: <?php echo json_encode($topProductData); ?>,
                                backgroundColor: [
                                    '#5A9FA3', '#FFD166', '#EF476F', '#06D6A0', '#118AB2'
                                ],
                                borderColor: '#fff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'bottom', labels: { font: { size: 14 } } },
                                title: { display: false }
                            }
                        }
                    });
                });
                </script>
                <div style="margin-top:24px;">
                    <table style="width:100%;border-collapse:collapse;background:#f8f9fa;border-radius:10px;overflow:hidden;">
                        <thead>
                            <tr style="background:#e0e0e0;">
                                <th style="padding:10px 8px;">#</th>
                                <th style="padding:10px 8px;">Hình</th>
                                <th style="padding:10px 8px;">Tên sản phẩm</th>
                                <th style="padding:10px 8px;">Giá</th>
                                <th style="padding:10px 8px;">Số lượng bán</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $result = $conn->query("SELECT sp.id, sp.ten_san_pham, sp.hinh_anh, sp.gia, SUM(ct.so_luong) as total_qty FROM chi_tiet_don_hang ct JOIN san_pham sp ON ct.san_pham_id = sp.id JOIN don_hang dh ON ct.don_hang_id = dh.id WHERE dh.trang_thai != 'Đã hủy' GROUP BY sp.id, sp.ten_san_pham, sp.hinh_anh, sp.gia ORDER BY total_qty DESC LIMIT 5");
                        if ($result) {
                            $i = 1;
                            while ($row = $result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td style="padding:10px 8px;text-align:center;">' . $i . '</td>';
                                echo '<td style="padding:10px 8px;text-align:center;"><img src="uploads/' . htmlspecialchars($row['hinh_anh']) . '" style="width:48px;height:48px;object-fit:cover;border-radius:8px;"></td>';
                                echo '<td style="padding:10px 8px;">' . htmlspecialchars($row['ten_san_pham']) . '</td>';
                                echo '<td style="padding:10px 8px;white-space:nowrap;">' . number_format($row['gia'],0,',','.') . '₫</td>';
                                echo '<td style="padding:10px 8px;text-align:center;font-weight:600;color:#118AB2;">' . $row['total_qty'] . '</td>';
                                echo '</tr>';
                                $i++;
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="section">
                <h2><i class="fas fa-chart-line"></i> Thống kê Hệ Thống</h2>
                <p style="color: #666; line-height: 1.8;">
                    Chào mừng bạn đến với trang quản trị. Chọn một mục bên trái để bắt đầu quản lý.
                </p>
            </div>
        </div>

        <!-- PRODUCTS SECTION -->
        <!-- PRODUCTS SECTION -->
        <div id="products-section" class="content-section" style="display:none;">
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-box"></i> Quản Lý Sản Phẩm</h2>
                    <button class="btn btn-primary" onclick="openAddProductModal()">
                        <i class="fas fa-plus"></i> Thêm Sản Phẩm
                    </button>
                </div>
                <!-- Tabs danh mục động -->
                <div id="product-category-tabs" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="promo-tab active" data-category="all">Tất cả</button>
                    <?php
                    $dm_has_sp = [];
                    foreach ($san_pham_list as $sp) {
                        $dm = $sp['ten_danh_muc'] ?? '';
                        if ($dm) $dm_has_sp[$dm] = true;
                    }
                    foreach ($danh_muc_list as $dm) {
                        if (!empty($dm_has_sp[$dm['ten_danh_muc']])) {
                            echo '<button class="promo-tab" data-category="'.htmlspecialchars($dm['ten_danh_muc']).'">'.htmlspecialchars($dm['ten_danh_muc']).'</button>';
                        }
                    }
                    ?>
                </div>
                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="selectAllProducts">
                        Chọn tất cả
                    </label>
                    <button class="btn btn-danger" id="deleteSelectedProducts" style="display:none;">
                        <i class="fas fa-trash"></i> Xóa đã chọn (<span id="selectedProductsCount">0</span>)
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr class="order-row">
                                <th><input type="checkbox" class="selectAllCheckbox" data-target="product"></th>
                                <th>ID</th>
                                <th>Hình</th>
                                <th>Mã SP</th>
                                <th>Tên Sản Phẩm</th>
                                <th>Danh Mục</th>
                                <th>Giá</th>
                                <th>Giá Giảm</th>
                                <th>Số Lượng</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody id="product-table-body">
                            <?php foreach ($san_pham_list as $sp): ?>
                            <tr class="<?= $sp['trang_thai'] == 0 ? 'product-locked' : '' ?>" data-category="<?= htmlspecialchars($sp['ten_danh_muc'] ?? '') ?>">
                                <td><input type="checkbox" class="item-checkbox product-checkbox" data-id="<?= $sp['id'] ?>"></td>
                                <td><?= $sp['id'] ?></td>
                                <td><img src="uploads/<?= htmlspecialchars($sp['hinh_anh']) ?>" class="product-img" alt=""></td>
                                <td><?= htmlspecialchars($sp['ma_san_pham']) ?></td>
                                <td><?= htmlspecialchars($sp['ten_san_pham']) ?></td>
                                <td><?= htmlspecialchars($sp['ten_danh_muc'] ?? 'N/A') ?></td>
                                <td><?= number_format($sp['gia'], 0, ',', '.') ?>₫</td>
                                <td>
                                    <?php if (isset($sp['gia_giam']) && $sp['gia_giam'] > 0): ?>
                                        <span style="color:#EF476F; font-weight:600;"><?= number_format($sp['gia_giam'], 0, ',', '.') ?>₫</span>
                                    <?php else: ?>
                                        <span style="color:#999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $sp['so_luong'] ?></td>
                                <td>
                                    <?php if ($sp['trang_thai'] == 1): ?>
                                        <span class="badge badge-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Hết hàng</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-<?= $sp['trang_thai'] == 1 ? 'secondary' : 'success' ?> btn-sm btn-toggle-product" 
                                            data-id="<?= $sp['id'] ?>" 
                                            data-status="<?= $sp['trang_thai'] ?>"
                                            title="<?= $sp['trang_thai'] == 1 ? 'Khóa sản phẩm' : 'Mở khóa sản phẩm' ?>">
                                        <i class="fas fa-<?= $sp['trang_thai'] == 1 ? 'lock' : 'unlock' ?>"></i>
                                    </button>
                                    <button class="btn btn-warning btn-sm btn-edit-product" data-id="<?= $sp['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-delete-product" data-id="<?= $sp['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <script>
            // Tab click: lọc sản phẩm đúng danh mục
            $(document).ready(function() {
                $('#product-category-tabs .promo-tab').on('click', function() {
                    $('#product-category-tabs .promo-tab').removeClass('active');
                    $(this).addClass('active');
                    var cat = $(this).data('category');
                    $('#product-table-body tr').each(function() {
                        var rowCat = $(this).data('category');
                        if (cat === 'all' || (rowCat && rowCat === cat)) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });
            });
            </script>
            </div>
        </div>

        <!-- CATEGORIES SECTION -->
        <div id="categories-section" class="content-section" style="display:none;">
            <div class="section">
                <div class="section-header">
                    <h2><i class="fas fa-tags"></i> Quản Lý Danh Mục</h2>
                    <button class="btn btn-primary" onclick="openAddCategoryModal()">
                        <i class="fas fa-plus"></i> Thêm Danh Mục
                    </button>
                </div>
                
                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="selectAllCategories">
                        Chọn tất cả
                    </label>
                    <button class="btn btn-danger" id="deleteSelectedCategories" style="display:none;">
                        <i class="fas fa-trash"></i> Xóa đã chọn (<span id="selectedCategoriesCount">0</span>)
                    </button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="selectAllCheckbox" data-target="category"></th>
                                <th>ID</th>
                                <th>Tên Danh Mục</th>
                                <th>Mô Tả</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($danh_muc_list as $dm): ?>
                            <tr>
                                <td><input type="checkbox" class="item-checkbox category-checkbox" data-id="<?= $dm['id'] ?>"></td>
                                <td><?= $dm['id'] ?></td>
                                <td><?= htmlspecialchars($dm['ten_danh_muc']) ?></td>
                                <td><?= htmlspecialchars($dm['mo_ta'] ?? '') ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm btn-edit-category" data-id="<?= $dm['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-delete-category" data-id="<?= $dm['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ORDERS SECTION -->
        <div id="orders-section" class="content-section" style="display:none;">
            <div class="section">
                <h2><i class="fas fa-shopping-cart"></i> Quản Lý Đơn Hàng</h2>
                <!-- Tabs phân loại trạng thái đơn hàng -->
                <div id="orderTabs" style="margin-bottom: 18px; display: flex; gap: 10px;">
                    <button class="order-tab-btn btn btn-primary btn-sm" data-status="Chờ xử lý">Chờ xử lý</button>
                    <button class="order-tab-btn btn btn-info btn-sm" data-status="Đang xử lý">Đang xử lý</button>
                    <button class="order-tab-btn btn btn-warning btn-sm" data-status="Đang giao">Đang giao</button>
                    <button class="order-tab-btn btn btn-success btn-sm" data-status="Đã giao">Đã giao</button>
                    <button class="order-tab-btn btn btn-danger btn-sm" data-status="Đã hủy">Đã hủy</button>
                    <button class="order-tab-btn btn btn-secondary btn-sm" data-status="">Tất cả</button>
                </div>
                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="selectAllOrders">
                        Chọn tất cả
                    </label>
                    <button class="btn btn-danger" id="deleteSelectedOrders" style="display:none;">
                        <i class="fas fa-trash"></i> Xóa đã chọn (<span id="selectedOrdersCount">0</span>)
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="selectAllCheckbox" data-target="order"></th>
                                <th>Mã ĐH</th>
                                <th>Khách Hàng</th>
                                <th>UserID/Email</th>
                                <th>Tổng Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Ngày Đặt</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($don_hang_list as $dh): ?>
                            <?php
                                // Đồng bộ trạng thái cho tab
                                $rowStatus = $dh['trang_thai'];
                                if (trim($rowStatus) === 'Chờ xác nhận') $rowStatus = 'Chờ xử lý';
                            ?>
                            <tr class="order-row" data-status="<?= htmlspecialchars($rowStatus) ?>">
                                <td><input type="checkbox" class="item-checkbox order-checkbox" data-id="<?= $dh['id'] ?>"></td>
                                <td>#<?= $dh['id'] ?></td>
                                <td>
                                    <?php 
                                    // Ưu tiên: Tên người nhận trên đơn hàng > Họ tên từ DB > Username > N/A
                                    $displayName = '';
                                    if (!empty($dh['ten_nguoi_nhan']) && $dh['ten_nguoi_nhan'] != 'N/A') {
                                        $displayName = $dh['ten_nguoi_nhan'];
                                    } elseif (!empty($dh['hoten_kh'])) {
                                        $displayName = $dh['hoten_kh'];
                                    } elseif (!empty($dh['tendangnhap_kh'])) {
                                        $displayName = $dh['tendangnhap_kh'];
                                    } else {
                                        $displayName = 'Khách';
                                    }
                                    echo '<strong>' . htmlspecialchars($displayName) . '</strong>';
                                    if (!empty($dh['tendangnhap_kh']) && $dh['tendangnhap_kh'] != $displayName) {
                                        echo '<br><small style="color:#999;">@' . htmlspecialchars($dh['tendangnhap_kh']) . '</small>';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($dh['debug_user']) ?></td>
                                <td>
                                    <?php 
                                    $total_display = isset($dh['tong_thanh_toan']) ? $dh['tong_thanh_toan'] : $dh['tong_tien'];
                                    echo number_format($total_display, 0, ',', '.') . '₫';
                                    if (!empty($dh['ma_voucher']) && !empty($dh['giam_gia']) && $dh['giam_gia'] > 0) {
                                        echo '<br><small style="color:#28a745;"><i class="fas fa-tag"></i> -' . number_format($dh['giam_gia'], 0, ',', '.') . '₫</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <select class="status-select order-status-select" data-id="<?= $dh['id'] ?>" style="padding:6px 10px; border-radius:6px; border:1px solid #ddd; font-size:13px; font-weight:600;" <?= $dh['trang_thai'] == 'Đã giao' ? 'disabled' : '' ?>>
                                        <option value="Chờ xử lý" <?= $dh['trang_thai'] == 'Chờ xử lý' ? 'selected' : '' ?>>Chờ xử lý</option>
                                        <option value="Đang xử lý" <?= $dh['trang_thai'] == 'Đang xử lý' ? 'selected' : '' ?>>Đang xử lý</option>
                                        <option value="Đang giao" <?= $dh['trang_thai'] == 'Đang giao' ? 'selected' : '' ?>>Đang giao</option>
                                        <option value="Đã giao" <?= $dh['trang_thai'] == 'Đã giao' ? 'selected' : '' ?>>Đã giao</option>
                                        <option value="Đã hủy" <?= $dh['trang_thai'] == 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                                    </select>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($dh['ngay_dat'])) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewOrder(<?= $dh['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-delete-order" data-id="<?= $dh['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USERS SECTION -->
        <div id="users-section" class="content-section" style="display:none;">
            <div class="section">
                <h2><i class="fas fa-users"></i> Quản Lý Người Dùng</h2>
                
                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="selectAllUsers">
                        Chọn tất cả
                    </label>
                    <button class="btn btn-danger" id="deleteSelectedUsers" style="display:none;">
                        <i class="fas fa-trash"></i> Xóa đã chọn (<span id="selectedUsersCount">0</span>)
                    </button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="selectAllCheckbox" data-target="user"></th>
                                <th>ID</th>
                                <th>Tên Đăng Nhập</th>
                                <th>Họ Tên</th>
                                <th>Email</th>
                                <th>Quyền</th>
                                <th>Trạng Thái</th>
                                <th>Ngày Tạo</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nguoi_dung_list as $nd): ?>
                            <tr>
                                <td><input type="checkbox" class="item-checkbox user-checkbox" data-id="<?= $nd['id'] ?>"></td>
                                <td><?= $nd['id'] ?></td>
                                <td><?= htmlspecialchars($nd['tendangnhap']) ?></td>
                                <td><?= htmlspecialchars($nd['hoten']) ?></td>
                                <td><?= htmlspecialchars($nd['email']) ?></td>
                                <td>
                                    <select class="status-select user-role-select" data-id="<?= $nd['id'] ?>" style="padding:6px 10px; border-radius:6px; border:1px solid #ddd; font-size:13px; font-weight:600;">
                                        <option value="user" <?= $nd['quyen'] == 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $nd['quyen'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="status-select user-status-select" data-id="<?= $nd['id'] ?>" style="padding:6px 10px; border-radius:6px; border:1px solid #ddd; font-size:13px; font-weight:600;">
                                        <option value="0" <?= $nd['khoa'] == 0 ? 'selected' : '' ?>>Hoạt động</option>
                                        <option value="1" <?= $nd['khoa'] == 1 ? 'selected' : '' ?>>Đã khóa</option>
                                    </select>
                                </td>
                                <td><?= date('d/m/Y', strtotime($nd['ngay_tao'])) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewUserHistory(<?= $nd['id'] ?>)" title="Lịch sử mua hàng">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-delete-user" data-id="<?= $nd['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- REVIEWS SECTION -->
        <div id="reviews-section" class="content-section" style="display:none;">
            <div class="section">
                <h2><i class="fas fa-star"></i> Quản Lý Đánh Giá</h2>
                
                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="selectAllReviews">
                        Chọn tất cả
                    </label>
                    <button class="btn btn-danger" id="deleteSelectedReviews" style="display:none;">
                        <i class="fas fa-trash"></i> Xóa đã chọn (<span id="selectedReviewsCount">0</span>)
                    </button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="selectAllCheckbox" data-target="review"></th>
                                <th>ID</th>
                                <th>Sản Phẩm</th>
                                <th>Người Đánh Giá</th>
                                <th>Đánh Giá</th>
                                <th>Bình Luận</th>
                                <th>Ngày</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($danh_gia_list as $dg): ?>
                            <tr>
                                <td><input type="checkbox" class="item-checkbox review-checkbox" data-id="<?= $dg['id'] ?>"></td>
                                <td><?= $dg['id'] ?></td>
                                <td><?= htmlspecialchars($dg['ten_san_pham']) ?></td>
                                <td><?= htmlspecialchars($dg['user_name']) ?></td>
                                <td>
                                    <?php for($i=0; $i<$dg['rating']; $i++): ?>
                                        <i class="fas fa-star" style="color: #FFD166;"></i>
                                    <?php endfor; ?>
                                </td>
                                <td>
                                    <div><?= substr(htmlspecialchars($dg['comment']), 0, 50) ?>...</div>
                                    <?php if (!empty($dg['admin_reply'])): ?>
                                        <div style="margin-top: 8px; padding: 8px; background: #e8f5e9; border-left: 3px solid #4caf50; border-radius: 4px; font-size: 13px;">
                                            <strong style="color: #2e7d32;">↳ Admin:</strong> <?= htmlspecialchars($dg['admin_reply']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($dg['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="replyReview(<?= $dg['id'] ?>, '<?= htmlspecialchars($dg['admin_reply'] ?? '', ENT_QUOTES) ?>')" title="Trả lời">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteReview(<?= $dg['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CHAT SECTION -->
        <div id="chat-section" class="content-section" style="display:none;">
            <div class="section">
                <h2><i class="fas fa-comments"></i> Chat Khách Hàng</h2>
                <p style="color: #666;">Tích hợp với trang chat hiện có...</p>
                <a href="admin_chat.php" class="btn btn-primary">
                    <i class="fas fa-external-link-alt"></i> Mở Trang Chat
                </a>
            </div>
        </div>

        <!-- WAREHOUSE SECTION -->
        <div id="warehouse-section" class="content-section" style="display:none;">
                        <?php
                        // Tổng số sản phẩm
                        $kho_total_products = count($san_pham_list);
                        // Tổng số lượng tồn
                        $kho_total_stock = 0;
                        $kho_sap_het = 0;
                        foreach ($san_pham_list as $sp) {
                            $kho_total_stock += (int)$sp['so_luong'];
                            if ($sp['so_luong'] < 10) $kho_sap_het++;
                        }
                        ?>
                        <div style="display:flex;gap:24px;margin-bottom:24px;flex-wrap:wrap;">
                            <div style="background:#fff;border-radius:12px;padding:22px 36px;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-left:5px solid #5A9FA3;min-width:220px;flex:1;display:flex;align-items:center;">
                                <div style="font-size:2.2rem;font-weight:700;color:#43a047;min-width:60px;"> <?= $kho_total_products ?> </div>
                                <div style="margin-left:18px;">
                                    <div style="font-size:1.1rem;color:#666;font-weight:600;">Tổng Sản Phẩm</div>
                                </div>
                                <div style="margin-left:auto;font-size:2.2rem;color:#b2dfdb;"><i class="fas fa-box"></i></div>
                            </div>
                            <div style="background:#fff;border-radius:12px;padding:22px 36px;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-left:5px solid #388e3c;min-width:220px;flex:1;display:flex;align-items:center;">
                                <div style="font-size:2.2rem;font-weight:700;color:#388e3c;min-width:60px;"> <?= $kho_total_stock ?> </div>
                                <div style="margin-left:18px;">
                                    <div style="font-size:1.1rem;color:#666;font-weight:600;">Tổng Số Lượng Tồn</div>
                                </div>
                                <div style="margin-left:auto;font-size:2.2rem;color:#b2dfdb;"><i class="fas fa-cubes"></i></div>
                            </div>
                            <div style="background:#fff;border-radius:12px;padding:22px 36px;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-left:5px solid #e53935;min-width:220px;flex:1;display:flex;align-items:center;">
                                <div style="font-size:2.2rem;font-weight:700;color:#e53935;min-width:60px;"> <?= $kho_sap_het ?> </div>
                                <div style="margin-left:18px;">
                                    <div style="font-size:1.1rem;color:#666;font-weight:600;">Sắp Hết Hàng</div>
                                </div>
                                <div style="margin-left:auto;font-size:2.2rem;color:#ffcdd2;"><i class="fas fa-exclamation-triangle"></i></div>
                            </div>
                        </div>
            <div class="section">
                <h2><i class="fas fa-warehouse"></i> Quản Lý Kho Hàng</h2>
                <p style="color: #666;">Quản lý tồn kho </p>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Hình</th>
                                <th>Mã SP</th>
                                <th>Tên Sản Phẩm</th>
                                <th>Tồn Kho</th>
                                <th>Số lượng cập nhật</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($san_pham_list as $sp): ?>
                            <tr>
                                <td><img src="uploads/<?= htmlspecialchars($sp['hinh_anh']) ?>" class="product-img" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" alt=""></td>
                                <td><?= htmlspecialchars($sp['ma_san_pham']) ?></td>
                                <td><?= htmlspecialchars($sp['ten_san_pham']) ?></td>
                                <td><?= $sp['so_luong'] ?></td>
                                <td class="updated-qty" data-id="<?= $sp['id'] ?>">—</td>
                                <td>
                                    <?php if ($sp['so_luong'] < 10): ?>
                                        <span class="badge badge-danger">Sắp hết</span>
                                    <?php elseif ($sp['so_luong'] < 50): ?>
                                        <span class="badge badge-warning">Tồn thấp</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Đủ hàng</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-sm btn-update-stock" data-id="<?= $sp['id'] ?>" data-stock="<?= $sp['so_luong'] ?>" data-name="<?= htmlspecialchars($sp['ten_san_pham']) ?>" data-image="uploads/<?= htmlspecialchars($sp['hinh_anh']) ?>">
                                        <i class="fas fa-edit"></i> Cập nhật
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
            });
            </script>
        </div>
        


        <!-- PROMOTIONS SECTION - KHUYẾN MÃI -->
        <div id="promotions-section" class="content-section" style="display:none;">
            <div class="section">
                <h2><i class="fas fa-percent"></i> Quản Lý Khuyến Mãi</h2>
                
                <!-- Tabs -->
                <div class="promo-tabs" style="display:flex;gap:10px;margin:20px 0;">
                    <button class="promo-tab active" data-tab="category" onclick="switchPromoTab('category')">
                        <i class="fas fa-tags"></i> Giảm theo danh mục
                    </button>
                </div>

                <!-- Tab 1: Giảm theo danh mục -->
                <div id="promo-category" class="promo-content">
                    <h3 style="margin-bottom:15px;color:#5A9FA3;">Áp dụng giảm giá theo danh mục</h3>
                    <p style="color:#666;margin-bottom:20px;">Chọn danh mục và nhập % giảm giá để áp dụng cho tất cả sản phẩm trong danh mục đó.</p>
                    
                    <div style="display:grid;gap:15px;">
                        <?php foreach ($danh_muc_list as $cat): ?>
                        <div style="background:#f8f9fa;padding:15px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <strong><?php echo htmlspecialchars($cat['ten_danh_muc']); ?></strong>
                                <p style="color:#666;font-size:13px;margin:0;"><?php echo htmlspecialchars($cat['mo_ta'] ?? ''); ?></p>
                            </div>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <input type="number" id="cat-discount-<?php echo $cat['id']; ?>" 
                                       placeholder="%" min="0" max="100" style="width:80px;padding:8px;border:1px solid #ddd;border-radius:4px;">
                                <select id="cat-type-<?php echo $cat['id']; ?>" style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                                    <option value="percent">%</option>
                                    <option value="fixed">VNĐ</option>
                                </select>
                                <button class="btn btn-primary btn-sm" onclick="applyCategoryDiscount(<?php echo $cat['id']; ?>)">
                                    <i class="fas fa-check"></i> Áp dụng
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="removeCategoryDiscount(<?php echo $cat['id']; ?>)">
                                    <i class="fas fa-times"></i> Xóa
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab 2: Sale toàn cửa hàng -->
                <div id="promo-store" class="promo-content" style="display:none;">
                    <h3 style="margin-bottom:15px;color:#5A9FA3;">Sale toàn cửa hàng</h3>
                    <p style="color:#666;margin-bottom:20px;">Áp dụng giảm giá cho TẤT CẢ sản phẩm trong cửa hàng.</p>
                    
                    <div id="currentSaleBanner" style="display:none;background:linear-gradient(135deg,#EF476F,#ff6b6b);color:white;padding:20px;border-radius:12px;margin-bottom:20px;">
                        <h4><i class="fas fa-fire"></i> Đang có chương trình Sale!</h4>
                        <p id="currentSaleInfo"></p>
                        <button class="btn" style="background:white;color:#EF476F;margin-top:10px;" onclick="endStoreSale()">
                            <i class="fas fa-stop"></i> Kết thúc Sale
                        </button>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;max-width:600px;">
                        <div class="form-group">
                            <label>Tên chương trình</label>
                            <input type="text" id="saleName" placeholder="VD: Flash Sale Cuối Năm" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div class="form-group">
                            <label>Giá trị giảm</label>
                            <div style="display:flex;gap:5px;">
                                <input type="number" id="saleValue" placeholder="10" min="1" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:4px;">
                                <select id="saleType" style="width:100px;padding:10px;border:1px solid #ddd;border-radius:4px;">
                                    <option value="phan_tram">%</option>
                                    <option value="co_dinh">VNĐ</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ngày bắt đầu</label>
                            <input type="date" id="saleStart" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div class="form-group">
                            <label>Ngày kết thúc</label>
                            <input type="date" id="saleEnd" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="startStoreSale()" style="margin-top:15px;">
                        <i class="fas fa-rocket"></i> Bắt đầu Sale toàn cửa hàng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Sản Phẩm -->
    <div id="modalProduct" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeProductModal()">&times;</span>
            <h3 class="modal-title" id="productModalTitle">Thêm Sản Phẩm</h3>
            <form id="formProduct" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="id" id="product_id">
                
                <div class="form-group">
                    <label>Mã Sản Phẩm *</label>
                    <input type="text" name="ma_san_pham" id="ma_san_pham" required>
                </div>
                
                <div class="form-group">
                    <label>Tên Sản Phẩm *</label>
                    <input type="text" name="ten_san_pham" id="ten_san_pham" required>
                </div>
                
                <div class="form-group">
                    <label>Danh Mục *</label>
                    <select name="danh_muc_id" id="danh_muc_id" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($danh_muc_list as $dm): ?>
                        <option value="<?= $dm['id'] ?>"><?= htmlspecialchars($dm['ten_danh_muc']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Giá *</label>
                    <input type="number" name="gia" id="gia" required>
                </div>
                
                <div class="form-group">
                    <label>Giá Giảm (để trống nếu không giảm giá)</label>
                    <input type="number" name="gia_giam" id="gia_giam" placeholder="Nhập giá sau khi giảm">
                    <small style="color:#666; font-size:12px;">Giá giảm phải nhỏ hơn giá gốc</small>
                </div>
                
                <div class="form-group">
                    <label>Số Lượng *</label>
                    <input type="number" name="so_luong" id="so_luong" required>
                </div>
                
                <div class="form-group">
                    <label>Mô Tả</label>
                    <textarea name="mo_ta" id="mo_ta"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Hình Ảnh</label>
                    <input type="file" name="hinh_anh" id="hinh_anh" accept="image/*">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Lưu
                </button>
                <button type="button" class="btn btn-danger" onclick="closeProductModal()">
                    <i class="fas fa-times"></i> Hủy
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Danh Mục -->
    <div id="modalCategory" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeCategoryModal()">&times;</span>
            <h3 class="modal-title" id="categoryModalTitle">Thêm Danh Mục</h3>
            <form id="formCategory">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" id="category_id">
                
                <div class="form-group">
                    <label>Tên Danh Mục *</label>
                    <input type="text" name="ten_danh_muc" id="ten_danh_muc" required>
                </div>
                
                <div class="form-group">
                    <label>Mô Tả</label>
                    <textarea name="mo_ta" id="category_mo_ta"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Lưu
                </button>
                <button type="button" class="btn btn-danger" onclick="closeCategoryModal()">
                    <i class="fas fa-times"></i> Hủy
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Chi Tiết Đơn Hàng -->
    <div id="modalOrderDetail" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close-modal" onclick="closeOrderDetailModal()">&times;</span>
            <h3 class="modal-title">
                <i class="fas fa-receipt"></i> Chi Tiết Đơn Hàng <span id="orderIdDisplay"></span>
            </h3>
            
            <div id="orderDetailContent" style="max-height: 500px; overflow-y: auto;">
                <!-- Nội dung sẽ được load bằng AJAX -->
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #5A9FA3;"></i>
                    <p style="margin-top: 15px; color: #666;">Đang tải...</p>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #f0f2f5; text-align: right;">
                <button type="button" class="btn btn-danger" onclick="closeOrderDetailModal()">
                    <i class="fas fa-times"></i> Đóng
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Trả Lời Đánh Giá -->
    <div id="modalReplyReview" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close-modal" onclick="closeReplyModal()">&times;</span>
            <h3 class="modal-title">
                <i class="fas fa-reply"></i> Trả Lời Đánh Giá
            </h3>
            
            <form id="formReplyReview" style="padding: 20px 0;">
                <input type="hidden" id="review_id_reply">
                
                <div class="form-group">
                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">
                        Nội dung trả lời *
                    </label>
                    <textarea 
                        id="admin_reply_text" 
                        rows="5" 
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical;"
                        placeholder="Nhập câu trả lời của bạn cho khách hàng..."
                        required
                    ></textarea>
                    <small style="color: #666; font-size: 12px;">Câu trả lời sẽ hiển thị công khai bên dưới đánh giá của khách hàng</small>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-danger" onclick="closeReplyModal()">
                        <i class="fas fa-times"></i> Hủy
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Gửi Trả Lời
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Lịch Sử Mua Hàng -->
    <div id="modalUserHistory" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="close-modal" onclick="closeUserHistoryModal()">&times;</span>
            <h3 class="modal-title">
                <i class="fas fa-history"></i> Lịch Sử Mua Hàng - <span id="userHistoryName"></span>
            </h3>
            
            <div id="userHistoryContent" style="max-height: 600px; overflow-y: auto;">
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #5A9FA3;"></i>
                    <p style="margin-top: 15px; color: #666;">Đang tải...</p>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #f0f2f5;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="color: #666; font-size: 14px;">
                        <strong>Tổng đơn hàng:</strong> <span id="totalOrders">0</span> | 
                        <strong>Tổng chi tiêu:</strong> <span id="totalSpent" style="color: #EF476F; font-weight: 700;">0₫</span>
                    </div>
                    <button type="button" class="btn btn-danger" onclick="closeUserHistoryModal()">
                        <i class="fas fa-times"></i> Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Voucher -->
    <div id="modalVoucher" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <span class="close-modal" onclick="closeVoucherModal()">&times;</span>
            <h3 class="modal-title" id="voucherModalTitle">Thêm Voucher</h3>
            <form id="formVoucher">
                <input type="hidden" name="action" value="save_voucher">
                <input type="hidden" name="id" id="voucher_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Mã Voucher <span style="color:red;">*</span></label>
                        <input type="text" name="ma_voucher" id="voucher_ma" required class="form-control" placeholder="VD: SALE50K">
                    </div>
                    <div class="form-group">
                        <label>Tên Voucher <span style="color:red;">*</span></label>
                        <input type="text" name="ten_voucher" id="voucher_ten" required class="form-control" placeholder="VD: Giảm 50,000đ">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mô Tả</label>
                    <textarea name="mo_ta" id="voucher_mota" rows="2" class="form-control" placeholder="Mô tả chi tiết voucher..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Loại Giảm <span style="color:red;">*</span></label>
                        <select name="loai_giam" id="voucher_loai" required class="form-control" onchange="toggleVoucherFields()">
                            <option value="phan_tram">Phần trăm (%)</option>
                            <option value="tien_mat">Tiền mặt (₫)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Giá Trị Giảm <span style="color:red;">*</span></label>
                        <input type="number" name="gia_tri_giam" id="voucher_giatri" required class="form-control" min="0" step="0.01">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" id="giamToiDaGroup">
                        <label>Giảm Tối Đa (₫)</label>
                        <input type="number" name="giam_toi_da" id="voucher_giamtoida" class="form-control" min="0" placeholder="Để trống nếu không giới hạn">
                    </div>
                    <div class="form-group">
                        <label>Giá Trị Đơn Tối Thiểu (₫)</label>
                        <input type="number" name="gia_tri_don_toi_thieu" id="voucher_dontoithieu" class="form-control" min="0" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Số Lượng <span style="color:red;">*</span></label>
                        <input type="number" name="so_luong" id="voucher_soluong" required class="form-control" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label>Trạng Thái</label>
                        <select name="trang_thai" id="voucher_trangthai" class="form-control">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Tạm dừng</option>
                        </select>
                    </div                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Ngày Bắt Đầu <span style="color:red;">*</span></label>
                        <input type="datetime-local" name="ngay_bat_dau" id="voucher_batdau" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Ngày Kết Thúc <span style="color:red;">*</span></label>
                        <input type="datetime-local" name="ngay_ket_thuc" id="voucher_ketthuc" required class="form-control">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeVoucherModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 3000
        };

        // NAVIGATION
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
            
            // Show selected section
            document.getElementById(section + '-section').style.display = 'block';
            
            // Update active menu
            document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
            if (event && event.target) {
                event.target.closest('a').classList.add('active');
            } else {
                document.querySelector('a[href="#' + section + '"]').classList.add('active');
            }
            
            // Update page title
            const titles = {
                'dashboard': 'Thống kê',
                'products': 'Quản Lý Sản Phẩm',
                'categories': 'Quản Lý Danh Mục',
                'orders': 'Quản Lý Đơn Hàng',
                'users': 'Quản Lý Người Dùng',
                'reviews': 'Quản Lý Đánh Giá',
                'chat': 'Chat Khách Hàng',
                'warehouse': 'Quản Lý Kho Hàng'
            };
            document.getElementById('pageTitle').textContent = titles[section] || 'Khuyến mãi';
            
            // Lưu section hiện tại vào localStorage
            localStorage.setItem('currentAdminSection', section);
        }

        // Helper function để reload với section hiện tại
        function reloadCurrentSection() {
            const currentSection = localStorage.getItem('currentAdminSection') || 'dashboard';
            location.reload();
        }

        // === SẢN PHẨM ===
        
        function openAddProductModal() {
            $('#productModalTitle').text('Thêm Sản Phẩm');
            $('#formProduct')[0].reset();
            $('#product_id').val('');
            $('#modalProduct').fadeIn(200);
        }

        function closeProductModal() {
            $('#modalProduct').fadeOut(200);
        }

        // Sửa sản phẩm
        $(document).on('click', '.btn-edit-product', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            console.log('Edit product clicked, ID:', id);
            
            $.post('admin_ajax.php', { action: 'get_product', id: id }, function(res) {
                console.log('Response:', res);
                if (res.success) {
                    $('#product_id').val(res.product.id);
                    $('#ma_san_pham').val(res.product.ma_san_pham);
                    $('#ten_san_pham').val(res.product.ten_san_pham);
                    $('#danh_muc_id').val(res.product.danh_muc_id);
                    $('#gia').val(res.product.gia);
                    $('#gia_giam').val(res.product.gia_giam || '');
                    $('#so_luong').val(res.product.so_luong);
                    $('#mo_ta').val(res.product.mo_ta);
                    $('#productModalTitle').text('Sửa Sản Phẩm');
                    $('#modalProduct').fadeIn(200);
                } else {
                    toastr.error(res.message || 'Không tải được sản phẩm');
                }
            }, 'json').fail(function(xhr) {
                console.error('AJAX Error:', xhr.responseText);
                toastr.error('Lỗi kết nối: ' + xhr.status);
            });
        });

        // Toggle khóa/mở khóa sản phẩm
        $(document).on('click', '.btn-toggle-product', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const id = $btn.data('id');
            const currentStatus = $btn.data('status');
            const actionText = currentStatus == 1 ? 'khóa' : 'mở khóa';
            
            if (!confirm(`Bạn có chắc muốn ${actionText} sản phẩm này?`)) return;
            
            $.ajax({
                url: 'xu_ly_san_pham.php',
                type: 'POST',
                data: { 
                    action: 'toggle_trang_thai', 
                    san_pham_id: id 
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(() => reloadCurrentSection(), 500);
                    } else {
                        toastr.error(res.message || 'Thao tác thất bại');
                    }
                },
                error: function(xhr) {
                    console.error('Toggle error:', xhr.responseText);
                    toastr.error('Lỗi kết nối');
                }
            });
        });

        // Xóa sản phẩm
        $(document).on('click', '.btn-delete-product', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!confirm('Xóa sản phẩm này?')) return;
            
            const id = $(this).data('id');
            console.log('Delete product, ID:', id);
            
            $.post('admin_ajax.php', { action: 'delete_product', id: id }, function(res) {
                if (res.success) {
                    toastr.success('Đã xóa sản phẩm');
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Xóa thất bại');
                }
            }, 'json');
        });

        // Submit form sản phẩm
        $('#formProduct').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            $.ajax({
                url: 'admin_ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Lưu thành công');
                        closeProductModal();
                        setTimeout(() => reloadCurrentSection(), 500);
                    } else {
                        toastr.error(res.message || 'Lưu thất bại');
                    }
                },
                error: function(xhr) {
                    console.error('Error:', xhr.responseText);
                    toastr.error('Lỗi: ' + xhr.status);
                }
            });
        });

        // === DANH MỤC ===
        
        function openAddCategoryModal() {
            $('#categoryModalTitle').text('Thêm Danh Mục');
            $('#formCategory')[0].reset();
            $('#category_id').val('');
            $('#modalCategory').fadeIn(200);
        }

        function closeCategoryModal() {
            $('#modalCategory').fadeOut(200);
        }

        // Sửa danh mục
        $(document).on('click', '.btn-edit-category', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            
            $.post('admin_ajax.php', { action: 'get_category', id: id }, function(res) {
                if (res.success) {
                    $('#category_id').val(res.category.id);
                    $('#ten_danh_muc').val(res.category.ten_danh_muc);
                    $('#category_mo_ta').val(res.category.mo_ta);
                    $('#categoryModalTitle').text('Sửa Danh Mục');
                    $('#modalCategory').fadeIn(200);
                } else {
                    toastr.error(res.message || 'Không tải được danh mục');
                }
            }, 'json');
        });

        // Xóa danh mục
        $(document).on('click', '.btn-delete-category', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!confirm('Xóa danh mục này?')) return;
            
            const id = $(this).data('id');
            $.post('admin_ajax.php', { action: 'delete_category', id: id }, function(res) {
                if (res.success) {
                    toastr.success('Đã xóa danh mục');
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Xóa thất bại');
                }
            }, 'json');
        });

        // Submit form danh mục
        $('#formCategory').on('submit', function(e) {
            e.preventDefault();
            
            $.post('admin_ajax.php', $(this).serialize(), function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Lưu thành công');
                    closeCategoryModal();
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Lưu thất bại');
                }
            }, 'json');
        });

        // === ĐƠN HÀNG ===
        
        // === ĐƠN HÀNG - PHÂN TAB TRẠNG THÁI ===
        $(document).on('click', '.order-tab-btn', function() {
            var status = $(this).data('status');
            // Đổi màu active
            $('.order-tab-btn').removeClass('active');
            $(this).addClass('active');
            // Hiện/ẩn các dòng đơn hàng
            if (!status) {
                // Tất cả
                $('#orders-section .order-row').show();
            } else {
                $('#orders-section .order-row').each(function() {
                    var rowStatus = $(this).data('status') || '';
                    if (rowStatus === status) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });
        // Khi vào section orders, mặc định chọn tab "Tất cả"
        function activateDefaultOrderTab() {
            var $defaultTab = $('#orderTabs .order-tab-btn[data-status=""]');
            if ($defaultTab.length) $defaultTab.click();
        }
        // Gọi khi chuyển sang section orders
        const origReloadCurrentSection = reloadCurrentSection;
        reloadCurrentSection = function() {
            const currentSection = localStorage.getItem('currentAdminSection') || 'dashboard';
            if (currentSection === 'orders') {
                setTimeout(activateDefaultOrderTab, 100);
            }
            origReloadCurrentSection();
        };
        // Khi load trang nếu đang ở orders thì cũng gọi luôn
        $(document).ready(function() {
            const lastSection = localStorage.getItem('currentAdminSection');
            if (lastSection === 'orders') {
                setTimeout(activateDefaultOrderTab, 100);
            }
        });
        function viewOrder(id) {
            $('#modalOrderDetail').fadeIn(200);
            $('#orderIdDisplay').text('#' + id);
            
            // Load order details via AJAX
            $.post('admin_ajax.php', { action: 'get_order_detail', id: id }, function(res) {
                if (res.success) {
                    const order = res.data;
                    const items = res.items || [];
                    
                    let itemsHtml = '';
                    let totalAmount = 0;
                    
                    items.forEach(function(item) {
                        const itemTotal = item.gia * item.so_luong;
                        totalAmount += itemTotal;
                        itemsHtml += `
                            <tr>
                                <td>${item.ten_san_pham}</td>
                                <td style="text-align: center;">${item.so_luong}</td>
                                <td style="text-align: right;">${Number(item.gia).toLocaleString('vi-VN')}₫</td>
                                <td style="text-align: right; font-weight: 600;">${itemTotal.toLocaleString('vi-VN')}₫</td>
                            </tr>
                        `;
                    });
                    
                    const html = `
                        <div style="padding: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                                <div>
                                    <h4 style="color: #5A9FA3; margin-bottom: 15px; font-size: 16px;">
                                        <i class="fas fa-user"></i> Thông Tin Khách Hàng
                                    </h4>
                                    <p style="margin: 8px 0;"><strong>Họ tên:</strong> ${order.ten_nguoi_nhan || 'N/A'}</p>
                                    <p style="margin: 8px 0;"><strong>Email:</strong> ${order.email || 'N/A'}</p>
                                    <p style="margin: 8px 0;"><strong>Số điện thoại:</strong> ${order.so_dien_thoai || 'N/A'}</p>
                                </div>
                                <div>
                                    <h4 style="color: #5A9FA3; margin-bottom: 15px; font-size: 16px;">
                                        <i class="fas fa-shipping-fast"></i> Thông Tin Giao Hàng
                                    </h4>
                                    <p style="margin: 8px 0;"><strong>Địa chỉ:</strong> ${order.dia_chi || 'N/A'}</p>
                                    <p style="margin: 8px 0;"><strong>Ngày đặt:</strong> ${new Date(order.ngay_dat).toLocaleString('vi-VN')}</p>
                                    <p style="margin: 8px 0;"><strong>Trạng thái:</strong> 
                                        <span style="padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; 
                                            background: ${order.trang_thai === 'Đã giao' ? '#d4f4dd' : order.trang_thai === 'Đã hủy' ? '#f8d7da' : '#fff3cd'};
                                            color: ${order.trang_thai === 'Đã giao' ? '#0e6027' : order.trang_thai === 'Đã hủy' ? '#721c24' : '#856404'};">
                                            ${order.trang_thai}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <h4 style="color: #5A9FA3; margin-bottom: 15px; font-size: 16px;">
                                <i class="fas fa-shopping-cart"></i> Sản Phẩm Đã Mua
                            </h4>
                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Sản phẩm</th>
                                        <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">SL</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;">Đơn giá</th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" style="padding: 10px; text-align: right;">Tạm tính:</td>
                                        <td style="padding: 10px; text-align: right;">${Number(order.tong_tien || 0).toLocaleString('vi-VN')}₫</td>
                                    </tr>
                                    ${order.phi_van_chuyen > 0 ? `
                                    <tr>
                                        <td colspan="3" style="padding: 10px; text-align: right;">Phí vận chuyển:</td>
                                        <td style="padding: 10px; text-align: right;">${Number(order.phi_van_chuyen).toLocaleString('vi-VN')}₫</td>
                                    </tr>
                                    ` : ''}
                                    ${order.ma_voucher && order.giam_gia > 0 ? `
                                    <tr style="color: #28a745;">
                                        <td colspan="3" style="padding: 10px; text-align: right;">
                                            <i class="fas fa-tag"></i> Giảm giá (${order.ma_voucher}):
                                        </td>
                                        <td style="padding: 10px; text-align: right; font-weight: 600;">-${Number(order.giam_gia).toLocaleString('vi-VN')}₫</td>
                                    </tr>
                                    ` : ''}
                                    <tr style="background: #f8f9fa; font-weight: 700; font-size: 16px;">
                                        <td colspan="3" style="padding: 15px; text-align: right; border-top: 2px solid #5A9FA3;">Tổng thanh toán:</td>
                                        <td style="padding: 15px; text-align: right; color: #EF476F; border-top: 2px solid #5A9FA3;">
                                            ${Number(order.tong_thanh_toan || order.tong_tien || 0).toLocaleString('vi-VN')}₫
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            ${order.ghi_chu ? `
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                                    <strong style="color: #555;">Ghi chú:</strong>
                                    <p style="margin: 8px 0 0 0; color: #666;">${order.ghi_chu}</p>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    $('#orderDetailContent').html(html);
                } else {
                    $('#orderDetailContent').html(`
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #EF476F; margin-bottom: 15px;"></i>
                            <p style="color: #666;">${res.message || 'Không thể tải thông tin đơn hàng'}</p>
                        </div>
                    `);
                }
            }, 'json').fail(function() {
                $('#orderDetailContent').html(`
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #FFD166; margin-bottom: 15px;"></i>
                        <p style="color: #666;">Lỗi kết nối server</p>
                    </div>
                `);
            });
        }
        
        function closeOrderDetailModal() {
            $('#modalOrderDetail').fadeOut(200);
        }

        // Thay đổi trạng thái đơn hàng trực tiếp
        $(document).on('change', '.order-status-select', function() {
            const id = $(this).data('id');
            const newStatus = $(this).val();
            const $select = $(this);
            $.post('admin_ajax.php', { action: 'update_order_status', id: id, status: newStatus }, function(res) {
                if (res && res.success) {
                    toastr.success('Đã cập nhật trạng thái đơn hàng');
                    $select.css({
                        'background': newStatus === 'Đã giao' ? '#d1fae5' : newStatus === 'Đã hủy' ? '#f8d7da' : newStatus === 'Đang giao' ? '#ffe066' : '#fff3cd',
                        'color': newStatus === 'Đã giao' ? '#065f46' : newStatus === 'Đã hủy' ? '#721c24' : newStatus === 'Đang giao' ? '#b68900' : '#856404'
                    });
                    if (newStatus === 'Đã giao') {
                        $select.prop('disabled', true);
                    }
                } else if (res && res.message) {
                    toastr.error(res.message);
                } else {
                    toastr.error('Cập nhật thất bại');
                }
            }, 'json').fail(function(xhr) {
                // Nếu status là 200 (OK), không hiện lỗi gì cả (có thể do response rỗng hoặc lỗi JSON nhỏ)
                if (xhr.status && xhr.status !== 200) {
                    toastr.error('Lỗi kết nối server: ' + xhr.status);
                }
                // Nếu status là 200 thì im lặng, không hiện lỗi gây khó chịu
            });
        });

        // Xóa đơn hàng
        $(document).on('click', '.btn-delete-order', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!confirm('Xóa đơn hàng này? Hành động không thể hoàn tác!')) return;
            
            const id = $(this).data('id');
            $.post('admin_ajax.php', { action: 'delete_order', id: id }, function(res) {
                if (res.success) {
                    toastr.success('Đã xóa đơn hàng');
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Xóa thất bại');
                }
            }, 'json');
        });

        // === NGƯỜI DÙNG ===
        
        // Đổi quyền trực tiếp
        $(document).on('change', '.user-role-select', function() {
            const id = $(this).data('id');
            const newRole = $(this).val();
            const $select = $(this);
            $.post('admin_ajax.php', { action: 'update_user_role', id: id, role: newRole }, function(res) {
                if (res.success) {
                    toastr.success('Đã cập nhật quyền');
                    $select.css({
                        'background': newRole === 'admin' ? '#f8d7da' : '#d4f4dd',
                        'color': newRole === 'admin' ? '#721c24' : '#0e6027'
                    });
                } else {
                    toastr.error(res.message || 'Cập nhật thất bại');
                }
            }, 'json').fail(function() {
                toastr.error('Lỗi kết nối server');
            });
        });

        // Đổi trạng thái trực tiếp
        $(document).on('change', '.user-status-select', function() {
            const id = $(this).data('id');
            const newStatus = $(this).val();
            const $select = $(this);
            $.post('admin_ajax.php', { action: 'update_user_status', id: id, status: newStatus }, function(res) {
                if (res.success) {
                    toastr.success('Đã cập nhật trạng thái');
                    $select.css({
                        'background': newStatus == 1 ? '#f8d7da' : '#d4f4dd',
                        'color': newStatus == 1 ? '#721c24' : '#0e6027'
                    });
                } else {
                    toastr.error(res.message || 'Cập nhật trạng thái thất bại');
                }
            }, 'json').fail(function() {
                toastr.error('Lỗi kết nối server');
            });
        });

        // Xóa người dùng
        $(document).on('click', '.btn-delete-user', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!confirm('Xóa người dùng này? Hành động không thể hoàn tác!')) return;
            
            const id = $(this).data('id');
            $.post('admin_ajax.php', { action: 'delete_user', id: id }, function(res) {
                if (res.success) {
                    toastr.success('Đã xóa người dùng');
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Xóa thất bại');
                }
            }, 'json');
        });
        
        // Xem lịch sử mua hàng
        function viewUserHistory(userId) {
            $('#modalUserHistory').fadeIn(200);
            $('#userHistoryName').text('');
            
            $.post('admin_ajax.php', { action: 'get_user_history', user_id: userId }, function(res) {
                if (res.success) {
                    const user = res.user;
                    const orders = res.orders || [];
                    
                    $('#userHistoryName').text(user.hoten || user.tendangnhap);
                    $('#totalOrders').text(orders.length);
                    
                    let totalSpent = 0;
                    let html = '';
                    
                    if (orders.length === 0) {
                        html = `
                            <div style="text-align: center; padding: 60px; color: #999;">
                                <i class="fas fa-shopping-bag" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                                <p style="font-size: 16px;">Khách hàng chưa có đơn hàng nào</p>
                            </div>
                        `;
                    } else {
                        orders.forEach(function(order) {
                            totalSpent += parseFloat(order.tong_tien);
                            
                            const statusColors = {
                                'Chờ xử lý': '#ffc107',
                                'Đang giao': '#007bff',
                                'Đã giao': '#28a745',
                                'Đã hủy': '#dc3545'
                            };
                            const statusColor = statusColors[order.trang_thai] || '#6c757d';
                            
                            html += `
                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 15px; transition: all 0.3s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                        <div>
                                            <h4 style="margin: 0; color: #333; font-size: 16px;">
                                                <i class="fas fa-receipt"></i> Đơn hàng #${order.id}
                                            </h4>
                                            <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                                <i class="far fa-calendar"></i> ${new Date(order.ngay_dat).toLocaleDateString('vi-VN', { 
                                                    year: 'numeric', 
                                                    month: 'long', 
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                })}
                                            </p>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="background: ${statusColor}; color: white; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                ${order.trang_thai}
                                            </span>
                                            <div style="margin-top: 8px; font-size: 18px; font-weight: 700; color: #EF476F;">
                                                ${Number(order.tong_tien).toLocaleString('vi-VN')}₫
                                            </div>
                                        </div>
                                    </div>
                                    
                                    ${order.items && order.items.length > 0 ? `
                                        <div style="background: #f9fafb; padding: 12px; border-radius: 8px; margin-top: 12px;">
                                            <strong style="color: #555; font-size: 13px; display: block; margin-bottom: 8px;">
                                                <i class="fas fa-box"></i> Sản phẩm (${order.items.length}):
                                            </strong>
                                            ${order.items.map(item => `
                                                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px;">
                                                    <span style="color: #333;">• ${item.ten_san_pham}</span>
                                                    <span style="color: #666;">x${item.so_luong} = ${Number(item.thanh_tien).toLocaleString('vi-VN')}₫</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : ''}
                                    
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #666;">
                                        <i class="fas fa-map-marker-alt"></i> <strong>Giao đến:</strong> ${order.dia_chi || 'N/A'}<br>
                                        <i class="fas fa-phone"></i> <strong>SĐT:</strong> ${order.so_dien_thoai || 'N/A'}
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    $('#userHistoryContent').html(html);
                    $('#totalSpent').text(totalSpent.toLocaleString('vi-VN') + '₫');
                    
                } else {
                    $('#userHistoryContent').html(`
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #EF476F; margin-bottom: 15px;"></i>
                            <p style="color: #666;">${res.message || 'Không thể tải lịch sử'}</p>
                        </div>
                    `);
                }
            }, 'json').fail(function() {
                $('#userHistoryContent').html(`
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #FFD166; margin-bottom: 15px;"></i>
                        <p style="color: #666;">Lỗi kết nối server</p>
                    </div>
                `);
            });
        }
        
        function closeUserHistoryModal() {
            $('#modalUserHistory').fadeOut(200);
        }
        
        // === VOUCHER ===
        
        // Mở modal voucher
        function openVoucherModal(id = null) {
            if (id) {
                $('#voucherModalTitle').text('Chỉnh Sửa Voucher');
                $.post('admin_ajax.php', { action: 'get_voucher', id: id }, function(res) {
                    if (res.success) {
                        const v = res.data;
                        $('#voucher_id').val(v.id);
                        $('#voucher_ma').val(v.ma_voucher);
                        $('#voucher_ten').val(v.ten_voucher);
                        $('#voucher_mota').val(v.mo_ta);
                        $('#voucher_loai').val(v.loai_giam);
                        $('#voucher_giatri').val(v.gia_tri_giam);
                        $('#voucher_giamtoida').val(v.giam_toi_da);
                        $('#voucher_dontoithieu').val(v.gia_tri_don_toi_thieu || 0);
                        $('#voucher_soluong').val(v.so_luong);
                        $('#voucher_trangthai').val(v.trang_thai);
                        
                        // Convert datetime
                        const batdau = new Date(v.ngay_bat_dau);
                        const ketthuc = new Date(v.ngay_ket_thuc);
                        $('#voucher_batdau').val(batdau.toISOString().slice(0, 16));
                        $('#voucher_ketthuc').val(ketthuc.toISOString().slice(0, 16));
                        
                        toggleVoucherFields();
                    }
                }, 'json');
            } else {
                $('#voucherModalTitle').text('Thêm Voucher Mới');
                $('#formVoucher')[0].reset();
                $('#voucher_id').val('');
                
                // Set default dates
                const today = new Date().toISOString().split('T')[0];
                const nextMonth = new Date();
                nextMonth.setMonth(nextMonth.getMonth() + 1);
                $('#voucher_batdau').val(today);
                $('#voucher_ketthuc').val(nextMonth.toISOString().split('T')[0]);
                
                toggleVoucherFields();
            }
            $('#modalVoucher').fadeIn(200);
        }
        
        function closeVoucherModal() {
            $('#modalVoucher').fadeOut(200);
        }
        
        function toggleVoucherFields() {
            const loai = $('#voucher_loai').val();
            if (loai === 'phan_tram') {
                $('#giamToiDaGroup').show();
                $('#voucher_giatri').attr('max', '100').attr('placeholder', 'VD: 10 (cho 10%)');
            } else {
                $('#giamToiDaGroup').hide();
                $('#voucher_giatri').removeAttr('max').attr('placeholder', 'VD: 50000');
            }
        }
        
        // Edit voucher
        $(document).on('click', '.btn-edit-voucher', function(e) {
            e.preventDefault();
            openVoucherModal($(this).data('id'));
        });
        
        // Save voucher
        $('#formVoucher').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            $.post('admin_ajax.php', Object.fromEntries(formData), function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Đã lưu voucher');
                    closeVoucherModal();
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Lưu thất bại');
                }
            }, 'json');
        });
        
        // Delete voucher
        $(document).on('click', '.btn-delete-voucher', function(e) {
            e.preventDefault();
            if (!confirm('Xóa voucher này? Hành động không thể hoàn tác!')) return;
            
            const id = $(this).data('id');
            $.post('admin_ajax.php', { action: 'delete_voucher', id: id }, function(res) {
                if (res.success) {
                    toastr.success('Đã xóa voucher');
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Xóa thất bại');
                }
            }, 'json');
        });
        
        // Chọn tất cả vouchers
        $(document).on('change', '#selectAllVouchers', function() {
            const isChecked = $(this).prop('checked');
            $('.voucher-checkbox').prop('checked', isChecked);
            $('.selectAllCheckbox[data-target="voucher"]').prop('checked', isChecked);
            updateBulkDeleteButton('voucher');
        });
        
        // Delete selected vouchers
        $('#deleteSelectedVouchers').on('click', function() {
            const ids = [];
            $('.voucher-checkbox:checked').each(function() {
                ids.push($(this).data('id'));
            });
            
            if (ids.length === 0) return;
            if (!confirm(`Xóa ${ids.length} voucher đã chọn? Hành động không thể hoàn tác!`)) return;
            
            bulkDelete('voucher', ids);
        });
        
        function viewVoucherHistory(voucherId) {
            // TODO: Implement voucher history modal
            toastr.info('Chức năng xem lịch sử sử dụng voucher');
        }

        // === KHO HÀNG ===
        
        // Cập nhật tồn kho
        $(document).on('click', '.btn-update-stock', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const currentStock = $(this).data('stock');
            const productName = $(this).data('name');
            const productImage = $(this).data('image');

            let html = `<div style='display:flex;align-items:center;gap:12px;margin-bottom:12px;'>`;
            if (productImage) {
                html += `<img src='${productImage}' style='width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #eee;'>`;
            }
            html += `<span style='font-weight:600;font-size:16px;'>${productName || ''}</span></div>`;
            html += `<label>Nhập số lượng tồn kho mới:</label><input type='number' id='newStockInput' value='${currentStock}' min='0' style='width:120px;padding:8px;border-radius:6px;border:1px solid #ccc;'>`;
            html += `<div style='margin-top:16px;text-align:right;'><button id='confirmUpdateStockBtn' class='btn btn-primary'>Cập nhật</button></div>`;
            // Tạo modal đơn giản
            let $modal = $('#updateStockModal');
            if ($modal.length === 0) {
                $modal = $('<div id="updateStockModal" class="modal"><div class="modal-content" style="max-width:400px;position:relative;"></div></div>');
                $('body').append($modal);
            }
            $modal.find('.modal-content').html(html + `<span class='close-modal' style='position:absolute;top:12px;right:18px;font-size:28px;cursor:pointer;'>&times;</span>`);
            $modal.fadeIn(200);
            $modal.off('click', '.close-modal').on('click', '.close-modal', function() { $modal.fadeOut(200); });
            $modal.off('click', '#confirmUpdateStockBtn').on('click', '#confirmUpdateStockBtn', function() {
                const newStock = parseInt($('#newStockInput').val());
                if (isNaN(newStock) || newStock < 0) { toastr.error('Số lượng không hợp lệ!'); return; }
                $.post('admin_ajax.php', { action: 'update_stock', id: id, stock: newStock }, function(res) {
                    if (res.success) {
                        toastr.success('Đã cập nhật tồn kho');
                        $modal.fadeOut(200);
                        setTimeout(() => reloadCurrentSection(), 500);
                    } else {
                        toastr.error(res.message || 'Cập nhật thất bại');
                    }
                }, 'json');
            });
        });

        // === ĐÁNH GIÁ ===
        
        // Mở modal trả lời đánh giá
        function replyReview(id, currentReply) {
            $('#review_id_reply').val(id);
            $('#admin_reply_text').val(currentReply || '');
            $('#modalReplyReview').fadeIn(200);
        }
        
        function closeReplyModal() {
            $('#modalReplyReview').fadeOut(200);
        }
        
        // Submit trả lời đánh giá
        $('#formReplyReview').on('submit', function(e) {
            e.preventDefault();
            
            const reviewId = $('#review_id_reply').val();
            const replyText = $('#admin_reply_text').val().trim();
            
            if (!replyText) {
                toastr.error('Vui lòng nhập nội dung trả lời');
                return;
            }
            
            $.post('admin_ajax.php', {
                action: 'reply_review',
                review_id: reviewId,
                admin_reply: replyText
            }, function(res) {
                if (res.success) {
                    toastr.success('Đã gửi câu trả lời');
                    closeReplyModal();
                    setTimeout(() => reloadCurrentSection(), 500);
                } else {
                    toastr.error(res.message || 'Gửi trả lời thất bại');
                }
            }, 'json').fail(function() {
                toastr.error('Lỗi kết nối server');
            });
        });
        
        function deleteReview(id) {
            if (confirm('Xóa đánh giá này?')) {
                $.post('admin_ajax.php', { action: 'delete_review', id: id }, function(res) {
                    if (res.success) {
                        toastr.success('Đã xóa đánh giá');
                        setTimeout(() => reloadCurrentSection(), 500);
                    } else {
                        toastr.error(res.message || 'Xóa thất bại');
                    }
                }, 'json');
            }
        }

        // Tô màu dropdown trạng thái khi load trang
        $(document).ready(function() {
            $('.order-status-select').each(function() {
                const status = $(this).val();
                if (status === 'Đã giao') {
                    $(this).css({
                        'background': '#d1fae5',
                        'color': '#065f46'
                    });
                    $(this).prop('disabled', true);
                } else if (status === 'Đã hủy') {
                    $(this).css({
                        'background': '#f8d7da',
                        'color': '#721c24'
                    });
                    $(this).prop('disabled', false);
                } else if (status === 'Đang giao') {
                    $(this).css({
                        'background': '#ffe066',
                        'color': '#b68900'
                    });
                    $(this).prop('disabled', false);
                } else {
                    $(this).css({
                        'background': '#fff3cd',
                        'color': '#856404'
                    });
                    $(this).prop('disabled', false);
                }
            });
            
            $('.user-role-select').each(function() {
                const role = $(this).val();
                $(this).css({
                    'background': role === 'admin' ? '#f8d7da' : '#d4f4dd',
                    'color': role === 'admin' ? '#721c24' : '#0e6027'
                });
            });
            
            // === BULK SELECTION FUNCTIONALITY ===
            
            // Checkbox "Chọn tất cả" trong bulk-actions
            $(document).on('change', '#selectAllProducts', function() {
                const isChecked = $(this).prop('checked');
                $('.product-checkbox').prop('checked', isChecked);
                $('.selectAllCheckbox[data-target="product"]').prop('checked', isChecked);
                updateBulkDeleteButton('product');
            });
            
            $(document).on('change', '#selectAllCategories', function() {
                const isChecked = $(this).prop('checked');
                $('.category-checkbox').prop('checked', isChecked);
                $('.selectAllCheckbox[data-target="category"]').prop('checked', isChecked);
                updateBulkDeleteButton('category');
            });
            
            $(document).on('change', '#selectAllOrders', function() {
                const isChecked = $(this).prop('checked');
                $('.order-checkbox').prop('checked', isChecked);
                $('.selectAllCheckbox[data-target="order"]').prop('checked', isChecked);
                updateBulkDeleteButton('order');
            });
            
            $(document).on('change', '#selectAllUsers', function() {
                const isChecked = $(this).prop('checked');
                $('.user-checkbox').prop('checked', isChecked);
                $('.selectAllCheckbox[data-target="user"]').prop('checked', isChecked);
                updateBulkDeleteButton('user');
            });
            
            $(document).on('change', '#selectAllReviews', function() {
                const isChecked = $(this).prop('checked');
                $('.review-checkbox').prop('checked', isChecked);
                $('.selectAllCheckbox[data-target="review"]').prop('checked', isChecked);
                updateBulkDeleteButton('review');
            });
            
            // Toggle all checkboxes for a specific type (checkbox trong table header)
            $(document).on('change', '.selectAllCheckbox', function() {
                const target = $(this).data('target');
                const isChecked = $(this).prop('checked');
                $(`.${target}-checkbox`).prop('checked', isChecked);
                $(`#selectAll${capitalizeFirst(target)}s`).prop('checked', isChecked);
                updateBulkDeleteButton(target);
            });
            
            // Individual checkbox change
            $(document).on('change', '.item-checkbox', function() {
                const className = $(this).attr('class').split(' ').find(c => c.endsWith('-checkbox'));
                const target = className.replace('-checkbox', '');
                
                // Update "select all" checkbox state
                const total = $(`.${target}-checkbox`).length;
                const checked = $(`.${target}-checkbox:checked`).length;
                const allChecked = checked === total;
                
                $(`.selectAllCheckbox[data-target="${target}"]`).prop('checked', allChecked);
                $(`#selectAll${capitalizeFirst(target)}s`).prop('checked', allChecked);
                
                updateBulkDeleteButton(target);
            });
            
            // Update bulk delete button visibility and count
            function updateBulkDeleteButton(type) {
                const checked = $(`.${type}-checkbox:checked`).length;
                const total = $(`.${type}-checkbox`).length;
                
                // Chuyển type thành tên button phù hợp
                const typeMap = {
                    'product': 'Products',
                    'category': 'Categories',
                    'order': 'Orders',
                    'user': 'Users',
                    'review': 'Reviews',
                    'voucher': 'Vouchers'
                };
                
                const buttonType = typeMap[type] || capitalizeFirst(type) + 's';
                const $btn = $(`#deleteSelected${buttonType}`);
                
                if (checked > 0) {
                    $btn.show();
                    
                    // Đổi text nút khi chọn tất cả
                    if (checked === total) {
                        $btn.html('<i class="fas fa-trash"></i> Xóa tất cả');
                    } else {
                        $btn.html(`<i class="fas fa-trash"></i> Xóa đã chọn (<span id="selected${buttonType}Count">${checked}</span>)`);
                    }
                } else {
                    $btn.hide();
                }
            }
            
            function capitalizeFirst(str) {
                return str.charAt(0).toUpperCase() + str.slice(1);
            }
            
            // === BULK DELETE HANDLERS ===
            
            // Delete selected products
            $('#deleteSelectedProducts').on('click', function() {
                const ids = [];
                $('.product-checkbox:checked').each(function() {
                    ids.push($(this).data('id'));
                });
                
                if (ids.length === 0) return;
                if (!confirm(`Xóa ${ids.length} sản phẩm đã chọn? Hành động không thể hoàn tác!`)) return;
                
                bulkDelete('product', ids);
            });
            
            // Delete selected categories
            $('#deleteSelectedCategories').on('click', function() {
                const ids = [];
                $('.category-checkbox:checked').each(function() {
                    ids.push($(this).data('id'));
                });
                
                if (ids.length === 0) return;
                if (!confirm(`Xóa ${ids.length} danh mục đã chọn? Hành động không thể hoàn tác!`)) return;
                
                bulkDelete('category', ids);
            });
            
            // Delete selected orders
            $('#deleteSelectedOrders').on('click', function() {
                const ids = [];
                $('.order-checkbox:checked').each(function() {
                    ids.push($(this).data('id'));
                });
                
                if (ids.length === 0) return;
                if (!confirm(`Xóa ${ids.length} đơn hàng đã chọn? Hành động không thể hoàn tác!`)) return;
                
                bulkDelete('order', ids);
            });
            
            // Delete selected users
            $('#deleteSelectedUsers').on('click', function() {
                const ids = [];
                $('.user-checkbox:checked').each(function() {
                    ids.push($(this).data('id'));
                });
                
                if (ids.length === 0) return;
                if (!confirm(`Xóa ${ids.length} người dùng đã chọn? Hành động không thể hoàn tác!`)) return;
                
                bulkDelete('user', ids);
            });
            
            // Delete selected reviews
            $('#deleteSelectedReviews').on('click', function() {
                const ids = [];
                $('.review-checkbox:checked').each(function() {
                    ids.push($(this).data('id'));
                });
                
                if (ids.length === 0) return;
                if (!confirm(`Xóa ${ids.length} đánh giá đã chọn? Hành động không thể hoàn tác!`)) return;
                
                bulkDelete('review', ids);
            });
            
            // Bulk delete function
            function bulkDelete(type, ids) {
                let successCount = 0;
                let failCount = 0;
                const total = ids.length;
                
                toastr.info(`Đang xóa ${total} mục...`);
                
                // Delete items one by one
                let completed = 0;
                ids.forEach(function(id) {
                    $.post('admin_ajax.php', {
                        action: `delete_${type}`,
                        id: id
                    }, function(res) {
                        completed++;
                        if (res.success) {
                            successCount++;
                        } else {
                            failCount++;
                        }
                        
                        // When all requests complete
                        if (completed === total) {
                            if (successCount > 0) {
                                toastr.success(`Đã xóa ${successCount} mục thành công`);
                            }
                            if (failCount > 0) {
                                toastr.warning(`${failCount} mục không thể xóa`);
                            }
                            setTimeout(() => reloadCurrentSection(), 1000);
                        }
                    }, 'json').fail(function() {
                        completed++;
                        failCount++;
                        
                        if (completed === total) {
                            toastr.error(`Xóa thất bại: ${failCount} mục lỗi`);
                            if (successCount > 0) {
                                setTimeout(() => reloadCurrentSection(), 1000);
                            }
                        }
                    });
                });
            }
        });

        // ===== VOUCHER MANAGEMENT =====
        function toggleAddVoucherForm() {
            const form = document.getElementById('addVoucherForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
                // Set default dates
                const today = new Date().toISOString().split('T')[0];
                const nextMonth = new Date();
                nextMonth.setMonth(nextMonth.getMonth() + 1);
                document.getElementById('v_ngay_bat_dau').value = today;
                document.getElementById('v_ngay_ket_thuc').value = nextMonth.toISOString().split('T')[0];
            } else {
                form.style.display = 'none';
                document.getElementById('voucherForm').reset();
            }
        }

        function loadVouchers() {
            $.post('xu_ly_voucher.php', { action: 'load_vouchers' }, function(res) {
                if (res.success) {
                    let html = '';
                    if (res.vouchers.length === 0) {
                        html = '<tr><td colspan="9" style="text-align:center;">Chưa có voucher nào</td></tr>';
                    } else {
                        res.vouchers.forEach(v => {
                            const loaiText = v.loai_giam === 'phan_tram' ? v.gia_tri_giam + '%' : new Intl.NumberFormat('vi-VN').format(v.gia_tri_giam) + 'đ';
                            const statusBadge = v.trang_thai === 'hoat_dong' 
                                ? '<span class="badge badge-success">Hoạt động</span>' 
                                : '<span class="badge badge-secondary">Vô hiệu</span>';
                            
                            html += `
                                <tr>
                                    <td>${v.id}</td>
                                    <td><strong>${v.ma_voucher}</strong></td>
                                    <td>${v.ten_voucher}</td>
                                    <td>${loaiText}</td>
                                    <td>${v.loai_giam === 'phan_tram' ? 'Phần trăm' : 'Tiền mặt'}</td>
                                    <td>
                                        ${v.category_id ? 
                                            res.categories.find(c => c.id === v.category_id)?.ten_danh_muc || 'Không rõ' : 
                                            'Tất cả'}
                                    </td>
                                    <td>${new Date(v.ngay_bat_dau).toLocaleDateString('vi-VN')}</td>
                                    <td>${v.ngay_ket_thuc ? new Date(v.ngay_ket_thuc).toLocaleDateString('vi-VN') : 'Không xác định'}</td>
                                    <td>
                                        ${v.trang_thai === 'hoat_dong' ? 
                                            '<span class="badge badge-success">Hoạt động</span>' : 
                                            '<span class="badge badge-secondary">Vô hiệu</span>'}
                                    </td>
                                    <td>
                                        <button class="btn-edit" onclick="editVoucher(${v.id})" title="Sửa"><i class="fas fa-edit"></i></button>
                                        <button class="btn-delete" onclick="deleteVoucher(${v.id})" title="Xóa"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    $('#vouchersTable').html(html);
                }
            }, 'json');
        }

        function saveVoucher(e) {
            e.preventDefault();
            
            const data = {
                action: 'save_voucher',
                ma_voucher: $('#v_ma_voucher').val(),
                ten_voucher: $('#v_ten_voucher').val(),
                mo_ta: $('#v_mo_ta').val(),
                gia_tri_giam: $('#v_gia_tri_giam').val(),
                loai_giam: $('#v_loai_giam').val(),
                gia_tri_don_hang_toi_thieu: $('#v_gia_tri_don_hang_toi_thieu').val() || 0,
                so_luong_con_lai: $('#v_so_luong_con_lai').val() || null,
                ngay_bat_dau: $('#v_ngay_bat_dau').val(),
                ngay_ket_thuc: $('#v_ngay_ket_thuc').val(),
                trang_thai: $('#v_trang_thai').val()
            };

            console.log('Saving voucher:', data);

            $.ajax({
                url: 'xu_ly_voucher.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(res) {
                    console.log('Response:', res);
                    if (res.success) {
                        toastr.success('Đã lưu voucher thành công!');
                        $('#voucherForm')[0].reset();
                        toggleAddVoucherForm();
                        loadVouchers(); // Reload danh sách voucher
                    } else {
                        toastr.error(res.message || 'Có lỗi xảy ra');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    toastr.error('Lỗi kết nối. Xem console để biết chi tiết.');
                }
            });
        }

        function updateVoucherDate(id, field, value) {
            $.post('xu_ly_voucher.php', {
                action: 'update_voucher_date',
                id: id,
                field: field,
                value: value
            }, function(res) {
                if (res.success) {
                    toastr.success('Đã cập nhật ngày thành công!');
                } else {
                    toastr.error(res.message || 'Có lỗi xảy ra');
                    loadVouchers(); // Reload to revert
                }
            }, 'json');
        }

        function editVoucher(id) {
            $.post('xu_ly_voucher.php', { action: 'get_voucher', id: id }, function(res) {
                if (res.success) {
                    const v = res.voucher;
                    $('#v_ma_voucher').val(v.ma_voucher);
                    $('#v_ten_voucher').val(v.ten_voucher);
                    $('#v_mo_ta').val(v.mo_ta || '');
                    $('#v_gia_tri_giam').val(v.gia_tri_giam);
                    $('#v_loai_giam').val(v.loai_giam);
                    $('#v_gia_tri_don_hang_toi_thieu').val(v.gia_tri_don_hang_toi_thieu || 0);
                    $('#v_so_luong_con_lai').val(v.so_luong_con_lai || '');
                    $('#v_ngay_bat_dau').val(v.ngay_bat_dau);
                    $('#v_ngay_ket_thuc').val(v.ngay_ket_thuc || '');
                    $('#v_trang_thai').val(v.trang_thai);
                    
                    document.getElementById('addVoucherForm').style.display = 'block';
                    
                    // Change form to update mode
                    $('#voucherForm').off('submit').on('submit', function(e) {
                        e.preventDefault();
                        $.post('xu_ly_voucher.php', {
                            action: 'update_voucher',
                            id: id,
                            ma_voucher: $('#v_ma_voucher').val(),
                            ten_voucher: $('#v_ten_voucher').val(),
                            mo_ta: $('#v_mo_ta').val(),
                            gia_tri_giam: $('#v_gia_tri_giam').val(),
                            loai_giam: $('#v_loai_giam').val(),
                            gia_tri_don_hang_toi_thieu: $('#v_gia_tri_don_hang_toi_thieu').val() || 0,
                            so_luong_con_lai: $('#v_so_luong_con_lai').val() || null,
                            ngay_bat_dau: $('#v_ngay_bat_dau').val(),
                            ngay_ket_thuc: $('#v_ngay_ket_thuc').val(),
                            trang_thai: $('#v_trang_thai').val()
                        }, function(res) {
                            if (res.success) {
                                toastr.success('Đã cập nhật voucher!');
                                toggleAddVoucherForm();
                                loadVouchers();
                                // Reset form handler
                                $('#voucherForm').off('submit').on('submit', saveVoucher);
                            } else {
                                toastr.error(res.message || 'Có lỗi');
                            }
                        }, 'json');
                    });
                }
            }, 'json');
        }

        function deleteVoucher(id) {
            if (confirm('Bạn có chắc muốn xóa voucher này?')) {
                $.post('xu_ly_voucher.php', { action: 'delete_voucher', id: id }, function(res) {
                    if (res.success) {
                        toastr.success('Đã xóa voucher!');
                        loadVouchers(); // Reload danh sách voucher
                    } else {
                        toastr.error(res.message || 'Có lỗi');
                    }
                }, 'json');
            }
        }

        // Load vouchers when section is shown
        $(document).on('click', 'a[href="#vouchers"]', function() {
            loadVouchers();
        });

        // Khôi phục section cuối cùng khi trang load
        $(document).ready(function() {
            const lastSection = localStorage.getItem('currentAdminSection');
            if (lastSection && lastSection !== 'dashboard') {
                showSection(lastSection);
                // Load dữ liệu tương ứng với từng section
                switch(lastSection) {
                    case 'vouchers':
                        loadVouchers();
                        break;
                    case 'products':
                        // Data đã load trong PHP
                        break;
                    case 'orders':
                        // Data đã load trong PHP
                        break;
                    case 'users':
                        // Data đã load trong PHP
                        break;
                    case 'reviews':
                        // Data đã load trong PHP
                        break;
                    case 'categories':
                        // Data đã load trong PHP
                        break;
                    case 'warehouse':
                        // Data đã load trong PHP
                        break;
                }
            }
        });

        console.log('✅ Khuyến mãi!');

        // ========== PROMOTIONS / KHUYẾN MÃI ==========
        function switchPromoTab(tab) {
            document.querySelectorAll('.promo-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.promo-content').forEach(c => c.style.display = 'none');
            
            document.querySelector(`.promo-tab[data-tab="${tab}"]`).classList.add('active');
            document.getElementById('promo-' + tab).style.display = 'block';
        }

        function applyCategoryDiscount(categoryId) {
            const discount = document.getElementById('cat-discount-' + categoryId).value;
            const type = document.getElementById('cat-type-' + categoryId).value;
            
            if (!discount || discount <= 0) {
                toastr.error('Vui lòng nhập giá trị giảm giá hợp lệ!');
                return;
            }
            
            if (confirm('Áp dụng giảm giá ' + discount + (type === 'percent' ? '%' : 'đ') + ' cho tất cả sản phẩm trong danh mục này?')) {
                $.post('xu_ly_khuyen_mai.php', {
                    action: 'category_discount',
                    category_id: categoryId,
                    discount: discount,
                    type: type
                }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        }

        function removeCategoryDiscount(categoryId) {
            if (confirm('Xóa giảm giá cho tất cả sản phẩm trong danh mục này?')) {
                $.post('xu_ly_khuyen_mai.php', {
                    action: 'remove_category_discount',
                    category_id: categoryId
                }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        }

        function startStoreSale() {
            const name = document.getElementById('saleName').value;
            const value = document.getElementById('saleValue').value;
            const type = document.getElementById('saleType').value;
            const start = document.getElementById('saleStart').value;
            const end = document.getElementById('saleEnd').value;
            
            if (!name || !value || !end) {
                toastr.error('Vui lòng điền đầy đủ thông tin!');
                return;
            }
            
            if (confirm('Bắt đầu chương trình Sale toàn cửa hàng? Tất cả sản phẩm sẽ được giảm giá.')) {
                $.post('xu_ly_khuyen_mai.php', {
                    action: 'store_sale',
                    name: name,
                    value: value,
                    type: type,
                    start: start,
                    end: end
                }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        }

        function endStoreSale() {
            if (confirm('Kết thúc chương trình Sale? Giá giảm của tất cả sản phẩm sẽ được xóa.')) {
                $.post('xu_ly_khuyen_mai.php', {
                    action: 'end_store_sale'
                }, function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        }

        // Check current sale on page load
        function checkCurrentSale() {
            $.get('xu_ly_khuyen_mai.php?action=check_sale', function(res) {
                if (res.success && res.sale) {
                    const s = res.sale;
                    document.getElementById('currentSaleBanner').style.display = 'block';
                    document.getElementById('currentSaleInfo').innerHTML = 
                        'Giảm ' + s.gia_tri_giam + (s.loai_giam === 'phan_tram' ? '%' : 'đ') + 
                        ' - Kết thúc: ' + s.ngay_ket_thuc;
                }
            }, 'json');
        }
    </script>
</body>
</html>
