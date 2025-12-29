
<?php
// admin_chat_api.php - API cho admin quản lý chat (chuẩn hóa, chỉ trả về JSON)
header('Content-Type: application/json; charset=utf-8');
session_start();

function send_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    $conn = new mysqli('localhost', 'root', '', 'ban_hang');
    if ($conn->connect_error) {
        send_json(['success' => false, 'error' => 'Lỗi kết nối database'], 500);
    }
    $conn->set_charset('utf8mb4');

    // Kiểm tra quyền admin (cho phép nhiều admin)
    $admin_id = $_SESSION['user_id'] ?? 0;
    $is_admin = $_SESSION['admin_logged_in'] ?? false;
    if (!$is_admin || !$admin_id) {
        send_json(['success' => false, 'error' => 'Không có quyền truy cập'], 403);
    }

    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET: Lấy danh sách session
    if ($action === 'get_sessions' && $method === 'GET') {
        $result = $conn->query("SELECT * FROM chat_sessions WHERE status = 'active' ORDER BY last_message_time DESC");
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        send_json(['success' => true, 'sessions' => $sessions]);

    // GET: Lấy tin nhắn của 1 session (có phân trang)
    } elseif ($action === 'get_messages' && $method === 'GET') {
        $session_id = $_GET['session_id'] ?? '';
        $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        if (!$session_id) send_json(['success' => false, 'error' => 'Thiếu session_id'], 400);
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC LIMIT ? OFFSET ?");
        $stmt->bind_param('sii', $session_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        send_json(['success' => true, 'messages' => $messages]);

    // POST: Admin gửi reply
    } elseif ($action === 'send_reply' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $session_id = $input['session_id'] ?? '';
        $message = trim($input['message'] ?? '');
        if (!$session_id || !$message) send_json(['success' => false, 'error' => 'Thiếu session_id hoặc message'], 400);
        // Lưu tin nhắn từ admin
        $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, message, is_from_admin, admin_id) VALUES (?, ?, 1, ?)");
        $stmt->bind_param('ssi', $session_id, $message, $admin_id);
        $stmt->execute();
        // Cập nhật session
        $stmt2 = $conn->prepare("UPDATE chat_sessions SET last_message = ?, last_message_time = CURRENT_TIMESTAMP WHERE session_id = ?");
        $stmt2->bind_param('ss', $message, $session_id);
        $stmt2->execute();
        send_json(['success' => true]);

    // POST: Đánh dấu đã đọc
    } elseif ($action === 'mark_read' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $session_id = $input['session_id'] ?? '';
        if (!$session_id) send_json(['success' => false, 'error' => 'Thiếu session_id'], 400);
        $stmt = $conn->prepare("UPDATE chat_sessions SET unread_count = 0 WHERE session_id = ?");
        $stmt->bind_param('s', $session_id);
        $stmt->execute();
        send_json(['success' => true]);

    } else {
        send_json(['success' => false, 'error' => 'Action không hợp lệ hoặc sai method'], 400);
    }

    $conn->close();

} catch (Exception $e) {
    send_json(['success' => false, 'error' => $e->getMessage()], 500);
}
