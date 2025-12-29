<?php
/**
 * Authentication Helper
 * Handle user authentication and authorization
 */
class Auth {
    
    public static function getUser() {
        session_start();
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserInfo() {
        session_start();
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'vai_tro' => $_SESSION['vai_tro'] ?? 0
        ];
    }
    
    public static function requireAuth() {
        if (!self::getUser()) {
            Response::error('Unauthorized. Please login first.', 401);
        }
    }
    
    public static function requireAdmin() {
        session_start();
        if (!isset($_SESSION['vai_tro']) || $_SESSION['vai_tro'] != 1) {
            Response::error('Forbidden. Admin access required.', 403);
        }
    }
    
    public static function getInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    public static function validateRequired($data, $required_fields) {
        $missing = [];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            Response::error('Missing required fields', 400, ['missing_fields' => $missing]);
        }
        
        return true;
    }
}
?>
