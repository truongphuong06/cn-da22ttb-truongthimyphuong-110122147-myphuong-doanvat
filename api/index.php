<?php
/**
 * API Router - Main Entry Point
 * Handles all API requests and routes to appropriate handlers
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Auth.php';

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/Web/api/index.php', '', $uri);
$uri = str_replace('/Web/api', '', $uri);
$uri = trim($uri, '/');
$segments = $uri ? explode('/', $uri) : [];

// Route the request
try {
    // Extract endpoint and ID
    $endpoint = $segments[0] ?? '';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;
    
    // Route to appropriate controller
    switch ($endpoint) {
        case 'products':
            require_once __DIR__ . '/controllers/ProductController.php';
            $controller = new ProductController($conn);
            break;
            
        case 'cart':
            require_once __DIR__ . '/controllers/CartController.php';
            $controller = new CartController($conn);
            break;
            
        case 'users':
            require_once __DIR__ . '/controllers/UserController.php';
            $controller = new UserController($conn);
            break;
            
        case 'orders':
            require_once __DIR__ . '/controllers/OrderController.php';
            $controller = new OrderController($conn);
            break;
            
        case 'payments':
            require_once __DIR__ . '/controllers/PaymentController.php';
            $controller = new PaymentController($conn);
            break;
            
        case 'discounts':
            require_once __DIR__ . '/controllers/DiscountController.php';
            $controller = new DiscountController($conn);
            break;
            
        case 'reviews':
            require_once __DIR__ . '/controllers/ReviewController.php';
            $controller = new ReviewController($conn);
            break;
            
        case 'shipping':
            require_once __DIR__ . '/controllers/ShippingController.php';
            $controller = new ShippingController($conn);
            break;
            
        case 'notifications':
            require_once __DIR__ . '/controllers/NotificationController.php';
            $controller = new NotificationController($conn);
            break;
            
        case 'analytics':
            require_once __DIR__ . '/controllers/AnalyticsController.php';
            $controller = new AnalyticsController($conn);
            break;
            
        default:
            Response::error('Endpoint not found', 404);
            exit();
    }
    
    // Handle the request
    $controller->handleRequest($method, $id, $action);
    
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}
?>
