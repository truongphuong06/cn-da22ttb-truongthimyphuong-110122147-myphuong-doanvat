<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$san_pham_id = (int)($input['san_pham_id'] ?? 0);

try {
    if ($action === 'toggle') {
        // Lấy user_id hoặc session_id
        $user_id = $_SESSION['user_id'] ?? null;
        $session_id = session_id();
        
        if (!$san_pham_id) {
            throw new Exception('Thiếu thông tin sản phẩm');
        }
        
        // Kiểm tra đã yêu thích chưa
        if ($user_id) {
            $stmt = $conn->prepare("SELECT id FROM yeu_thich WHERE nguoi_dung_id = ? AND san_pham_id = ?");
            $stmt->execute([$user_id, $san_pham_id]);
        } else {
            $stmt = $conn->prepare("SELECT id FROM yeu_thich WHERE session_id = ? AND san_pham_id = ?");
            $stmt->execute([$session_id, $san_pham_id]);
        }
        
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Bỏ yêu thích
            if ($user_id) {
                $stmt = $conn->prepare("DELETE FROM yeu_thich WHERE nguoi_dung_id = ? AND san_pham_id = ?");
                $stmt->execute([$user_id, $san_pham_id]);
            } else {
                $stmt = $conn->prepare("DELETE FROM yeu_thich WHERE session_id = ? AND san_pham_id = ?");
                $stmt->execute([$session_id, $san_pham_id]);
            }
            $liked = false;
        } else {
            // Thêm yêu thích
            $stmt = $conn->prepare("INSERT INTO yeu_thich (nguoi_dung_id, san_pham_id, session_id) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $san_pham_id, $session_id]);
            $liked = true;
        }
        
        // Đếm tổng lượt yêu thích
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM yeu_thich WHERE san_pham_id = ?");
        $stmt->execute([$san_pham_id]);
        $count = $stmt->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'count' => $count
        ]);
        
    } elseif ($action === 'get_count') {
        if (!$san_pham_id) {
            throw new Exception('Thiếu thông tin sản phẩm');
        }
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM yeu_thich WHERE san_pham_id = ?");
        $stmt->execute([$san_pham_id]);
        $count = $stmt->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        
    } elseif ($action === 'check_liked') {
        $user_id = $_SESSION['user_id'] ?? null;
        $session_id = session_id();
        
        if (!$san_pham_id) {
            throw new Exception('Thiếu thông tin sản phẩm');
        }
        
        if ($user_id) {
            $stmt = $conn->prepare("SELECT id FROM yeu_thich WHERE nguoi_dung_id = ? AND san_pham_id = ?");
            $stmt->execute([$user_id, $san_pham_id]);
        } else {
            $stmt = $conn->prepare("SELECT id FROM yeu_thich WHERE session_id = ? AND san_pham_id = ?");
            $stmt->execute([$session_id, $san_pham_id]);
        }
        
        $liked = $stmt->fetch() ? true : false;
        
        echo json_encode([
            'success' => true,
            'liked' => $liked
        ]);
        
    } else {
        throw new Exception('Action không hợp lệ');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
