<?php
// Layout chung cho tất cả trang admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - My Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #5A9FA3;
            --secondary: #4DD4D6;
            --accent: #FFD166;
            --coral: #FF9A76;
            --pink: #EF476F;
            --emerald: #06D6A0;
            --dark: #2d3748;
            --gray-100: #f7fafc;
            --gray-200: #edf2f7;
            --gray-300: #e2e8f0;
            --gray-400: #cbd5e0;
            --gray-500: #a0aec0;
            --gray-600: #718096;
            --gray-700: #4a5568;
            --gray-800: #2d3748;
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #3D4457 0%, #1a1f2e 100%);
            padding: 2rem 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--accent), var(--coral));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .admin-title {
            color: var(--accent);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .menu-item {
            margin: 0.25rem 1rem;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .menu-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .menu-link.active {
            background: linear-gradient(135deg, var(--accent), var(--coral));
            color: white;
            box-shadow: 0 4px 12px rgba(255, 209, 102, 0.3);
        }

        .menu-link i {
            width: 24px;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90, 159, 163, 0.3);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="admin-avatar">
                <i class="fas fa-gem"></i>
            </div>
            <h2>My Shop</h2>
            <p class="admin-title">Quản Trị Viên</p>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="admin_dashboard.php" class="menu-link <?php echo $current_page == 'admin_dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="admin_products.php" class="menu-link <?php echo $current_page == 'admin_products' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Sản Phẩm</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="admin_categories.php" class="menu-link <?php echo $current_page == 'admin_categories' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Danh Mục</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="admin_orders.php" class="menu-link <?php echo $current_page == 'admin_orders' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Đơn Hàng</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="admin_customers.php" class="menu-link <?php echo $current_page == 'admin_customers' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Khách Hàng</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="admin_reviews.php" class="menu-link <?php echo $current_page == 'admin_reviews' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Đánh Giá</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="admin_warehouse.php" class="menu-link <?php echo $current_page == 'admin_warehouse' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span>Kho Hàng</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="logout.php" class="menu-link" style="color: #FFD166;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Đăng Xuất</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($page_title)): ?>
        <div class="page-header">
            <h1><?php echo $page_title; ?></h1>
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <span>Trang Chủ</span>
                <i class="fas fa-chevron-right"></i>
                <span><?php echo $page_title; ?></span>
            </div>
        </div>
        <?php endif; ?>
