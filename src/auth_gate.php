<?php
// auth_gate.php — include at top of pages that must be protected
// Usage: require_once __DIR__ . '/auth_gate.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// TEMPORARY: disable auth gate so all pages are public while debugging.
// Remove or revert this early return to re-enable authentication checks.
return;

// Pages that are always public (no login required)
// Per policy: only homepage and contact page are public.
$public_paths = [
    '\/trangchu.php$',
    '\/lienhe.php$',
    '\/giohang.php$',
    '\/dangnhap.php$',
    '\/dangky.php$'
];

// Compute the current request path (relative to document root)
$script = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ($_SERVER['PHP_SELF'] ?? '');
$scriptPath = parse_url($script, PHP_URL_PATH);

// Allow public pages
foreach ($public_paths as $pattern) {
    if (preg_match('#' . $pattern . '#', $scriptPath)) {
        return; // public page — no auth required
    }
}

// Check login: accept username, user_id or email
$logged = !empty($_SESSION['username']) || !empty($_SESSION['user_id']) || !empty($_SESSION['email']);
if ($logged) return;

// If AJAX request, return 401 JSON
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Otherwise redirect to login page and preserve return URL
$cur = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'];
$redirect = 'dangnhap.php?redirect=' . urlencode($cur);
header('Location: ' . $redirect);
exit;
