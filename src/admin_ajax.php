<?php
if (session_status() === PHP_SESSION_NONE) session_start();
/**
 * Admin AJAX Handler
 * Xử lý các request AJAX của admin
 */

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);


// Load database connection (PDO first for constants)
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');
ob_clean(); // Clear any previous output

// Kiểm tra quyền admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Kết nối mysqli (để tương thích) - OVERRIDE $conn
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Kết nối DB thất bại']));
}
$conn->set_charset("utf8mb4");

// Load notification helpers AFTER mysqli connection is set
require_once __DIR__ . '/notification_helpers.php';

$action = $_POST['action'] ?? '';

try {
        // === LỊCH SỬ NHẬP KHO ===
        if ($action === 'get_import_history') {
            $product_id = (int)($_POST['product_id'] ?? 0);
            $stmt = $conn->prepare(
                "SELECT nk.ngay_nhap, nk.so_luong, nk.ghi_chu, nk.loai, sp.hinh_anh
                 FROM nhap_kho nk
                 JOIN san_pham sp ON nk.san_pham_id = sp.id
                 WHERE nk.san_pham_id = ?
                 ORDER BY nk.ngay_nhap DESC"
            );
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            echo json_encode([
                'success' => true,
                'history' => $rows
            ]);
            exit();
        }
    // === SẢN PHẨM ===
    
    if ($action === 'get_product') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM san_pham WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm']);
        }
        exit();
    }
    
    if ($action === 'save_product') {
        $id = (int)($_POST['id'] ?? 0);
        $ma_san_pham = trim($_POST['ma_san_pham'] ?? '');
        $ten_san_pham = trim($_POST['ten_san_pham'] ?? '');
        $danh_muc_id = (int)($_POST['danh_muc_id'] ?? 0);
        $gia = (float)($_POST['gia'] ?? 0);
        $gia_giam = isset($_POST['gia_giam']) && $_POST['gia_giam'] !== '' ? (float)$_POST['gia_giam'] : null;
        $so_luong = (int)($_POST['so_luong'] ?? 0);
        $mo_ta = trim($_POST['mo_ta'] ?? '');
        
        // Kiểm tra giá giảm phải nhỏ hơn giá gốc
        if ($gia_giam !== null && $gia_giam >= $gia) {
            echo json_encode(['success' => false, 'message' => 'Giá giảm phải nhỏ hơn giá gốc']);
            exit();
        }
        
        // Xử lý upload ảnh
        $hinh_anh = '';
        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === 0) {
            $ext = pathinfo($_FILES['hinh_anh']['name'], PATHINFO_EXTENSION);
            $hinh_anh = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            move_uploaded_file($_FILES['hinh_anh']['tmp_name'], __DIR__ . '/uploads/' . $hinh_anh);
        }
        
        if ($id > 0) {
            // Cập nhật
            if ($hinh_anh) {
                $stmt = $conn->prepare("UPDATE san_pham SET ma_san_pham=?, ten_san_pham=?, danh_muc_id=?, gia=?, gia_giam=?, so_luong=?, mo_ta=?, hinh_anh=? WHERE id=?");
                $stmt->bind_param("ssiddissi", $ma_san_pham, $ten_san_pham, $danh_muc_id, $gia, $gia_giam, $so_luong, $mo_ta, $hinh_anh, $id);
            } else {
                $stmt = $conn->prepare("UPDATE san_pham SET ma_san_pham=?, ten_san_pham=?, danh_muc_id=?, gia=?, gia_giam=?, so_luong=?, mo_ta=? WHERE id=?");
                $stmt->bind_param("ssiddisi", $ma_san_pham, $ten_san_pham, $danh_muc_id, $gia, $gia_giam, $so_luong, $mo_ta, $id);
            }
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Cập nhật sản phẩm thành công']);
        } else {
            // Thêm mới
            if (!$hinh_anh) {
                $hinh_anh = 'default.jpg';
            }
            $stmt = $conn->prepare("INSERT INTO san_pham (ma_san_pham, ten_san_pham, danh_muc_id, gia, gia_giam, so_luong, mo_ta, hinh_anh) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiddiss", $ma_san_pham, $ten_san_pham, $danh_muc_id, $gia, $gia_giam, $so_luong, $mo_ta, $hinh_anh);
            $stmt->execute();
            // Lấy id sản phẩm vừa thêm
            $new_product_id = $conn->insert_id;
            
            // Gọi helper tạo thông báo (wrapped in try-catch)
            try {
                error_log("=== START CREATE NOTIFICATION ===");
                error_log("Product ID: $new_product_id, Name: $ten_san_pham, Category ID: $danh_muc_id");
                
                // Lấy tên danh mục (cột là ten_san_pham trong bảng danh_muc)
                $cat_stmt = $conn->prepare("SELECT ten_san_pham FROM danh_muc WHERE id = ?");
                $cat_stmt->bind_param("i", $danh_muc_id);
                $cat_stmt->execute();
                $cat_result = $cat_stmt->get_result()->fetch_assoc();
                $category_name = $cat_result ? $cat_result['ten_san_pham'] : 'Danh mục';
                $cat_stmt->close();
                
                error_log("Category name: $category_name");
                
                // Tạo thông báo
                if (function_exists('auto_notify_new_product')) {
                    error_log("Calling auto_notify_new_product()");
                    $notify_result = auto_notify_new_product($new_product_id, $ten_san_pham, $category_name);
                    error_log("Notification result: " . ($notify_result ? "SUCCESS" : "FAILED"));
                } else {
                    error_log("ERROR: Function auto_notify_new_product() not found!");
                }
                
                error_log("=== END CREATE NOTIFICATION ===");
            } catch (Exception $e) {
                // Log lỗi nhưng không ảnh hưởng response
                error_log("Notification error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            
            echo json_encode(['success' => true, 'message' => 'Thêm sản phẩm thành công']);
        }
        exit();
    }
    
    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM san_pham WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã xóa sản phẩm']);
        exit();
    }
    
    // === DANH MỤC ===
    
    if ($action === 'get_category') {
        $id = (int)($_POST['id'] ?? 0);
        
        // Tự động phát hiện tên cột
        $dm_cols = [];
        $res = $conn->query("SHOW COLUMNS FROM danh_muc");
        while ($r = $res->fetch_assoc()) {
            $dm_cols[] = $r['Field'];
        }
        $dm_name_col = 'ten_danh_muc';
        foreach (['ten_danh_muc', 'ten_san_pham', 'ten', 'name'] as $c) {
            if (in_array($c, $dm_cols)) { $dm_name_col = $c; break; }
        }
        
        $stmt = $conn->prepare("SELECT id, `$dm_name_col` as ten_danh_muc, mo_ta FROM danh_muc WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        
        if ($category) {
            echo json_encode(['success' => true, 'category' => $category]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy danh mục']);
        }
        exit();
    }
    
    if ($action === 'save_category') {
        $id = (int)($_POST['id'] ?? 0);
        $ten_danh_muc = trim($_POST['ten_danh_muc'] ?? '');
        $mo_ta = trim($_POST['mo_ta'] ?? '');
        
        // Tự động phát hiện tên cột
        $dm_cols = [];
        $res = $conn->query("SHOW COLUMNS FROM danh_muc");
        while ($r = $res->fetch_assoc()) {
            $dm_cols[] = $r['Field'];
        }
        $dm_name_col = 'ten_danh_muc';
        foreach (['ten_danh_muc', 'ten_san_pham', 'ten', 'name'] as $c) {
            if (in_array($c, $dm_cols)) { $dm_name_col = $c; break; }
        }
        
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE danh_muc SET `$dm_name_col`=?, mo_ta=? WHERE id=?");
            $stmt->bind_param("ssi", $ten_danh_muc, $mo_ta, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Cập nhật danh mục thành công']);
        } else {
            $stmt = $conn->prepare("INSERT INTO danh_muc (`$dm_name_col`, mo_ta) VALUES (?, ?)");
            $stmt->bind_param("ss", $ten_danh_muc, $mo_ta);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Thêm danh mục thành công']);
        }
        exit();
    }
    
    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM danh_muc WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã xóa danh mục']);
        exit();
    }
    
    // === ĐÁNH GIÁ ===
    
    if ($action === 'reply_review') {
        $review_id = (int)($_POST['review_id'] ?? 0);
        $admin_reply = trim($_POST['admin_reply'] ?? '');
        if (empty($admin_reply)) {
            echo json_encode(['success' => false, 'message' => 'Nội dung trả lời không được để trống']);
            exit();
        }
        $stmt = $conn->prepare("UPDATE danh_gia SET admin_reply = ? WHERE id = ?");
        $stmt->bind_param("si", $admin_reply, $review_id);
        $stmt->execute();
        // Lấy thông tin đánh giá và sản phẩm để tạo thông báo
        $info_stmt = $conn->prepare("SELECT dg.san_pham_id, sp.ten_san_pham, dg.user_id FROM danh_gia dg LEFT JOIN san_pham sp ON dg.san_pham_id = sp.id WHERE dg.id = ?");
        $info_stmt->bind_param("i", $review_id);
        $info_stmt->execute();
        $info = $info_stmt->get_result()->fetch_assoc();
        if ($info && function_exists('auto_notify_reply_review')) {
            auto_notify_reply_review($info['san_pham_id'], $info['ten_san_pham'], $info['user_id']);
        }
        echo json_encode(['success' => true, 'message' => 'Đã gửi câu trả lời']);
        exit();
    }
    
    if ($action === 'delete_review') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM danh_gia WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã xóa đánh giá']);
        exit();
    }
    
    // === ĐƠN HÀNG ===
    
    if ($action === 'get_order_detail') {
        $id = (int)($_POST['id'] ?? 0);
        // Lấy thông tin đơn hàng
        $stmt = $conn->prepare("SELECT * FROM don_hang WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit();
        }
        // Nếu có user, lấy tên khách hàng từ bảng nguoi_dung (an toàn, không lỗi nếu thiếu trường)
        if (!empty($order['nguoi_dung_id']) && $order['nguoi_dung_id'] > 0) {
            $userStmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE id = ?");
            $userStmt->bind_param("i", $order['nguoi_dung_id']);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc();
            if ($user) {
                $fields = ['hoten', 'ho_ten', 'name', 'full_name'];
                foreach ($fields as $f) {
                    if (isset($user[$f]) && !empty($user[$f])) {
                        $order['ten_khach_hang'] = $user[$f];
                        break;
                    }
                }
            }
        }
        // Lấy chi tiết sản phẩm trong đơn hàng
        $stmt = $conn->prepare("
            SELECT ct.*, sp.ten_san_pham 
            FROM chi_tiet_don_hang ct 
            LEFT JOIN san_pham sp ON ct.san_pham_id = sp.id 
            WHERE ct.don_hang_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode([
            'success' => true, 
            'data' => $order,
            'items' => $items
        ]);
        exit();
    }
    
    if ($action === 'update_order_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        // Chỉ cập nhật đúng trạng thái được chọn từ dropdown
        $valid_statuses = ['Chờ xử lý', 'Đang xử lý', 'Đang giao', 'Đã giao', 'Đã hủy'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
            exit();
        }
        $stmt = $conn->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?");
        if (!$stmt) {
            error_log("[ORDER_STATUS] Prepare failed for id=$id, status=$status: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Lỗi prepare: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("si", $status, $id);
        $execResult = $stmt->execute();
        error_log("[ORDER_STATUS] Update id=$id, status=$status, execResult=" . ($execResult ? 'OK' : 'FAIL'));
        if (!$execResult) {
            error_log("[ORDER_STATUS] Execute failed for id=$id, status=$status: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Lỗi execute: ' . $stmt->error]);
            exit();
        }
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái', 'new_status' => $status]);
        exit();
    }
    
    if ($action === 'delete_order') {
        $id = (int)($_POST['id'] ?? 0);
        // Xóa chi tiết đơn hàng trước
        $conn->query("DELETE FROM chi_tiet_don_hang WHERE don_hang_id = $id");
        // Xóa đơn hàng
        $stmt = $conn->prepare("DELETE FROM don_hang WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã xóa đơn hàng']);
        exit();
    }
    
    // === NGƯỜI DÙNG ===
        // Cập nhật trạng thái người dùng (kích hoạt/khóa)
        if ($action === 'update_user_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
            // Không cho khóa admin chính
            if ($id == 1) {
                echo json_encode(['success' => false, 'message' => 'Không thể thay đổi trạng thái admin chính']);
                exit();
            }
            $stmt = $conn->prepare("UPDATE nguoi_dung SET khoa = ? WHERE id = ?");
            $stmt->bind_param("ii", $status, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái']);
            }
            exit();
        }
    
    if ($action === 'update_user_role') {
        $id = (int)($_POST['id'] ?? 0);
        $role = trim($_POST['role'] ?? 'user');
        $stmt = $conn->prepare("UPDATE nguoi_dung SET quyen = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật quyền']);
        exit();
    }

    // Thêm người dùng mới
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        if (!$username || !$fullname || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
            exit();
        }
        // Kiểm tra trùng tên đăng nhập hoặc email
        $check = $conn->prepare("SELECT id FROM nguoi_dung WHERE tendangnhap = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Tên đăng nhập hoặc email đã tồn tại']);
            exit();
        }
        $check->close();
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO nguoi_dung (tendangnhap, hoten, email, mat_khau, quyen, ngay_tao, trang_thai) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssssss", $username, $fullname, $email, $hashed, $role, $now);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm người dùng']);
        }
        exit();
    }

    // Khóa/mở khóa người dùng
    if ($action === 'toggle_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id == 1) {
            echo json_encode(['success' => false, 'message' => 'Không thể khóa/mở khóa admin chính']);
            exit();
        }
        $row = $conn->query("SELECT khoa FROM nguoi_dung WHERE id = $id")->fetch_assoc();
        $newStatus = (isset($row['khoa']) && $row['khoa'] == 1) ? 0 : 1;
        $stmt = $conn->prepare("UPDATE nguoi_dung SET khoa = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'new_status' => $newStatus]);
        exit();
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        // Không cho xóa admin có id = 1
        if ($id == 1) {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa tài khoản admin chính']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM nguoi_dung WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã xóa người dùng']);
        exit();
    }
    
    // === KHO HÀNG ===
    
    if ($action === 'update_stock') {
        $id = (int)($_POST['id'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        // Lấy số lượng cũ và giá vốn
        $old = 0;
        $gia_nhap = 0;
        $res = $conn->query("SELECT so_luong, gia FROM san_pham WHERE id = $id");
        if ($res && $row = $res->fetch_assoc()) {
            $old = (int)$row['so_luong'];
            $gia_nhap = isset($row['gia']) ? (float)$row['gia'] : 0;
        }
        $stmt = $conn->prepare("UPDATE san_pham SET so_luong = ? WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Lỗi prepare UPDATE: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("ii", $stock, $id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Lỗi execute UPDATE: ' . $stmt->error]);
            exit();
        }
        $now = date('Y-m-d H:i:s');
        if ($stock > $old) {
            // Nhập kho
            $so_luong_nhap = $stock - $old;
            $note = 'Nhập kho qua cập nhật tồn kho';
            $type = 'import';
            $stmt2 = $conn->prepare("INSERT INTO nhap_kho (san_pham_id, ngay_nhap, so_luong, gia_nhap, ghi_chu, loai) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt2) {
                echo json_encode(['success' => false, 'message' => 'Lỗi prepare INSERT import: ' . $conn->error]);
                exit();
            }
            $stmt2->bind_param("isidss", $id, $now, $so_luong_nhap, $gia_nhap, $note, $type);
            if (!$stmt2->execute()) {
                echo json_encode(['success' => false, 'message' => 'Lỗi execute INSERT import: ' . $stmt2->error]);
                exit();
            }
        } else if ($stock < $old) {
            // Xuất kho
            $so_luong_xuat = $old - $stock;
            $note = 'Xuất kho qua cập nhật tồn kho';
            $type = 'export';
            $stmt2 = $conn->prepare("INSERT INTO nhap_kho (san_pham_id, ngay_nhap, so_luong, gia_nhap, ghi_chu, loai) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt2) {
                echo json_encode(['success' => false, 'message' => 'Lỗi prepare INSERT export: ' . $conn->error]);
                exit();
            }
            $so_luong_xuat = -$so_luong_xuat;
            $stmt2->bind_param("isidss", $id, $now, $so_luong_xuat, $gia_nhap, $note, $type);
            if (!$stmt2->execute()) {
                echo json_encode(['success' => false, 'message' => 'Lỗi execute INSERT export: ' . $stmt2->error]);
                exit();
            }
        }
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật tồn kho']);
        exit();
    }
    
    // === CHAT ===
    
    if ($action === 'delete_chat') {
        $session_id = trim($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            echo json_encode(['success' => false, 'message' => 'Session ID không hợp lệ']);
            exit();
        }
        
        // Xóa tất cả tin nhắn trong session
        $stmt = $conn->prepare("DELETE FROM chat_messages WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        
        // Xóa session
        $stmt = $conn->prepare("DELETE FROM chat_sessions WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Đã xóa phiên chat']);
        exit();
    }
    
    // Lấy lịch sử mua hàng
    if ($action == 'get_user_history') {
        $user_id = $_POST['user_id'];
        
        // Lấy thông tin user
        $result = $conn->query("SELECT * FROM nguoi_dung WHERE id = $user_id");
        $user = $result->fetch_assoc();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
            exit();
        }
        
        // Lấy danh sách đơn hàng
        $orders = [];
        $result = $conn->query("SELECT * FROM don_hang WHERE nguoi_dung_id = $user_id ORDER BY ngay_dat DESC");
        
        while ($order = $result->fetch_assoc()) {
            // Lấy chi tiết sản phẩm trong đơn hàng
            $order_id = $order['id'];
            $items = [];
            
            $items_result = $conn->query("
                SELECT ct.*, sp.ten_san_pham 
                FROM chi_tiet_don_hang ct 
                JOIN san_pham sp ON ct.san_pham_id = sp.id 
                WHERE ct.don_hang_id = $order_id
            ");
            
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            
            $order['items'] = $items;
            $orders[] = $order;
        }
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'orders' => $orders
        ]);
        exit();
    }
    
    // === REVENUE STATISTICS ===
    // === REVENUE STATISTICS (ĐÃ SỬA LỖI) ===
    if ($action === 'revenue_stats_full') {
        // 1. Xử lý ngày tháng (thêm giờ để lấy trọn ngày)
        $start_date = ($_POST['start_date'] ?? date('Y-m-01')) . ' 00:00:00';
        $end_date   = ($_POST['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

        // 2. Tổng quan doanh thu
        // Dùng COALESCE để tránh lỗi null, bỏ cột loi_nhuan để tránh lỗi
        $sql = "SELECT 
                    SUM(COALESCE(tong_thanh_toan, tong_tien)) as total_revenue, 
                    COUNT(*) as total_orders, 
                    SUM(CASE WHEN trang_thai = 'Đã giao' THEN 1 ELSE 0 END) as success_orders 
                FROM don_hang 
                WHERE ngay_dat BETWEEN '$start_date' AND '$end_date' 
                AND trang_thai != 'Đã hủy'";
        
        $result = $conn->query($sql);
        $totalRevenue = 0; $totalOrders = 0; $successOrders = 0;
        
        if ($result && $row = $result->fetch_assoc()) {
            $totalRevenue = (float)$row['total_revenue'];
            $totalOrders = (int)$row['total_orders'];
            $successOrders = (int)$row['success_orders'];
        }
        
        $successRate = $totalOrders > 0 ? round(($successOrders / $totalOrders) * 100, 1) : 0;

        // 3. Xu hướng doanh thu theo ngày
        $daily_labels = [];
        $daily_values = [];
        $sql = "SELECT DATE(ngay_dat) as d, SUM(COALESCE(tong_thanh_toan, tong_tien)) as revenue 
                FROM don_hang 
                WHERE ngay_dat BETWEEN '$start_date' AND '$end_date' 
                AND trang_thai != 'Đã hủy' 
                GROUP BY d ORDER BY d ASC";
        
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $daily_labels[] = date('d/m', strtotime($row['d']));
                $daily_values[] = (float)$row['revenue'];
            }
        }

        // 4. Doanh thu theo danh mục
        // Tính tiền bằng (so_luong * gia) để an toàn hơn
        $category_labels = [];
        $category_values = [];
        $dm_col = 'ten_danh_muc'; // Tên cột danh mục
        
        $sql = "SELECT dm.$dm_col as name, SUM(ct.so_luong * ct.gia) as revenue 
                FROM chi_tiet_don_hang ct
                JOIN don_hang dh ON ct.don_hang_id = dh.id
                JOIN san_pham sp ON ct.san_pham_id = sp.id
                JOIN danh_muc dm ON sp.danh_muc_id = dm.id
                WHERE dh.ngay_dat BETWEEN '$start_date' AND '$end_date' 
                AND dh.trang_thai != 'Đã hủy'
                GROUP BY dm.id ORDER BY revenue DESC";
                
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $category_labels[] = $row['name'];
                $category_values[] = (float)$row['revenue'];
            }
        }

        // 5. Top 5 sản phẩm bán chạy
        $top_products_html = '';
        $sql = "SELECT sp.ten_san_pham, sp.hinh_anh, SUM(ct.so_luong) as sold, SUM(ct.so_luong * ct.gia) as revenue
                FROM chi_tiet_don_hang ct
                JOIN san_pham sp ON ct.san_pham_id = sp.id
                JOIN don_hang dh ON ct.don_hang_id = dh.id
                WHERE dh.ngay_dat BETWEEN '$start_date' AND '$end_date' 
                AND dh.trang_thai != 'Đã hủy'
                GROUP BY sp.id ORDER BY sold DESC LIMIT 5";
                
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $i = 1;
            while ($p = $result->fetch_assoc()) {
                $img = !empty($p['hinh_anh']) ? 'uploads/' . $p['hinh_anh'] : 'https://via.placeholder.com/40';
                $top_products_html .= '<tr>
                    <td>#' . $i++ . '</td>
                    <td><img src="' . htmlspecialchars($img) . '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;"></td>
                    <td>' . htmlspecialchars($p['ten_san_pham']) . '</td>
                    <td style="text-align:center">' . number_format($p['sold']) . '</td>
                    <td style="text-align:right;color:#EF476F;font-weight:bold">' . number_format($p['revenue'], 0, ',', '.') . '₫</td>
                </tr>';
            }
        } else {
            $top_products_html = '<tr><td colspan="5" style="text-align:center;color:#999;">Chưa có dữ liệu</td></tr>';
        }

        // HTML Cards
        $cards_html = '
            <div class="stat-card blue"><h3>Doanh Thu</h3><div class="value">' . number_format($totalRevenue, 0, ',', '.') . '₫</div></div>
            <div class="stat-card green"><h3>Đơn Thành Công</h3><div class="value">' . $successOrders . ' <span style="font-size:14px;color:#666">/ ' . $totalOrders . '</span></div></div>
            <div class="stat-card purple"><h3>Tỉ Lệ Chốt</h3><div class="value">' . $successRate . '%</div></div>
        ';

        echo json_encode([
            'success' => true,
            'daily_labels' => $daily_labels,
            'daily_values' => $daily_values,
            'category_labels' => $category_labels,
            'category_values' => $category_values,
            'top_products_html' => $top_products_html,
            'cards_html' => $cards_html
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    
    // === VOUCHER MANAGEMENT ===
    
    // Lưu voucher
    if ($action == 'save_voucher') {
        $id = $_POST['id'] ?? null;
        $ma_voucher = trim($_POST['ma_voucher']);
        $ten_voucher = trim($_POST['ten_voucher']);
        $mo_ta = trim($_POST['mo_ta'] ?? '');
        $loai_giam = $_POST['loai_giam'];
        $gia_tri_giam = floatval($_POST['gia_tri_giam']);
        $giam_toi_da = !empty($_POST['giam_toi_da']) ? floatval($_POST['giam_toi_da']) : null;
        $gia_tri_don_toi_thieu = floatval($_POST['gia_tri_don_toi_thieu'] ?? 0);
        $so_luong = intval($_POST['so_luong']);
        $trang_thai = $_POST['trang_thai'];
        $ngay_bat_dau = $_POST['ngay_bat_dau'];
        $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
        
        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE voucher SET ma_voucher=?, ten_voucher=?, mo_ta=?, loai_giam=?, gia_tri_giam=?, giam_toi_da=?, gia_tri_don_toi_thieu=?, so_luong=?, trang_thai=?, ngay_bat_dau=?, ngay_ket_thuc=? WHERE id=?");
            $stmt->bind_param("ssssdddiissi", $ma_voucher, $ten_voucher, $mo_ta, $loai_giam, $gia_tri_giam, $giam_toi_da, $gia_tri_don_toi_thieu, $so_luong, $trang_thai, $ngay_bat_dau, $ngay_ket_thuc, $id);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO voucher (ma_voucher, ten_voucher, mo_ta, loai_giam, gia_tri_giam, giam_toi_da, gia_tri_don_toi_thieu, so_luong, trang_thai, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdddiiss", $ma_voucher, $ten_voucher, $mo_ta, $loai_giam, $gia_tri_giam, $giam_toi_da, $gia_tri_don_toi_thieu, $so_luong, $trang_thai, $ngay_bat_dau, $ngay_ket_thuc);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $id ? 'Đã cập nhật voucher' : 'Đã thêm voucher mới']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $stmt->error]);
        }
        exit();
    }
    
    // Lấy thông tin voucher
    if ($action == 'get_voucher') {
        $id = $_POST['id'];
        $result = $conn->query("SELECT * FROM voucher WHERE id = $id");
        $data = $result->fetch_assoc();
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy voucher']);
        }
        exit();
    }
    
    // Xóa voucher
    if ($action == 'delete_voucher') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM voucher WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đã xóa voucher']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa voucher']);
        }
        exit();
    }

    // === VOUCHER MANAGEMENT - ĐƠN GIẢN ===
    
    if ($action === 'load_vouchers') {
        $result = $conn->query("SELECT * FROM voucher ORDER BY id DESC");
        $vouchers = [];
        while ($row = $result->fetch_assoc()) {
            $vouchers[] = $row;
        }
        echo json_encode(['success' => true, 'vouchers' => $vouchers]);
        exit();
    }
    
    if ($action === 'save_voucher') {
        error_log("Save voucher called");
        
        $ma_voucher = strtoupper(trim($_POST['ma_voucher'] ?? ''));
        $ten_voucher = trim($_POST['ten_voucher'] ?? '');
        $gia_tri_giam = (int)($_POST['gia_tri_giam'] ?? 0);
        $loai_giam = $_POST['loai_giam'] ?? 'phan_tram';
        $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? '';
        $ngay_het_han = $_POST['ngay_het_han'] ?? '';
        $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
        
        error_log("Data: " . json_encode([
            'ma_voucher' => $ma_voucher,
            'ten_voucher' => $ten_voucher,
            'gia_tri_giam' => $gia_tri_giam,
            'loai_giam' => $loai_giam,
            'ngay_bat_dau' => $ngay_bat_dau,
            'ngay_het_han' => $ngay_het_han,
            'trang_thai' => $trang_thai
        ]));
        
        // Validate
        if (empty($ma_voucher) || empty($ten_voucher) || $gia_tri_giam <= 0) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
            exit();
        }
        
        // Check if voucher code exists
        $check = $conn->prepare("SELECT id FROM voucher WHERE ma_voucher = ?");
        $check->bind_param("s", $ma_voucher);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Mã voucher đã tồn tại']);
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO voucher (ma_voucher, ten_voucher, gia_tri_giam, loai_giam, ngay_bat_dau, ngay_het_han, trang_thai) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissss", $ma_voucher, $ten_voucher, $gia_tri_giam, $loai_giam, $ngay_bat_dau, $ngay_het_han, $trang_thai);
        
        if ($stmt->execute()) {
            error_log("Insert success");
            echo json_encode(['success' => true, 'message' => 'Đã thêm voucher']);
        } else {
            error_log("Insert failed: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Không thể thêm voucher: ' . $stmt->error]);
        }
        exit();
    }
    
    if ($action === 'get_voucher') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM voucher WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $voucher = $stmt->get_result()->fetch_assoc();
        
        if ($voucher) {
            echo json_encode(['success' => true, 'voucher' => $voucher]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy voucher']);
        }
        exit();
    }
    
    if ($action === 'update_voucher') {
        $id = (int)($_POST['id'] ?? 0);
        $ma_voucher = strtoupper(trim($_POST['ma_voucher'] ?? ''));
        $ten_voucher = trim($_POST['ten_voucher'] ?? '');
        $gia_tri_giam = (int)($_POST['gia_tri_giam'] ?? 0);
        $loai_giam = $_POST['loai_giam'] ?? 'phan_tram';
        $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? '';
        $ngay_het_han = $_POST['ngay_het_han'] ?? '';
        $trang_thai = $_POST['trang_thai'] ?? 'hoat_dong';
        
        // Check if voucher code exists (except current)
        $check = $conn->prepare("SELECT id FROM voucher WHERE ma_voucher = ? AND id != ?");
        $check->bind_param("si", $ma_voucher, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Mã voucher đã tồn tại']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE voucher SET ma_voucher=?, ten_voucher=?, gia_tri_giam=?, loai_giam=?, ngay_bat_dau=?, ngay_het_han=?, trang_thai=? WHERE id=?");
        $stmt->bind_param("ssissssi", $ma_voucher, $ten_voucher, $gia_tri_giam, $loai_giam, $ngay_bat_dau, $ngay_het_han, $trang_thai, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đã cập nhật voucher']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật']);
        }
        exit();
    }
    
    if ($action === 'update_voucher_date') {
        $id = (int)($_POST['id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        
        if (!in_array($field, ['ngay_bat_dau', 'ngay_het_han'])) {
            echo json_encode(['success' => false, 'message' => 'Trường không hợp lệ']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE voucher SET $field = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đã cập nhật ngày']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật']);
        }
        exit();
    }
    

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}

