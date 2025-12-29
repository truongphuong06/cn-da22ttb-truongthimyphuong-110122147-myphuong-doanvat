<?php
/**
 * User Controller
 * Handles user authentication and profile management
 */
class UserController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function handleRequest($method, $id, $action) {
        switch ($method) {
            case 'GET':
                if ($action === 'profile') {
                    Auth::requireAuth();
                    $this->getProfile();
                } else {
                    Response::error('Invalid endpoint', 404);
                }
                break;
                
            case 'POST':
                if ($action === 'register') {
                    $this->register();
                } elseif ($action === 'login') {
                    $this->login();
                } elseif ($action === 'logout') {
                    $this->logout();
                } else {
                    Response::error('Invalid endpoint', 404);
                }
                break;
                
            case 'PUT':
                if ($action === 'profile') {
                    Auth::requireAuth();
                    $this->updateProfile();
                } else {
                    Response::error('Invalid endpoint', 404);
                }
                break;
                
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function register() {
        $data = Auth::getInput();
        
        Auth::validateRequired($data, ['ten_dang_nhap', 'mat_khau', 'email']);
        
        // Check if username exists
        $checkStmt = $this->conn->prepare("SELECT id FROM nguoi_dung WHERE ten_dang_nhap = ? OR email = ?");
        $checkStmt->execute([$data['ten_dang_nhap'], $data['email']]);
        
        if ($checkStmt->fetch()) {
            Response::error('Username or email already exists', 400);
        }
        
        $hashedPassword = password_hash($data['mat_khau'], PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("INSERT INTO nguoi_dung (ten_dang_nhap, mat_khau, email, ho_ten, sdt, vai_tro, ngay_tao) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $result = $stmt->execute([
            $data['ten_dang_nhap'],
            $hashedPassword,
            $data['email'],
            $data['ho_ten'] ?? null,
            $data['sdt'] ?? null
        ]);
        
        if ($result) {
            $userId = $this->conn->lastInsertId();
            Response::success([
                'user_id' => $userId,
                'username' => $data['ten_dang_nhap']
            ], 'Registration successful', 201);
        } else {
            Response::error('Registration failed');
        }
    }
    
    private function login() {
        $data = Auth::getInput();
        
        Auth::validateRequired($data, ['ten_dang_nhap', 'mat_khau']);
        
        $stmt = $this->conn->prepare("SELECT * FROM nguoi_dung WHERE ten_dang_nhap = ? OR email = ?");
        $stmt->execute([$data['ten_dang_nhap'], $data['ten_dang_nhap']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($data['mat_khau'], $user['mat_khau'])) {
            Response::error('Invalid credentials', 401);
        }
        
        if (!empty($user['khoa'])) {
            Response::error('Account is locked', 403);
        }
        
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['ten_dang_nhap'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['vai_tro'] = $user['vai_tro'];
        
        Response::success([
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['ten_dang_nhap'],
                'email' => $user['email'],
                'ho_ten' => $user['ho_ten'],
                'vai_tro' => (int)$user['vai_tro']
            ],
            'session_id' => session_id()
        ], 'Login successful');
    }
    
    private function logout() {
        session_start();
        session_destroy();
        Response::success(null, 'Logout successful');
    }
    
    private function getProfile() {
        $userId = Auth::getUser();
        
        $stmt = $this->conn->prepare("SELECT id, ten_dang_nhap, email, ho_ten, sdt, vai_tro, ngay_tao FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['id'] = (int)$user['id'];
            $user['vai_tro'] = (int)$user['vai_tro'];
            Response::success($user, 'Profile retrieved successfully');
        } else {
            Response::error('User not found', 404);
        }
    }
    
    private function updateProfile() {
        $userId = Auth::getUser();
        $data = Auth::getInput();
        
        $fields = [];
        $params = [];
        
        $allowedFields = ['ho_ten', 'email', 'sdt'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['mat_khau'])) {
            $fields[] = "mat_khau = ?";
            $params[] = password_hash($data['mat_khau'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            Response::error('No fields to update');
        }
        
        $params[] = $userId;
        
        $sql = "UPDATE nguoi_dung SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            Response::success(['user_id' => $userId], 'Profile updated successfully');
        } else {
            Response::error('Failed to update profile');
        }
    }
}
?>
