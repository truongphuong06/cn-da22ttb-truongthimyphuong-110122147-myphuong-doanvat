<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Kiểm tra quyền admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit();
}

require_once __DIR__ . '/connect.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'category_discount':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $discount = (float)($_POST['discount'] ?? 0);
            $type = $_POST['type'] ?? 'percent';
            
            if ($categoryId <= 0 || $discount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
                exit();
            }
            
            // Lấy tất cả sản phẩm trong danh mục
            $stmt = $conn->prepare("SELECT id, gia FROM san_pham WHERE danh_muc_id = ?");
            $stmt->execute([$categoryId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($products as $p) {
                if ($type === 'percent') {
                    $newPrice = $p['gia'] * (1 - $discount / 100);
                } else {
                    $newPrice = max(0, $p['gia'] - $discount);
                }
                
                $update = $conn->prepare("UPDATE san_pham SET gia_giam = ? WHERE id = ?");
                $update->execute([round($newPrice), $p['id']]);
                $updated++;
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Đã áp dụng giảm giá cho $updated sản phẩm"
            ]);
            break;

        case 'remove_category_discount':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            
            $stmt = $conn->prepare("UPDATE san_pham SET gia_giam = 0 WHERE danh_muc_id = ?");
            $stmt->execute([$categoryId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã xóa giảm giá cho danh mục'
            ]);
            break;
            
        case 'store_sale':
            $name = trim($_POST['name'] ?? '');
            $value = (float)($_POST['value'] ?? 0);
            $type = $_POST['type'] ?? 'phan_tram';
            $start = $_POST['start'] ?? date('Y-m-d');
            $end = $_POST['end'] ?? '';
            
            if (empty($name) || $value <= 0 || empty($end)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
                exit();
            }
            
            // Tạo bảng nếu chưa có
            $conn->exec("CREATE TABLE IF NOT EXISTS khuyen_mai_toan_cua_hang (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ten VARCHAR(255) NOT NULL,
                gia_tri_giam DECIMAL(10,2) NOT NULL,
                loai_giam VARCHAR(20) DEFAULT 'phan_tram',
                ngay_bat_dau DATE,
                ngay_ket_thuc DATE,
                trang_thai VARCHAR(20) DEFAULT 'hoat_dong',
                ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Kết thúc sale cũ nếu có
            $conn->exec("UPDATE khuyen_mai_toan_cua_hang SET trang_thai = 'ket_thuc'");
            
            // Thêm sale mới
            $stmt = $conn->prepare("INSERT INTO khuyen_mai_toan_cua_hang 
                (ten, gia_tri_giam, loai_giam, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $value, $type, $start, $end]);
            
            // Áp dụng giảm giá cho tất cả sản phẩm
            $products = $conn->query("SELECT id, gia FROM san_pham")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as $p) {
                if ($type === 'phan_tram') {
                    $newPrice = $p['gia'] * (1 - $value / 100);
                } else {
                    $newPrice = max(0, $p['gia'] - $value);
                }
                $update = $conn->prepare("UPDATE san_pham SET gia_giam = ? WHERE id = ?");
                $update->execute([round($newPrice), $p['id']]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã bắt đầu chương trình Sale toàn cửa hàng!'
            ]);
            break;

        case 'end_store_sale':
            // Kết thúc sale
            $conn->exec("UPDATE khuyen_mai_toan_cua_hang SET trang_thai = 'ket_thuc'");
            
            // Xóa giảm giá tất cả sản phẩm
            $conn->exec("UPDATE san_pham SET gia_giam = 0");
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã kết thúc chương trình Sale'
            ]);
            break;
            
        case 'check_sale':
            try {
                $stmt = $conn->query("SELECT * FROM khuyen_mai_toan_cua_hang WHERE trang_thai = 'hoat_dong' AND ngay_ket_thuc >= CURDATE() ORDER BY id DESC LIMIT 1");
                $sale = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'sale' => $sale]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'sale' => null]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
            break;
    }
} catch (Exception $e) {
    error_log("Error in xu_ly_khuyen_mai.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
    ]);
}
