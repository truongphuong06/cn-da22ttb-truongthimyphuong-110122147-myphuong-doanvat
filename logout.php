<?php
// Complete logout: destroy session and redirect to homepage
session_start();

// Unset all session variables
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIES[session_name()])) {
    setcookie(session_name(), '', time()-86400, '/');
}

// If there's a session cookie, delete it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Start fresh session for next login
session_start();
session_destroy();

// Redirect to login page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: dangnhap.php');
exit;
?>
