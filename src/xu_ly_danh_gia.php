<?php
session_start();
require_once __DIR__ . '/connect.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Handler for product reviews (from order page only)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('xu_ly_danh_gia.php: Not POST request');
    header('Location: ./');
    exit;
}

error_log('xu_ly_danh_gia.php: Processing review submission');
error_log('POST data: ' . print_r($_POST, true));
error_log('SESSION data: ' . print_r($_SESSION, true));

// Kiểm tra đăng nhập (cho phép cả user và admin)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['email']) && !isset($_SESSION['admin_logged_in'])) {
    error_log('xu_ly_danh_gia.php: User not logged in');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đánh giá sản phẩm.']);
    exit;
}

$san_pham_id = isset($_POST['san_pham_id']) ? (int)$_POST['san_pham_id'] : 0;
$user_id = $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? null);
$user_email = $_SESSION['email'] ?? null;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Lấy tên người dùng - ưu tiên từ session, nếu không có thì lấy từ đơn hàng
$user_name = $_SESSION['username'] ?? ($_SESSION['admin_username'] ?? null);

// Nếu không có tên trong session, lấy từ đơn hàng gần nhất
if (empty($user_name)) {
    try {
        if ($user_id) {
            $nameStmt = $conn->prepare("SELECT ten_khach_hang FROM don_hang WHERE nguoi_dung_id = ? ORDER BY ngay_dat DESC LIMIT 1");
            $nameStmt->execute([$user_id]);
            $nameResult = $nameStmt->fetch();
            if ($nameResult && !empty($nameResult['ten_khach_hang'])) {
                $user_name = $nameResult['ten_khach_hang'];
            }
        }
        
        // Nếu vẫn chưa có, thử tìm theo email
        if (empty($user_name) && $user_email) {
            $nameStmt = $conn->prepare("SELECT ten_khach_hang FROM don_hang WHERE email = ? ORDER BY ngay_dat DESC LIMIT 1");
            $nameStmt->execute([$user_email]);
            $nameResult = $nameStmt->fetch();
            if ($nameResult && !empty($nameResult['ten_khach_hang'])) {
                $user_name = $nameResult['ten_khach_hang'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting user name: " . $e->getMessage());
    }
}

// Nếu vẫn không có tên, dùng email hoặc 'Khách hàng'
if (empty($user_name)) {
    $user_name = $user_email ?? 'Khách hàng';
}

error_log("Review data: product_id=$san_pham_id, user_id=$user_id, user_email=$user_email, user_name=$user_name, rating=$rating");

if ($san_pham_id <= 0 || $comment === '') {
    error_log('xu_ly_danh_gia.php: Missing required fields');
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đủ nội dung đánh giá.']);
    exit;
}

if ($rating < 1 || $rating > 5) $rating = 5;

try {
    // Kiểm tra người dùng đã mua sản phẩm này chưa và đơn hàng đã giao
    $hasPurchased = false;
    
    error_log("Checking if user has purchased product...");
    error_log("User ID: " . ($user_id ?? 'NULL') . ", Email: " . ($user_email ?? 'NULL'));
    
    // Nếu là admin, bỏ qua kiểm tra mua hàng
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    if ($isAdmin) {
        error_log("User is admin - skipping purchase check");
        $hasPurchased = true;
    } else {
        // Kiểm tra trong chi_tiet_don_hang - chỉ cho phép đánh giá khi đơn hàng đã giao
        if ($user_id) {
            // Tìm theo user_id
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM chi_tiet_don_hang cd
                INNER JOIN don_hang d ON cd.don_hang_id = d.id
                WHERE cd.san_pham_id = ? 
                AND d.nguoi_dung_id = ?
                AND d.trang_thai = 'Đã giao'
            ");
            $checkStmt->execute([$san_pham_id, $user_id]);
            $result = $checkStmt->fetch();
            
            error_log("Query by user_id result: " . print_r($result, true));
            
            if ($result && $result['count'] > 0) {
                $hasPurchased = true;
            }
        }
        
        // Nếu chưa tìm thấy và có email, thử tìm theo email
        if (!$hasPurchased && $user_email) {
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM chi_tiet_don_hang cd
                INNER JOIN don_hang d ON cd.don_hang_id = d.id
                WHERE cd.san_pham_id = ? 
                AND d.email = ?
                AND d.trang_thai = 'Đã giao'
            ");
            $checkStmt->execute([$san_pham_id, $user_email]);
            $result = $checkStmt->fetch();
            
            error_log("Query by email result: " . print_r($result, true));
            
            if ($result && $result['count'] > 0) {
                $hasPurchased = true;
            }
        }
    }
    
    error_log("Final hasPurchased: " . ($hasPurchased ? 'YES' : 'NO'));
    
    if (!$hasPurchased) {
        error_log('xu_ly_danh_gia.php: User has not purchased this product');
        echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể đánh giá sản phẩm khi đơn hàng đã được giao.']);
        exit;
    }
    
    error_log("Purchase check passed");
    
    // Kiểm tra đã đánh giá chưa
    error_log("Checking for duplicate review...");
    $checkDuplicate = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM danh_gia 
        WHERE san_pham_id = ? 
        AND (user_id = ? OR user_email = ?)
    ");
    $checkDuplicate->execute([$san_pham_id, $user_id, $user_email]);
    $dupResult = $checkDuplicate->fetch();
    
    if ($dupResult && $dupResult['count'] > 0) {
        error_log('xu_ly_danh_gia.php: Duplicate review found');
        echo json_encode(['success' => false, 'message' => 'Bạn đã đánh giá sản phẩm này rồi.']);
        exit;
    }

    error_log("No duplicate found, creating table if not exists...");
    
    // Create table for reviews (separate from comments)
    $sqlCreate = "CREATE TABLE IF NOT EXISTS danh_gia (
        id INT AUTO_INCREMENT PRIMARY KEY,
        san_pham_id INT NOT NULL,
        user_id INT NULL,
        user_email VARCHAR(255) NULL,
        user_name VARCHAR(150) NOT NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        comment TEXT NOT NULL,
        admin_reply TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(san_pham_id),
        INDEX(user_id),
        INDEX(user_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sqlCreate);
    
    error_log("Table created/verified, inserting review...");

    $stmt = $conn->prepare('INSERT INTO danh_gia (san_pham_id, user_id, user_email, user_name, rating, comment) VALUES (:pid, :uid, :email, :user, :rating, :comment)');
    $success = $stmt->execute([
        ':pid' => $san_pham_id,
        ':uid' => $user_id,
        ':email' => $user_email,
        ':user' => $user_name,
        ':rating' => $rating,
        ':comment' => $comment,
    ]);

    if ($success) {
        $insertId = $conn->lastInsertId();
        error_log('xu_ly_danh_gia.php: Review inserted successfully! Insert ID: ' . $insertId);
        echo json_encode([
            'success' => true, 
            'message' => 'Cảm ơn bạn đã đánh giá sản phẩm!',
            'review_id' => $insertId
        ]);
    } else {
        error_log('xu_ly_danh_gia.php: Insert failed - no exception but execute returned false');
        echo json_encode(['success' => false, 'message' => 'Gửi đánh giá thất bại. Vui lòng thử lại.']);
    }
} catch (Throwable $e) {
    error_log('xu_ly_danh_gia.php: Review insert failed: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Gửi đánh giá thất bại: ' . $e->getMessage()]);
}
