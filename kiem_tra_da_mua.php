<?php
session_start();
require_once 'connect.php';
header('Content-Type: application/json');

if (!isset($_GET['san_pham_id'])) {
    echo json_encode(['canReview' => false, 'message' => 'ID sản phẩm không hợp lệ']);
    exit;
}

$san_pham_id = (int)$_GET['san_pham_id'];
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;

// Kiểm tra đăng nhập
if (!$user_id && !$user_email) {
    echo json_encode([
        'canReview' => false, 
        'message' => 'Vui lòng đăng nhập để đánh giá sản phẩm',
        'needLogin' => true
    ]);
    exit;
}

try {
    // Kiểm tra đã mua sản phẩm chưa và đơn hàng đã giao
    $hasPurchased = false;
    
    if ($user_id) {
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM chi_tiet_don_hang cd
            INNER JOIN don_hang d ON cd.don_hang_id = d.id
            WHERE cd.san_pham_id = ? 
            AND d.nguoi_dung_id = ?
            AND d.trang_thai = 'Đã giao'
        ");
        $checkStmt->execute([$san_pham_id, $user_id]);
    } else {
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM chi_tiet_don_hang cd
            INNER JOIN don_hang d ON cd.don_hang_id = d.id
            WHERE cd.san_pham_id = ? 
            AND d.email = ?
            AND d.trang_thai = 'Đã giao'
        ");
        $checkStmt->execute([$san_pham_id, $user_email]);
    }
    
    $result = $checkStmt->fetch();
    if ($result && $result['count'] > 0) {
        $hasPurchased = true;
    }
    
    if (!$hasPurchased) {
        echo json_encode([
            'canReview' => false, 
            'message' => 'Bạn chỉ có thể đánh giá sản phẩm khi đơn hàng đã được giao'
        ]);
        exit;
    }
    
    // Kiểm tra đã đánh giá chưa - sử dụng bảng danh_gia
    $checkReviewed = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM danh_gia 
        WHERE san_pham_id = ? 
        AND (user_id = ? OR user_email = ?)
    ");
    $checkReviewed->execute([$san_pham_id, $user_id, $user_email]);
    $reviewResult = $checkReviewed->fetch();
    
    if ($reviewResult && $reviewResult['count'] > 0) {
        echo json_encode([
            'canReview' => false, 
            'message' => 'Bạn đã đánh giá sản phẩm này rồi'
        ]);
        exit;
    }
    
    // Có thể đánh giá
    echo json_encode([
        'canReview' => true,
        'message' => 'Bạn có thể đánh giá sản phẩm này',
        'userName' => $_SESSION['username'] ?? $_SESSION['email'] ?? ''
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'canReview' => false, 
        'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
    ]);
}
?>
