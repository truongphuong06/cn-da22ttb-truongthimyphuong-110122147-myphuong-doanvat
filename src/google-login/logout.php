<?php
// Logout for Google flow: destroy session and (optionally) revoke token.
session_start();

// Try to revoke Google token if stored in session
if (!empty($_SESSION['access_token'])) {
    try {
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
            if (isset($client)) {
                $client->revokeToken($_SESSION['access_token']);
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

// Clear session and redirect to site root
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
header('Location: ../trangchu.php');
exit;
?>
