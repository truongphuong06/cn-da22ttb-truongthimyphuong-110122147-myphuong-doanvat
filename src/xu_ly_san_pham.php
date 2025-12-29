<?php
/**
 * Product Management Handler
 * Xử lý các thao tác quản lý sản phẩm
 */

// Load database connection
require_once __DIR__ . '/connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Thêm vào đầu file để debug
error_log("Request received: " . print_r($_POST, true));

// Note: $conn đã được load từ connect.php
// Convert PDO to mysqli for compatibility với code cũ
try {
    $mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli_conn->connect_error) {
        die(json_encode(['success' => false, 'message' => 'Kết nối database thất bại']));
    }
    $mysqli_conn->set_charset("utf8mb4");
    $conn = $mysqli_conn; // Override for this file
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Lỗi kết nối']));
}

// Xử lý khóa/mở khóa sản phẩm (toggle trang_thai)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_trang_thai') {
    try {
        $san_pham_id = intval($_POST['san_pham_id']);
        
        // Lấy trạng thái hiện tại
        $check_sql = "SELECT id, trang_thai FROM san_pham WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $san_pham_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Sản phẩm không tồn tại");
        }
        
        $product = $result->fetch_assoc();
        $new_status = ($product['trang_thai'] == 1) ? 0 : 1;
        
        // Cập nhật trạng thái
        $update_sql = "UPDATE san_pham SET trang_thai = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_status, $san_pham_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Cập nhật trạng thái thất bại");
        }
        
        $status_text = ($new_status == 1) ? 'Mở khóa' : 'Khóa';
        echo json_encode([
            'success' => true,
            'message' => "$status_text sản phẩm thành công",
            'new_status' => $new_status
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Xử lý xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'xoa_san_pham') {
    try {
        // Log thông tin request
        error_log("Processing delete request for product ID: " . $_POST['san_pham_id']);
        
        $san_pham_id = $_POST['san_pham_id'];
        
        // Kiểm tra xem ID có tồn tại không
        $check_sql = "SELECT id, hinh_anh FROM san_pham WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Prepare check statement failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $san_pham_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Sản phẩm không tồn tại");
        }
        
        // Lấy thông tin hình ảnh
        $san_pham = $check_result->fetch_assoc();
        
        // Tiếp tục với quá trình xóa
        $sql = "DELETE FROM san_pham WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare delete statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $san_pham_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute delete failed: " . $stmt->error);
        }
        
        // Xóa file hình ảnh nếu có
        if (!empty($san_pham['hinh_anh'])) {
            $image_path = __DIR__ . '/uploads/' . $san_pham['hinh_anh'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        error_log("Product deleted successfully. Affected rows: " . $stmt->affected_rows);
        echo json_encode([
            'success' => true, 
            'message' => 'Xóa sản phẩm thành công',
            'deletedId' => $san_pham_id
        ]);
        
    } catch (Exception $e) {
        error_log("Error in delete process: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

$conn->close();
?> 