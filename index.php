<link rel="stylesheet" href="assets/notification_bell.css">
<?php
/**
 * Home Page - Index
 * Trang chủ của website
 */

// Load database connection
require_once __DIR__ . '/connect.php';

// Temporary debug: append ?debug_session=1 to URL to see session contents
$show_debug = !empty($_GET['debug_session']);

try {
    // Lấy thông tin user nếu đã đăng nhập
    $user_info = null;
    if (isLoggedIn()) {
        $stmt = $conn->prepare("SELECT ten_dang_nhap, ho_ten, email FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lấy 12 sản phẩm mới nhất để hiển thị Featured
    $limit = 12;

    // Nếu có tham số tìm kiếm 'search', lọc theo tên sản phẩm (ten_san_pham)
    $search = '';
    if (!empty($_GET['search'])) {
      $search = trim((string)$_GET['search']);
    }

    if ($search !== '') {
      $limitInt = (int)$limit;

      // Map a few category tokens to safer, category-specific patterns to avoid short-token false-positives
      $categoryPatterns = [
        'ao' => ['%áo%', '%ao%'],
        'quan' => ['%quần%', '%quan%'],
        'dam' => ['%đầm%', '%dam%', '%váy%', '%vay%'],
        'phu-kien' => ['%phụ%', '%phu%', '%phụ kiện%', '%phu kien%'],
      ];

      $lowerToken = mb_strtolower($search, 'UTF-8');

      if (isset($categoryPatterns[$lowerToken])) {
        $patterns = $categoryPatterns[$lowerToken];
        $whereParts = [];
        $params = [];
        foreach ($patterns as $i => $pat) {
          $key = ':p' . $i;
          $whereParts[] = "sp.ten_san_pham LIKE " . $key;
          $params[$key] = $pat;
        }
        $sql = "SELECT sp.* FROM san_pham sp WHERE (" . implode(' OR ', $whereParts) . ") AND (sp.gia_giam IS NULL OR sp.gia_giam = 0 OR sp.gia_giam >= sp.gia) ORDER BY sp.id DESC LIMIT $limitInt";
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
          $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
      } else {
        $like = '%' . $search . '%';
        $stmt = $conn->prepare("SELECT sp.* FROM san_pham sp WHERE sp.ten_san_pham LIKE :s AND (sp.gia_giam IS NULL OR sp.gia_giam = 0 OR sp.gia_giam >= sp.gia) ORDER BY sp.id DESC LIMIT $limitInt");
        $stmt->bindValue(':s', $like, PDO::PARAM_STR);
        $stmt->execute();
      }
    } else {
      $stmt = $conn->query("SELECT sp.* FROM san_pham sp WHERE (sp.gia_giam IS NULL OR sp.gia_giam = 0 OR sp.gia_giam >= sp.gia) ORDER BY sp.id DESC LIMIT " . (int)$limit);
    }

    // --- Thêm: build map ảnh danh mục từ DB để dùng cho featured nếu sản phẩm không có ảnh ---
    $catImgMap = [];
    try {
        $catStmt = $conn->query("SELECT id, COALESCE(hinh_anh,'') AS img, COALESCE(ten_san_pham, '') AS name FROM danh_muc");
        $cats = $catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($cats as $c) {
            $cid = (int)$c['id'];
            $raw = trim($c['img']);
            if ($raw !== '') {
                $catImgMap[$cid] = (strpos($raw, 'http') === 0) ? $raw : 'uploads/' . $raw;
            } else {
                // fallback dựa trên tên danh mục — thay đường dẫn uploads/... bằng ảnh thực của bạn nếu cần
                $name = $c['name'] ?? '';
                if (mb_stripos($name, 'phụ') !== false) $catImgMap[$cid] = 'uploads/phu-kien.jpg';
                elseif (mb_stripos($name, 'váy') !== false || mb_stripos($name, 'đầm') !== false) $catImgMap[$cid] = 'uploads/vay-dam.jpg';
                elseif (mb_stripos($name, 'áo') !== false) $catImgMap[$cid] = 'uploads/ao-somi.jpg';
                elseif (mb_stripos($name, 'quần') !== false) $catImgMap[$cid] = 'uploads/quan.jpg';
                else $catImgMap[$cid] = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600';
            }
        }
    } catch (Throwable $e) {
        // ignore, sử dụng fallback sau
        $catImgMap = [];
    }
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Lỗi kết nối: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ==========================================
   LIGHT MINIMALIST DESIGN SYSTEM
   ========================================== */

:root {
  /* Stone Color Palette */
  --stone-50: #fafaf9;
  --stone-100: #f5f5f4;
  --stone-200: #e7e5e4;
  --stone-300: #d6d3d1;
  --stone-400: #a8a29e;
  --stone-500: #78716c;
  --stone-600: #57534e;
  --stone-900: #1c1917;
  --white: #ffffff;
  --rose-500: #f43f5e;
  
  /* Typography */
  --font-base: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  
  /* Layout */
  --container-max: 1280px;
  
  /* Border Radius */
  --radius-sm: 0.125rem;
  --radius-md: 0.25rem;
  --radius-lg: 0.5rem;
  --radius-full: 9999px;
}

/* ==========================================
   BASE STYLES
   ========================================== */

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: var(--font-base);
  font-size: 16px;
  line-height: 1.5;
  color: var(--stone-900);
  background-color: var(--stone-50);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a {
  color: inherit;
  text-decoration: none;
}

button {
  font-family: inherit;
  cursor: pointer;
  border: none;
  background: none;
}

img {
  max-width: 100%;
  height: auto;
  display: block;
}

/* ==========================================
   LAYOUT
   ========================================== */

.container {
  max-width: var(--container-max);
  margin: 0 auto;
  padding: 0 1rem;
}

@media (min-width: 640px) {
  .container { padding: 0 1.5rem; }
}

@media (min-width: 1024px) {
  .container { padding: 0 2rem; }
}

/* ==========================================
   ANNOUNCEMENT BAR
========================================== */

.announcement-bar {
  background-color: var(--stone-900);
  color: var(--white);
  padding: 0.625rem 0;
  text-align: center;
  font-size: 0.875rem;
}

.announcement-bar p {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

/* ==========================================
   HEADER
   ========================================== */

.header {
  position: sticky;
  top: 0;
  z-index: 40;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--stone-200);
}

.header .container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 4rem;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.5rem;
  font-weight: 600;
  letter-spacing: -0.025em;
  color: var(--stone-900);
}

.brand-logo img {
  height: 3.8rem;
  width: auto;
}

.nav {
  display: none;
  align-items: center;
  gap: 2rem;
}

@media (min-width: 1024px) {
  .nav { display: flex; }
}

.nav a {
  font-size: 0.875rem;
  color: var(--stone-700);
  transition: all 0.3s ease;
  padding: 0.625rem 1.25rem;
  border-radius: 25px;
  border: 2px solid var(--stone-300);
  font-weight: 500;
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
}

.nav a:hover {
  color: var(--stone-900);
  background: var(--stone-50);
  border-color: var(--stone-400);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.nav a.active {
  color: var(--stone-900);
  background: white;
  border-color: var(--stone-900);
  font-weight: 600;
  box-shadow: 0 2px 12px rgba(0,0,0,0.12);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* User menu dropdown */
.user-menu-wrapper { position: relative; }
.user-menu { 
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: var(--white);
  border: 1px solid var(--stone-200);
  box-shadow: 0 10px 20px rgba(0,0,0,0.08);
  border-radius: 8px;
  min-width: 160px;
  padding: 8px 0;
  display: none;
  z-index: 60;
}
.user-menu .user-greet { padding: 8px 12px; font-size: 0.95rem; color: var(--stone-700); border-bottom: 1px solid var(--stone-100); }
.user-menu-item { display: block; padding: 10px 14px; color: var(--stone-600); font-size: 0.95rem; text-decoration: none; }
.user-menu-item:hover { background: var(--stone-100); color: var(--stone-900); }
.user-menu-wrapper:hover .user-menu { display: block; }

/* On small screens show menu with a class toggle (JS will add .open) */
.user-menu-wrapper.open .user-menu { display: block; }

.icon-btn {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--stone-600);
  border-radius: var(--radius-lg);
  transition: all 0.2s;
  position: relative;
}

.icon-btn:hover {
  color: var(--stone-900);
  background-color: var(--stone-100);
}

.icon-btn i {
  font-size: 1.25rem;
}

.cart-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background-color: var(--stone-900);
  color: var(--white);
  font-size: 0.625rem;
  width: 1rem;
  height: 1rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
}

/* ==========================================
   BANNER SLIDER
   ========================================== */

.banner-slider {
  position: relative;
  height: 90vh;
  min-height: 600px;
  overflow: hidden;
  background: var(--stone-100);
}

.slider-track {
  position: relative;
  width: 100%;
  height: 100%;
}

.slide {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  transition: opacity 1s ease-in-out, transform 1s ease-in-out;
  transform: translateX(100%);
}

.slide.active {
  opacity: 1;
  transform: translateX(0);
  z-index: 2;
}

.slide.prev {
  transform: translateX(-100%);
  opacity: 0;
}

.slide-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
}

.slide-bg::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(to right, rgba(0,0,0,0.5), transparent);
}

.slide-content {
  position: relative;
  z-index: 3;
  max-width: var(--container-max);
  margin: 0 auto;
  padding: 0 2rem;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  color: white;
}

.slide-badge {
  display: inline-block;
  padding: 0.5rem 1.25rem;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: 1.5rem;
  width: fit-content;
  animation: fadeInUp 0.8s ease-out;
}

.slide h2 {
  font-size: 4rem;
  line-height: 1.1;
  font-weight: 700;
  margin-bottom: 1.5rem;
  max-width: 600px;
  animation: fadeInUp 1s ease-out;
}

.slide-desc {
  font-size: 1.25rem;
  line-height: 1.75;
  margin-bottom: 2.5rem;
  max-width: 500px;
  opacity: 0.95;
  animation: fadeInUp 1.2s ease-out;
}

.slide-cta {
  display: flex;
  gap: 1rem;
  animation: fadeInUp 1.4s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Slider Navigation */
.slider-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  width: 100%;
  max-width: var(--container-max);
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  justify-content: space-between;
  padding: 0 2rem;
  pointer-events: none;
}

.slider-btn {
  width: 3.5rem;
  height: 3.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-radius: 50%;
  border: none;
  outline: none;
  color: var(--stone-900);
  cursor: pointer;
  transition: all 0.3s;
  pointer-events: all;
  z-index: 20;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.slider-btn:hover {
  background: white;
  transform: scale(1.1);
  box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.slider-btn i {
  font-size: 1.5rem;
}

/* Slider Dots */
.slider-dots {
  position: absolute;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10;
  display: flex;
  gap: 0.75rem;
}

.dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
  cursor: pointer;
  transition: all 0.3s;
  border: 2px solid transparent;
}

.dot.active {
  width: 2.5rem;
  border-radius: var(--radius-full);
  background: white;
  border-color: rgba(255, 255, 255, 0.3);
}

.dot:hover {
  background: rgba(255, 255, 255, 0.8);
}

/* ==========================================
   RESPONSIVE BANNER
   ========================================== */

@media (max-width: 768px) {
  .banner-slider {
    height: 70vh;
    min-height: 500px;
  }

  .slide h2 {
    font-size: 2.5rem;
  }

  .slide-desc {
    font-size: 1rem;
  }

  .slider-btn {
    width: 2.5rem;
    height: 2.5rem;
  }

  .slider-btn i {
    font-size: 1.25rem;
  }

  .slider-nav {
    padding: 0 1rem;
  }
}

/* ==========================================
   FEATURES BAR
   ========================================== */

.features-bar {
  background-color: var(--white);
  border-top: 1px solid var(--stone-200);
  border-bottom: 1px solid var(--stone-200);
  padding: 1.5rem 0;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

@media (min-width: 1024px) {
  .features-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.feature-icon {
  font-size: 2rem;
  color: var(--stone-900);
  flex-shrink: 0;
}

.feature-text h3 {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--stone-900);
  margin-bottom: 0.125rem;
}

.feature-text p {
  font-size: 0.75rem;
  color: var(--stone-500);
}

/* ==========================================
   SECTIONS
   ========================================== */

.section {
  padding: 4rem 0;
}

.section-bg-white {
  background-color: var(--white);
}

.section-header {
  text-align: center;
  margin-bottom: 3rem;
}

.section-title {
  font-size: 1.875rem;
  color: var(--stone-900);
  margin-bottom: 0.5rem;
}

.section-subtitle {
  color: var(--stone-600);
}

/* ==========================================
   CATEGORIES (even spacing & equal height cards)
   ========================================== */

.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: stretch;
}

/* make the whole card a link and full height so items align */
.category-card,
.category-card > a {
  display: flex;
  flex-direction: column;
  height: 100%;
  background-color: var(--stone-50);
  border: 1px solid var(--stone-200);
  border-radius: var(--radius-md);
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
  text-decoration: none;
  color: inherit;
}

.category-card:hover,
.category-card > a:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 24px rgba(0,0,0,0.06);
  border-color: var(--stone-300);
}

/* fixed visual image area so all cards match */
.category-image {
  flex: 0 0 160px; /* same height for all images */
  width: 100%;
  overflow: hidden;
  background: var(--stone-100);
}

@media (min-width: 1024px) {
  .category-image { flex: 0 0 180px; }
}

.category-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.45s ease;
}

.category-card:hover .category-image img {
  transform: scale(1.04);
}

/* info area fills remaining height and is centered */
.category-info {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  gap: 0.25rem;
  flex: 1 1 auto;
}

.category-name {
  font-size: 1rem;
  font-weight: 600;
  color: var(--stone-900);
}

.category-count {
  font-size: 0.85rem;
  color: var(--stone-500);
}

/* ==========================================
   PRODUCTS
   ========================================== */

.gender-filter {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 3rem;
}

.filter-btn {
  padding: 0.5rem 1.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  border: 1px solid var(--stone-300);
  border-radius: var(--radius-md);
  color: var(--stone-600);
  transition: all 0.2s;
}

.filter-btn:hover {
  background-color: var(--stone-50);
  color: var(--stone-900);
}

.filter-btn.active {
  background-color: var(--stone-900);
  color: var(--white);
  border-color: var(--stone-900);
}

.products-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  margin-bottom: 3rem;
}

@media (min-width: 640px) {
  .products-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .products-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.product-card {
  background: #e8f2d8;
  border: 3px solid #a0c75f;
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.3s;
}

.product-card:hover {
  border-color: #8ab34f;
}

.product-image {
  position: relative;
  aspect-ratio: 3/4;
  background-color: var(--stone-50);
  overflow: hidden;
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.7s;
}

.product-card:hover .product-image img {
  transform: scale(1.05);
}

.product-badge {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  background-color: var(--stone-900);
  color: var(--white);
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 500;
}

.product-actions {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  opacity: 0;
  transition: opacity 0.3s;
}

.product-card:hover .product-actions {
  opacity: 1;
}

.action-btn {
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--white);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
  transition: all 0.2s;
}

.action-btn:hover {
  background-color: var(--stone-100);
}

.action-btn.favorited {
  background-color: var(--rose-500);
}

.action-btn.favorited i {
  color: var(--white);
}

.action-btn i {
  font-size: 1rem;
  color: var(--stone-900);
}

.product-add-cart {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 0.75rem;
  transform: translateY(100%);
  transition: transform 0.3s;
}

.product-card:hover .product-add-cart {
  transform: translateY(0);
}

.add-cart-btn,
.buy-now-btn {
  border: none;
  outline: none;
  text-decoration: none;
}

.add-cart-btn {
  width: 100%;
  background-color: var(--stone-900);
  color: var(--white);
  padding: 0.625rem;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: background-color 0.2s;
  cursor: pointer;
}

.add-cart-btn:hover {
  background-color: var(--stone-600);
}

.buy-now-btn {
  width: 100%;
  background: linear-gradient(135deg, #D4C5B0, #E8DCC8);
  color: #2d2a26;
  padding: 0.625rem;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(212, 197, 176, 0.4);
}

.buy-now-btn:hover {
  background: linear-gradient(135deg, #C5B5A0, #D4C5B0);
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(212, 197, 176, 0.5);
}

.product-info {
  padding: 1rem;
}

.product-name {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--stone-900);
  line-height: 1.4;
  min-height: 2.5rem;
  margin-bottom: 0.5rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-rating {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.rating-stars {
  display: flex;
  gap: 0.125rem;
}

.rating-stars i {
  font-size: 0.75rem;
  color: #f59e0b;
}

.rating-stars i.far {
  color: var(--stone-300);
}

.stars {
  display: flex;
  gap: 0.125rem;
}

.stars i {
  font-size: 0.75rem;
  color: var(--stone-900);
}

.stars i.empty {
  color: var(--stone-300);
}

.review-count {
  font-size: 0.75rem;
  color: var(--stone-500);
}

.product-price-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.product-price {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--stone-900);
}

.product-old-price {
  font-size: 0.875rem;
  color: var(--stone-400);
  text-decoration: line-through;
}

.product-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.quantity-selector {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
}

.qty-btn {
  width: 2.5rem;
  height: 2.5rem;
  border: 2px solid #a0c75f;
  background: white;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  color: #a0c75f;
}

.qty-btn:hover {
  background: #a0c75f;
  color: white;
  transform: scale(1.1);
}

.qty-btn i {
  font-size: 0.875rem;
}

.qty-input {
  width: 4rem;
  height: 2.5rem;
  text-align: center;
  border: 2px solid #a0c75f;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  color: #333;
  background: white;
}

.stock-badge {
  display: inline-block;
  background-color: var(--stone-100);
  color: var(--stone-900);
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-full);
  font-size: 0.75rem;
  font-weight: 600;
}

/* ==========================================
   BUTTONS
   ========================================== */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 2rem;
  font-size: 1rem;
  font-weight: 500;
  border-radius: var(--radius-md);
  transition: all 0.2s;
}

.btn-primary {
  background-color: var(--stone-900);
  color: var(--white);
}

.btn-primary:hover {
  background-color: var(--stone-600);
}

.btn-outline {
  border: 1px solid var(--stone-300);
  color: var(--stone-900);
}

.btn-outline:hover {
  background-color: var(--stone-100);
}

.btn-white {
  background-color: white;
  color: var(--stone-900);
  border: 2px solid white;
}

.btn-white:hover {
  background-color: transparent;
  color: white;
}

/* ==========================================
   FOOTER
   ========================================== */

.footer {
  background-color: var(--stone-50);
  border-top: 1px solid var(--stone-200);
  padding: 3rem 0 1.5rem;
}

.footer-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

@media (min-width: 640px) {
  .footer-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .footer-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.footer-col h3 {
  font-size: 1.125rem;
  color: var(--stone-900);
  margin-bottom: 1rem;
}

.footer-col p {
  font-size: 0.875rem;
  color: var(--stone-600);
  line-height: 1.75;
  margin-bottom: 1rem;
}

.footer-col ul {
  list-style: none;
}

.footer-col ul li {
  margin-bottom: 0.5rem;
}

.footer-col ul li a {
  font-size: 0.875rem;
  color: var(--stone-600);
  transition: color 0.2s;
}

.footer-col ul li a:hover {
  color: var(--stone-900);
}

.social-links {
  display: flex;
  gap: 0.75rem;
}

.social-btn {
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--stone-300);
  border-radius: var(--radius-full);
  color: var(--stone-600);
  transition: all 0.2s;
}

.social-btn:hover {
  color: var(--stone-900);
  border-color: var(--stone-900);
}

.footer-bottom {
  padding-top: 2rem;
  border-top: 1px solid var(--stone-200);
  text-align: center;
}

.footer-bottom p {
  font-size: 0.875rem;
  color: var(--stone-500);
}

/* ==========================================
   UTILITIES
   ========================================== */

.text-center {
  text-align: center;
}

.mt-3 {
  margin-top: 3rem;
}

@media (max-width: 1023px) {
  .hide-mobile { display: none; }
}

@media (min-width: 1024px) {
  .show-mobile { display: none; }
}
</style>
<!-- Checkout modal styles (homepage) -->
<style>
.hc-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:10000;display:none;align-items:center;justify-content:center}
.hc-modal-overlay.active{display:flex}
.hc-checkout-modal{background:#fff;border-radius:12px;max-width:680px;width:94%;max-height:90vh;overflow:auto;box-shadow:0 20px 40px rgba(0,0,0,0.2);}
.hc-modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px;border-bottom:1px solid #f1f1f1}
.hc-modal-title{font-size:1.25rem;font-weight:700}
.hc-modal-close{width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:transparent;border:none}
.hc-modal-body{padding:18px}
.hc-checkout-form{display:flex;flex-direction:column;gap:12px}
.hc-form-group{display:flex;flex-direction:column;gap:6px}
.hc-form-input,.hc-form-select,.hc-form-textarea{padding:10px;border:1px solid #e6e6e6;border-radius:8px}
.hc-summary{background:#fafafa;padding:12px;border-radius:8px;margin-top:8px}
.hc-submit-btn{background:#111;color:#fff;padding:12px;border-radius:8px;border:none;font-weight:700}
</style>
</head>
<body>

<!-- Announcement Bar -->
<div class="announcement-bar">
  <p>
    <i class="fas fa-star"></i>
    <span>Miễn phí vận chuyển cho đơn hàng từ trên 50.000₫ | Sale up to 40% - Limited time</span>
    <i class="fas fa-star"></i>
  </p>
</div>

<?php if (!empty($show_debug)): ?>
  <div style="position:fixed; right:12px; bottom:12px; background: rgba(0,0,0,0.85); color:#fff; padding:12px; border-radius:8px; z-index:9999; max-width:360px; font-size:12px; overflow:auto; max-height:40vh;">
    <strong>DEBUG SESSION</strong>
    <pre style="white-space:pre-wrap; color:#fff; margin-top:8px"><?php echo htmlspecialchars(var_export($_SESSION, true)); ?></pre>
  </div>
<?php endif; ?>

<!-- Header -->
<header class="header">
  <div class="container">
    <a href="/" class="brand-logo">
      <img src="images/logo.png?v=<?php echo time(); ?>" alt="Logo">
  
    </a>

    <nav class="nav">
      <a href="trangchu.php">
        <i class="fas fa-home"></i>
        <span>Trang Chủ</span>
      </a>
    
      <a href="san-pham.php">Sản Phẩm</a>
      <a href="don_hang_cua_toi.php">Theo Dõi Đơn Hàng</a>
      <a href="lienhe.php">Liên Hệ</a>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" onclick="toggleSearch()">
        <i class="fas fa-search"></i>
      </button>
      <a href="giohang.php" class="icon-btn">
        <i class="fas fa-shopping-bag"></i>
        <span class="cart-badge" style="display:none;">0</span>
      </a>
      <?php
        $isLogged = !empty($_SESSION['username']) || !empty($_SESSION['user_id']) || !empty($_SESSION['email']);
      ?>
      <?php if ($isLogged): ?>
        <a href="logout.php" class="icon-btn" title="Đăng xuất">
          <i class="fas fa-sign-in-alt"></i>
        </a>
      <?php else: ?>
        <a href="dangnhap.php" class="icon-btn" title="Đăng nhập">
          <i class="fas fa-user"></i>
        </a>
      <?php endif; ?>
      <button class="icon-btn show-mobile">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<!-- Banner Slider -->
<section class="banner-slider">
  <div class="slider-track">
    <!-- Slide 1 -->
    <div class="slide active">
      <div class="slide-bg" style="background-image: url('images/banner1.jpeg');"></div>
    </div>

    <!-- Slide 2 -->
    <div class="slide">
      <div class="slide-bg" style="background-image: url('images/banner2.jpeg');"></div>
    </div>

    <!-- Slide 3 -->
    <div class="slide">
      <div class="slide-bg" style="background-image: url('images/banner3.jpeg');"></div>
    </div>
  </div>

  <!-- Navigation -->
  <div class="slider-nav">
    <button class="slider-btn prev-btn" onclick="prevSlide()">
      <i class="fas fa-chevron-left"></i>
    </button>
    <button class="slider-btn next-btn" onclick="nextSlide()">
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>

  <!-- Dots -->
  <div class="slider-dots">
    <span class="dot active" onclick="goToSlide(0)"></span>
    <span class="dot" onclick="goToSlide(1)"></span>
    <span class="dot" onclick="goToSlide(2)"></span>
  </div>
</section>

<!-- Features Bar -->
<section class="features-bar">
  <div class="container">
    <div class="features-grid">
      <div class="feature-item">
        <i class="fas fa-truck feature-icon"></i>
        <div class="feature-text">
          <h3>Miễn Phí Ship</h3>
          <p>Đơn từ 1 triệu</p>
        </div>
      </div>
      <div class="feature-item">
        <i class="fas fa-sync-alt feature-icon"></i>
        <div class="feature-text">
          <h3>Đổi Trả 30 Ngày</h3>
          <p>Dễ dàng & nhanh chóng</p>
        </div>
      </div>
      <div class="feature-item">
        <i class="fas fa-shield-alt feature-icon"></i>
        <div class="feature-text">
          <h3>Thanh Toán An Toàn</h3>
          <p>Bảo mật 100%</p>
        </div>
      </div>
      <div class="feature-item">
        <i class="fas fa-award feature-icon"></i>
        <div class="feature-text">
          <h3>Chính Hãng 100%</h3>
          <p>Cam kết chất lượng</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Categories removed per request -->

<!-- PRODUCTS SECTION: Featured products (restored) -->
<?php
// Use the query we ran above ($stmt) to fetch featured products and render them.
$featured = [];
try {
    if (isset($stmt) && $stmt) {
        $featured = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    // if fetch fails, leave featured empty
    $featured = [];
}

// Lấy đánh giá cho các sản phẩm featured
$commentSummary = [];
if (!empty($featured)) {
    $productIds = array_column($featured, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    
    try {
        $commentStmt = $conn->prepare("
            SELECT san_pham_id, 
                   COUNT(*) as review_count,
                   AVG(rating) as avg_rating
            FROM danh_gia 
            WHERE san_pham_id IN ($placeholders)
            GROUP BY san_pham_id
        ");
        $commentStmt->execute($productIds);
        
        while ($row = $commentStmt->fetch(PDO::FETCH_ASSOC)) {
            $commentSummary[$row['san_pham_id']] = [
                'count' => (int)$row['review_count'],
                'avg' => round((float)$row['avg_rating'], 1)
            ];
        }
    } catch (Exception $e) {
        error_log("Error loading reviews: " . $e->getMessage());
    }
}
?>
<section class="section section-bg-white">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Sản Phẩm Nổi Bật</h2>
      <p class="section-subtitle">Sản phẩm mới nhất — cập nhật thường xuyên</p>
    </div>

    <div class="products-grid">
      <?php if (empty($featured)): ?>
        <p style="text-align:center; width:100%;">Hiện chưa có sản phẩm hiển thị. Vui lòng kiểm tra dữ liệu trong bảng <code>san_pham</code>.</p>
      <?php else: ?>
        <?php foreach ($featured as $p):
            $isLocked = (isset($p['trang_thai']) && $p['trang_thai'] == 0) || ((int)($p['so_luong'] ?? 0) <= 0);
            $productId = (int)($p['id'] ?? 0);
            $summary = isset($commentSummary[$productId]) ? $commentSummary[$productId] : null;
            $rating = $summary ? (float)$summary['avg'] : 0;
            $reviews = $summary ? (int)$summary['count'] : 0;
            $img = '';
            if (!empty($p['hinh_anh']) && file_exists(__DIR__ . '/uploads/' . $p['hinh_anh'])) {
              $img = 'uploads/' . $p['hinh_anh'];
            } elseif (!empty($p['hinh_anh']) && strpos($p['hinh_anh'], 'http') === 0) {
              $img = $p['hinh_anh'];
            } else {
              $img = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600';
            }
        ?>
        <article class="product-card<?php echo $isLocked ? ' product-locked' : ''; ?>" data-id="<?php echo $productId; ?>" 
           data-name="<?php echo htmlspecialchars($p['ten_san_pham'] ?? ''); ?>"
           data-price="<?php echo (float)($p['gia'] ?? 0); ?>"
           data-description="<?php echo htmlspecialchars($p['mo_ta'] ?? ''); ?>">
          <div class="product-image">
            <a href="chitiet_san_pham.php?id=<?php echo $productId; ?>" style="display: block;">
              <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($p['ten_san_pham'] ?? 'Sản phẩm'); ?>">
            </a>
            
            <!-- Quick Actions -->
            <div class="product-actions">
              <button class="action-btn" onclick="openHomeQuickView(<?php echo $productId; ?>)" title="Xem nhanh" <?php echo $isLocked ? 'disabled' : ''; ?>>
                <i class="far fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="product-info">
            <div class="product-name">
              <a href="chitiet_san_pham.php?id=<?php echo $productId; ?>" style="color: inherit; text-decoration: none;">
                <?php echo htmlspecialchars($p['ten_san_pham'] ?? ''); ?>
              </a>
            </div>
            <div class="product-price"><?php echo number_format((float)($p['gia'] ?? 0), 0, ',', '.'); ?>₫</div>
            
            <!-- Đánh giá sao -->
            <?php if ($reviews > 0): ?>
            <div class="product-rating">
              <div class="rating-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <?php if ($i <= floor($rating)): ?>
                    <i class="fas fa-star"></i>
                  <?php elseif ($i - 0.5 <= $rating): ?>
                    <i class="fas fa-star-half-alt"></i>
                  <?php else: ?>
                    <i class="far fa-star"></i>
                  <?php endif; ?>
                <?php endfor; ?>
              </div>
              <span class="review-count"><?php echo number_format($rating, 1); ?> (<?php echo $reviews; ?>)</span>
            </div>
            <?php endif; ?>
            
            <div class="product-meta">
              <span class="stock-badge"><?php echo $isLocked ? 'Hết hàng' : 'Còn hàng'; ?></span>
            </div>
            <!-- Quantity Selector -->
            <div class="quantity-selector" style="margin-top: 12px;">
              <button type="button" class="qty-btn minus" onclick="decreaseQty(this)" <?php echo $isLocked ? 'disabled' : ''; ?>>
                <i class="fas fa-minus"></i>
              </button>
              <input type="number" class="qty-input" value="1" min="1" max="99" readonly <?php echo $isLocked ? 'disabled' : ''; ?>>
              <button type="button" class="qty-btn plus" onclick="increaseQty(this)" <?php echo $isLocked ? 'disabled' : ''; ?>>
                <i class="fas fa-plus"></i>
              </button>
            </div>
            <div style="margin-top:8px;display:flex;gap:6px;flex-direction:column">
              <button type="button" class="add-cart-btn" <?php echo $isLocked ? 'disabled' : ''; ?>><i class="fas fa-shopping-cart"></i> <?php echo $isLocked ? 'Hết hàng' : 'Thêm vào giỏ'; ?></button>
              <button type="button" class="buy-now-btn" <?php echo $isLocked ? 'disabled' : ''; ?>><i class="fas fa-bolt"></i> <?php echo $isLocked ? 'Hết hàng' : 'Mua Ngay'; ?></button>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        
     
        <div class="social-links">
          <a href="https://facebook.com" target="_blank" class="social-btn" aria-label="Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="https://instagram.com" target="_blank" class="social-btn" aria-label="Instagram">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="https://twitter.com" target="_blank" class="social-btn" aria-label="Twitter">
            <i class="fab fa-twitter"></i>
          </a>
          <a href="https://youtube.com" target="_blank" class="social-btn" aria-label="YouTube">
            <i class="fab fa-youtube"></i>
          </a>
        </div>
      </div>

      

      <div class="footer-col">
        <h3>Hỗ Trợ</h3>
        <ul>
          <li><a href="#">Hướng Dẫn Mua Hàng</a></li>
          <li><a href="#">Chính Sách Đổi Trả</a></li>
          <li><a href="#">Phương Thức Thanh Toán</a></li>
          <li><a href="#">Vận Chuyển & Giao Hàng</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h3>Liên Hệ</h3>
        <ul>
          <li>
            <i class="fas fa-map-marker-alt" style="margin-right: 0.5rem;"></i>
            123 Ngọc Nguyên, Phường 5, Vĩnh Long
          </li>
          <li>
            <i class="fas fa-phone" style="margin-right: 0.5rem;"></i>
            <a href="tel:1900xxxx">1900 xxxx</a>
          </li>
          <li>
            <i class="fas fa-envelope" style="margin-right: 0.5rem;"></i>
            <a href="mailto:hello@shop.vn">hello@shop.vn</a>
          </li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© <?php echo date('Y'); ?>  Shop. All rights reserved. Made with <i class="fas fa-heart" style="color: var(--rose-500);"></i> in Vietnam</p>
    </div>
  </div>
</footer>

<!-- Homepage Checkout Modal -->
<div class="hc-modal-overlay" id="homeCheckoutModal">
  <div class="hc-checkout-modal" role="dialog" aria-modal="true" aria-label="Thanh toán">
    <div class="hc-modal-header">
      <div class="hc-modal-title">Thanh toán — My Shop</div>
      <button class="hc-modal-close" id="homeModalClose" aria-label="Đóng">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="hc-modal-body">
      <!-- Product info with quantity selector -->
      <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
          <img id="homeCheckoutProductImg" src="" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; display: none;">
          <div style="flex: 1;">
            <h4 id="homeCheckoutProductName" style="font-size: 1rem; font-weight: 600; margin-bottom: 8px;"></h4>
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="font-size: 0.875rem; color: #666;">Số lượng:</span>
              <div style="display: inline-flex; align-items: center; gap: 8px; background: white; padding: 4px 8px; border-radius: 6px; border: 1px solid #ddd;">
                <button type="button" onclick="adjustHomeCheckoutQty(-1)" style="background: #f0f0f0; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-weight: 600;">-</button>
                <span id="homeCheckoutQtyValue" style="min-width: 30px; text-align: center; font-weight: 600;">1</span>
                <button type="button" onclick="adjustHomeCheckoutQty(1)" style="background: #f0f0f0; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-weight: 600;">+</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <form id="homeCheckoutForm" class="hc-checkout-form">
        <div class="hc-form-group">
          <label>Họ và tên *</label>
          <input name="fullname" class="hc-form-input" required placeholder="Nguyễn Văn A" value="<?php echo isset($user_info['ten_dang_nhap']) ? htmlspecialchars($user_info['ten_dang_nhap']) : ''; ?>">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div class="hc-form-group"><label>Số điện thoại *</label><input name="phone" class="hc-form-input" required placeholder="0123456789" value="<?php echo isset($user_info['dien_thoai']) ? htmlspecialchars($user_info['dien_thoai']) : ''; ?>"></div>
          <div class="hc-form-group"><label>Email</label><input name="email" class="hc-form-input" placeholder="email@example.com" value="<?php echo isset($user_info['email']) ? htmlspecialchars($user_info['email']) : ''; ?>"></div>
        </div>

        <div class="hc-form-group"><label>Địa chỉ *</label><input name="address" class="hc-form-input" required placeholder="Số nhà, tên đường" value="<?php echo isset($user_info['dia_chi']) ? htmlspecialchars($user_info['dia_chi']) : ''; ?>"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div class="hc-form-group"><label>Thành phố *</label>
            <select name="city" class="hc-form-select" required>
              <option value="">Chọn thành phố</option>
              <option>Hồ Chí Minh</option>
              <option>Hà Nội</option>
              <option>Đà Nẵng</option>
              <option>Cần Thơ</option>
              <option>Vĩnh Long</option>
            </select>
          </div>
          <div class="hc-form-group"><label>Phương thức thanh toán</label>
            <select name="payment" class="hc-form-select" id="homePaymentSelect">
              <option value="cod">Thanh toán khi nhận hàng</option>
              <option value="bank">Chuyển khoản</option>
              <option value="qr">Quét mã QR</option>
            </select>
          </div>
        </div>

        <div class="hc-form-group"><label>Ghi chú</label><textarea name="note" class="hc-form-textarea" placeholder="Ghi chú đơn hàng (tùy chọn)"></textarea></div>

        <!-- Voucher Section -->
        <div class="hc-form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #dee2e6;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <label style="margin: 0;"><i class="fas fa-ticket-alt"></i> Mã giảm giá</label>
            <button type="button" onclick="showAvailableVouchers()" style="padding: 5px 12px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;">
              <i class="fas fa-tags"></i> Xem mã khả dụng
            </button>
          </div>
          <div style="display: flex; gap: 10px;">
            <input type="text" class="hc-form-input" id="voucherCodeTC" placeholder="Nhập mã voucher" style="flex: 1;">
            <button type="button" onclick="applyVoucherTC()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; white-space: nowrap;">
              <i class="fas fa-check"></i> Áp dụng
            </button>
          </div>
          <div id="voucherMessageTC" style="margin-top: 10px; padding: 8px; border-radius: 5px; display: none; font-size: 14px;"></div>
          <input type="hidden" name="ma_voucher" id="ma_voucherTC" value="">
          <input type="hidden" name="giam_gia" id="giam_giaTC" value="0">
        </div>

        <!-- Bank Transfer Info Section (hidden by default) -->
        <div id="homeBankInfoSection" style="display: none; margin-top: 1.5rem;">
          <div style="background: #f8fafc; border: 2px solid #3b82f6; border-radius: 12px; padding: 1.5rem; max-width: 420px; margin: 0 auto;">
            <h4 style="font-weight: 700; font-size: 1rem; margin-bottom: 1rem; color: #1e40af; text-align: center;">
              🏦 Thông tin chuyển khoản
            </h4>
            <div style="background: white; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
              <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Chủ tài khoản:</div>
                <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">Trương Thị Mỹ Phương</div>
              </div>
              <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Số tài khoản:</div>
                <div style="font-weight: 700; color: #1f2937; font-size: 1.25rem; letter-spacing: 0.05em;">0325048679</div>
              </div>
              <div>
                <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Ngân hàng:</div>
                <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">MB Bank</div>
              </div>
            </div>
            <p style="margin-top: 1rem; font-size: 0.8rem; color: #6b7280; text-align: center; font-style: italic;">
              ℹ️ Vui lòng chuyển khoản với nội dung: Tên + Số điện thoại
            </p>
          </div>
        </div>

        <!-- QR Code Section (hidden by default) -->
        <div id="homeQrCodeSection" style="display: none; margin-top: 1.5rem;">
          <div style="background: #f8fafc; border: 2px solid #3b82f6; border-radius: 12px; padding: 1.5rem; max-width: 420px; margin: 0 auto;">
            <h4 style="font-weight: 700; font-size: 1rem; margin-bottom: 1rem; color: #1e40af; text-align: center;">
              📱 Quét mã QR để thanh toán
            </h4>
            <div style="background: white; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
              <img id="homeQrCodeImage" src="" alt="QR Code Thanh Toán" style="max-width: 280px; width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 8px;">
            </div>
          </div>
        </div>

        <div class="hc-summary">
          <div style="display:flex;justify-content:space-between"><span>Tạm tính</span><span id="homeModalSubtotal">0₫</span></div>
          <div id="discountRowTC" style="display:none;justify-content:space-between;color:#28a745;"><span><i class="fas fa-tag"></i> Giảm giá</span><span id="homeModalDiscount">0₫</span></div>
          <div style="display:flex;justify-content:space-between"><span>Phí vận chuyển</span><span id="homeModalShipping">30.000₫</span></div>
          <div style="display:flex;justify-content:space-between;font-weight:700;margin-top:8px"><span>Tổng cộng</span><span id="homeModalTotal">0₫</span></div>
        </div>

        <button type="submit" class="hc-submit-btn">Đặt hàng ngay</button>
      </form>
    </div>
  </div>
</div>

<script>
'use strict';

// ===== QUANTITY FUNCTIONS =====
function increaseQty(btn) {
  const input = btn.parentElement.querySelector('.qty-input');
  const currentValue = parseInt(input.value) || 1;
  const maxValue = parseInt(input.max) || 99;
  if (currentValue < maxValue) {
    input.value = currentValue + 1;
    // Update tempSingleItem quantity if exists
    if (window.tempSingleItem) {
      window.tempSingleItem.quantity = currentValue + 1;
    }
    // Update modal summary
    if (typeof updateHomeModalSummary === 'function') {
      updateHomeModalSummary();
    }
  }
}

function decreaseQty(btn) {
  const input = btn.parentElement.querySelector('.qty-input');
  const currentValue = parseInt(input.value) || 1;
  const minValue = parseInt(input.min) || 1;
  if (currentValue > minValue) {
    input.value = currentValue - 1;
    // Update tempSingleItem quantity if exists
    if (window.tempSingleItem) {
      window.tempSingleItem.quantity = currentValue - 1;
    }
    // Update modal summary
    if (typeof updateHomeModalSummary === 'function') {
      updateHomeModalSummary();
    }
  }
}

// ==========================================
// SEARCH FUNCTIONALITY (Global scope)
// ==========================================
window.toggleSearch = function() {
  const searchTerm = prompt('🔍 Tìm kiếm sản phẩm:');
  if (searchTerm && searchTerm.trim()) {
    window.location.href = 'trangchu.php?search=' + encodeURIComponent(searchTerm.trim());
  }
}

document.addEventListener('DOMContentLoaded', function() {

// ==========================================
// HIGHLIGHT ACTIVE NAV LINK
// ==========================================
(function() {
  const currentPath = window.location.pathname;
  const navLinks = document.querySelectorAll('.nav a');
  
  navLinks.forEach(link => {
    const linkPath = new URL(link.href).pathname;
    
    // Check if current page matches
    if (currentPath === linkPath || 
        (currentPath === '/' && linkPath === '/') ||
        (currentPath.includes('trangchu.php') && linkPath === '/') ||
        (currentPath.includes('sale.php') && link.href.includes('sale.php')) ||
        (currentPath.includes('san-pham.php') && link.href.includes('san-pham.php')) ||
        (currentPath.includes('don_hang_cua_toi.php') && link.href.includes('don_hang_cua_toi.php')) ||
        (currentPath.includes('lienhe.php') && link.href.includes('lienhe.php'))) {
      link.classList.add('active');
    }
  });
})();

// ==========================================
// BANNER SLIDER
// ==========================================
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');
const totalSlides = slides.length;
let slideInterval;

function showSlide(index) {
  // Remove active class from all slides and dots
  slides.forEach(slide => {
    slide.classList.remove('active', 'prev');
  });
  dots.forEach(dot => {
    dot.classList.remove('active');
  });

  // Set prev class for smooth transition
  if (slides[currentSlide]) {
    slides[currentSlide].classList.add('prev');
  }

  // Update current slide
  currentSlide = index;
  if (currentSlide >= totalSlides) currentSlide = 0;
  if (currentSlide < 0) currentSlide = totalSlides - 1;

  // Add active class to current slide and dot
  slides[currentSlide].classList.add('active');
  dots[currentSlide].classList.add('active');
}

window.nextSlide = function() {
  showSlide(currentSlide + 1);
  resetSlideInterval();
}

window.prevSlide = function() {
  showSlide(currentSlide - 1);
  resetSlideInterval();
}

window.goToSlide = function(index) {
  showSlide(index);
  resetSlideInterval();
}

function resetSlideInterval() {
  clearInterval(slideInterval);
  slideInterval = setInterval(() => {
    showSlide(currentSlide + 1);
  }, 5000);
}

// Auto play
slideInterval = setInterval(() => {
  showSlide(currentSlide + 1);
}, 5000);

// Pause on hover
const slider = document.querySelector('.banner-slider');
if (slider) {
  slider.addEventListener('mouseenter', () => {
    clearInterval(slideInterval);
  });

  slider.addEventListener('mouseleave', () => {
    resetSlideInterval();
  });
}

// ==========================================
// GENDER FILTER
// ==========================================
function filterGender(gender, btn) {
  // Remove active class from all buttons
  document.querySelectorAll('.filter-btn').forEach(button => {
    button.classList.remove('active');
  });
  
  // Add active class to clicked button
  btn.classList.add('active');
  
  // Navigate to filtered page
  const url = gender === 'all' ? 'san-pham.php' : `san-pham.php?gender=${gender}`;
  window.location.href = url;
}

// Favorites feature removed per request

// ==========================================
// IMAGE ERROR HANDLING
// ==========================================
function handleImageError(img) {
  img.onerror = null; // Prevent infinite loop
  img.src = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600';
  img.alt = 'Placeholder image';
}

// ==========================================
// SMOOTH SCROLL
// ==========================================
function smoothScroll(target) {
  const element = document.querySelector(target);
  if (element) {
    element.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }
}

// ==========================================
// INIT ON DOM READY
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
  // Image error handling
  const productImages = document.querySelectorAll('.product-image img, .category-image img');
  productImages.forEach(img => {
    if (!img.complete) {
      img.addEventListener('error', function() {
        handleImageError(this);
      });
    }
  });
  
  // Favorites removed — no state to load
  
  // Add scroll to top button
  const scrollBtn = document.createElement('button');
  scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
  scrollBtn.className = 'scroll-to-top';
  scrollBtn.style.cssText = `
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 3rem;
    height: 3rem;
    background: var(--stone-900);
    color: var(--white);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999;
    transition: all 0.3s;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
  `;
  
  scrollBtn.addEventListener('click', () => {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
  
  document.body.appendChild(scrollBtn);
  
  // Show/hide scroll button
  window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
      scrollBtn.style.display = 'flex';
    } else {
      scrollBtn.style.display = 'none';
    }
  });
  
  // Add hover effect to scroll button
  scrollBtn.addEventListener('mouseenter', () => {
    scrollBtn.style.transform = 'translateY(-5px)';
    scrollBtn.style.boxShadow = '0 10px 15px -3px rgb(0 0 0 / 0.1)';
  });
  
  scrollBtn.addEventListener('mouseleave', () => {
    scrollBtn.style.transform = 'translateY(0)';
    scrollBtn.style.boxShadow = '0 4px 6px -1px rgb(0 0 0 / 0.1)';
  });
  
  console.log('My Shop with Banner Slider initialized successfully! 🎉');
  // user menu toggle for touch devices
  var userToggles = document.querySelectorAll('.user-menu-toggle');
  userToggles.forEach(function(btn){
    btn.addEventListener('click', function(e){
      var wrapper = btn.closest('.user-menu-wrapper');
      if (!wrapper) return;
      wrapper.classList.toggle('open');
      var expanded = wrapper.classList.contains('open');
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      // close when clicking outside
      if (expanded) {
        setTimeout(function(){
          document.addEventListener('click', function handler(ev){
            if (!wrapper.contains(ev.target)) {
              wrapper.classList.remove('open');
              btn.setAttribute('aria-expanded','false');
              document.removeEventListener('click', handler);
            }
          });
        }, 0);
      }
    });
  });
});

// ==========================================
// GLOBAL FORMAT PRICE FUNCTION
// ==========================================
function formatPrice(price) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
}

// ==========================================
// ADD TO CART (homepage) — show toast, update badge and open inline checkout
// ==========================================
(function(){
  'use strict';

  const CART_KEY = 'myphuongshop_cart_items';
  const CART_COUNT_KEY = 'myshop_cart_count';
  // temporary single-item order when buying directly from homepage
  let tempSingleItem = null;
  window.tempSingleItem = tempSingleItem;

  function getCartItems() {
    try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
    catch (e) { return []; }
  }

  function saveCartItems(items) {
    try { localStorage.setItem(CART_KEY, JSON.stringify(items)); }
    catch (e) { console.error('saveCartItems error', e); }
    const count = items.reduce((s,i) => s + (i.quantity||0), 0);
    try { localStorage.setItem(CART_COUNT_KEY, String(count)); } catch(e){}
  }

  // Update or create the cart badge in the header
  function updateCartUI() {
    const cartLink = document.querySelector('a.icon-btn[href="giohang.php"]') || document.querySelector('a.icon-btn');
    if (!cartLink) return;
    let badge = cartLink.querySelector('.cart-badge');
    const items = getCartItems();
    const count = items.reduce((s,i) => s + (i.quantity||0), 0);

    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'cart-badge';
      cartLink.appendChild(badge);
    }
    badge.textContent = String(count > 99 ? '99+' : count);
    badge.style.display = count > 0 ? 'flex' : 'none';
  }

  // Update product display in checkout modal
  function updateHomeCheckoutProductDisplay() {
    const imgEl = document.getElementById('homeCheckoutProductImg');
    const nameEl = document.getElementById('homeCheckoutProductName');
    const qtyEl = document.getElementById('homeCheckoutQtyValue');
    
    if (tempSingleItem) {
      if (imgEl) {
        imgEl.src = tempSingleItem.image;
        imgEl.style.display = 'block';
      }
      if (nameEl) nameEl.textContent = tempSingleItem.name;
      if (qtyEl) qtyEl.textContent = tempSingleItem.quantity;
    } else {
      const items = getCartItems();
      const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
      if (imgEl) imgEl.style.display = 'none';
      if (nameEl) nameEl.textContent = `Giỏ hàng (${items.length} sản phẩm)`;
      if (qtyEl) qtyEl.textContent = totalItems;
    }
  }

  // Adjust quantity in checkout modal
  window.adjustHomeCheckoutQty = function(delta) {
    const qtyEl = document.getElementById('homeCheckoutQtyValue');
    if (!qtyEl) return;
    
    let currentQty = parseInt(qtyEl.textContent) || 1;
    currentQty = Math.max(1, currentQty + delta);
    
    qtyEl.textContent = currentQty;
    
    // Update tempSingleItem if in single buy mode
    if (tempSingleItem) {
      tempSingleItem.quantity = currentQty;
      window.tempSingleItem = tempSingleItem;
    }
    
    updateHomeModalSummary();
  };

  // Update QR payment info for home page
  function updateHomeQRInfo(amount, content) {
    const amountNum = Math.round(amount);
    const orderCode = content || 'DH' + Date.now().toString().slice(-8);
    
    // Generate VietQR URL
    const accountNo = '0325048679';
    const accountName = 'TRUONG%20THI%20MY%20PHUONG';
    const bankCode = 'MB';
    const description = encodeURIComponent(orderCode);
    const qrUrl = `https://img.vietqr.io/image/${bankCode}-${accountNo}-compact2.png?amount=${amountNum}&addInfo=${description}&accountName=${accountName}`;
    
    // Update QR image
    const qrImage = document.getElementById('homeQrCodeImage');
    if (qrImage) qrImage.src = qrUrl;
  }

  function calculateTotals() {
    const items = getCartItems();
    const subtotal = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const shipping = subtotal < 50000 ? 5000 : 0;
    const total = subtotal + shipping;
    return { subtotal, shipping, total };
  }

  function updateHomeModalSummary() {
    let subtotal, shipping, total;
    
    // If Buy Now mode (tempSingleItem exists), calculate from single item
    if (tempSingleItem) {
      subtotal = tempSingleItem.price * tempSingleItem.quantity;
      shipping = subtotal < 50000 ? 5000 : 0;
    } else {
      // Otherwise, calculate from cart
      const totals = calculateTotals();
      subtotal = totals.subtotal;
      shipping = totals.shipping;
    }
    
    // Calculate total with voucher discount
    const discount = currentDiscountTC || 0;
    total = subtotal + shipping - discount;
    
    const elSub = document.getElementById('homeModalSubtotal');
    const elShip = document.getElementById('homeModalShipping');
    const elDiscount = document.getElementById('homeModalDiscount');
    const elTotal = document.getElementById('homeModalTotal');
    const discountRow = document.getElementById('discountRowTC');
    
    if (elSub) elSub.textContent = formatPrice(subtotal);
    if (elShip) elShip.textContent = shipping === 0 ? 'Miễn phí' : formatPrice(shipping);
    if (elTotal) elTotal.textContent = formatPrice(total);
    
    // Show/hide discount row
    if (discount > 0 && discountRow && elDiscount) {
      discountRow.style.display = 'flex';
      elDiscount.textContent = '-' + formatPrice(discount);
    } else if (discountRow) {
      discountRow.style.display = 'none';
    }
  }

  function openHomeCheckout() {
    updateHomeModalSummary();
    updateHomeCheckoutProductDisplay();
    const modal = document.getElementById('homeCheckoutModal');
    if (!modal) {
      console.error('homeCheckoutModal not found!');
      return;
    }
    
    // Tự động điền thông tin khách hàng đã lưu
    loadCustomerInfo();
    
    modal.classList.add('active');
    const first = modal.querySelector('input[name="fullname"]');
    if (first) first.focus();
  }

  function loadCustomerInfo() {
    try {
      const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
      if (!userId) return;
      
      const form = document.getElementById('homeCheckoutForm');
      if (!form) return;
      
      // Lấy thông tin đã lưu từ localStorage
      const savedInfo = localStorage.getItem('customer_info_' + userId);
      if (savedInfo) {
        const info = JSON.parse(savedInfo);
        if (info.fullname && form.fullname) form.fullname.value = info.fullname;
        if (info.phone && form.phone) form.phone.value = info.phone;
        if (info.email && form.email) form.email.value = info.email;
        if (info.address && form.address) form.address.value = info.address;
        if (info.city && form.city) form.city.value = info.city;
      } else {
        // Nếu chưa có thông tin lưu, điền sẵn tên và email từ tài khoản
        <?php if (isset($_SESSION['user_id'])): ?>
        <?php
          $stmt = $conn->prepare("SELECT ten_dang_nhap, ho_ten, email FROM nguoi_dung WHERE id = ?");
          $stmt->execute([$_SESSION['user_id']]);
          $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        if (form.fullname && !form.fullname.value) {
          form.fullname.value = <?php echo json_encode($user_info['ten_dang_nhap'] ?? ''); ?>;
        }
        if (form.email && !form.email.value) {
          form.email.value = <?php echo json_encode($user_info['email'] ?? ''); ?>;
        }
        <?php endif; ?>
      }
    } catch (e) {
      console.error('Lỗi khi load thông tin:', e);
    }
  }

  function saveCustomerInfo(data) {
    try {
      const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
      if (!userId) return;
      
      const info = {
        fullname: data.customer?.fullname || '',
        phone: data.customer?.phone || '',
        email: data.customer?.email || '',
        address: data.customer?.address || '',
        city: data.customer?.city || ''
      };
      localStorage.setItem('customer_info_' + userId, JSON.stringify(info));
    } catch (e) {
      console.error('Lỗi khi lưu thông tin:', e);
    }
  }

  function closeHomeCheckout() {
    const modal = document.getElementById('homeCheckoutModal');
    if (!modal) return;
    modal.classList.remove('active');
    // Reset voucher
    if (typeof resetVoucherTC === 'function') {
      resetVoucherTC();
    }
  }

  function showAddToast(text) {
    const t = document.createElement('div');
    t.textContent = text || 'Đã thêm vào giỏ hàng';
    t.style.cssText = 'position:fixed; right:1.25rem; bottom:4.5rem; background:rgba(28,25,23,0.95); color:#fff; padding:0.6rem 0.9rem; border-radius:8px; z-index:10000; box-shadow:0 8px 24px rgba(0,0,0,0.15); font-size:0.95rem; opacity:0; transform:translateY(8px); transition:all 240ms ease';
    document.body.appendChild(t);
    void t.offsetWidth;
    t.style.opacity = '1';
    t.style.transform = 'translateY(0)';
    setTimeout(() => {
      t.style.opacity = '0';
      t.style.transform = 'translateY(8px)';
      setTimeout(() => t.remove(), 240);
    }, 1500);
  }

  // Show cart notification with product details
  function showCartNotification(productName, qty) {
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 6rem;
      right: 2rem;
      background: linear-gradient(135deg, #2ecc71, #27ae60);
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 1rem;
      animation: slideInRight 0.3s ease;
      max-width: 400px;
    `;
    
    notification.innerHTML = `
      <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
      <div style="flex: 1;">
        <div style="font-weight: 700; margin-bottom: 0.25rem;">Đã thêm vào giỏ hàng!</div>
        <div style="font-size: 0.875rem; opacity: 0.95;">${productName} (x${qty})</div>
        <a href="giohang.php" style="color: white; text-decoration: underline; font-size: 0.875rem; margin-top: 0.25rem; display: inline-block;">Xem giỏ hàng →</a>
      </div>
      <button onclick="this.parentElement.remove()" style="background: transparent; border: none; color: white; cursor: pointer; font-size: 1.25rem; padding: 0.25rem;">
        <i class="fas fa-times"></i>
      </button>
    `;
    
    document.body.appendChild(notification);
    
    // Add animation styles if not exist
    if (!document.getElementById('cart-animations')) {
      const style = document.createElement('style');
      style.id = 'cart-animations';
      style.textContent = `
        @keyframes slideInRight {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        @keyframes slideOutRight {
          from {
            transform: translateX(0);
            opacity: 1;
          }
          to {
            transform: translateX(100%);
            opacity: 0;
          }
        }
      `;
      document.head.appendChild(style);
    }
    
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    }, 4000);
  }

  // Close modal when clicking close button or overlay, or pressing Esc
  (function(){
    var btnClose = document.getElementById('homeModalClose');
    if (btnClose) btnClose.addEventListener('click', closeHomeCheckout);
    var overlay = document.getElementById('homeCheckoutModal');
    if (overlay) {
      // click on backdrop (overlay itself) closes
      overlay.addEventListener('click', function(e){
        if (e.target === overlay) closeHomeCheckout();
      });
    }
    // allow Esc to close modal
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' || e.key === 'Esc') closeHomeCheckout(); });
  })();

  // Add payment method display handler for home checkout modal
  (function() {
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        const modal = document.getElementById('homeCheckoutModal');
        if (modal && modal.classList.contains('active')) {
          // Hide both sections initially
          const qrSection = document.getElementById('homeQrCodeSection');
          const bankSection = document.getElementById('homeBankInfoSection');
          if (qrSection) qrSection.style.display = 'none';
          if (bankSection) bankSection.style.display = 'none';
          
          const paymentSelect = document.getElementById('homePaymentSelect');
          if (paymentSelect && !paymentSelect.dataset.listenerAdded) {
            paymentSelect.dataset.listenerAdded = 'true';
            paymentSelect.addEventListener('change', function() {
              const qrSection = document.getElementById('homeQrCodeSection');
              const bankSection = document.getElementById('homeBankInfoSection');
              
              // Hide both first
              if (qrSection) qrSection.style.display = 'none';
              if (bankSection) bankSection.style.display = 'none';
              
              // Show appropriate section
              if (this.value === 'qr' && qrSection) {
                // Update QR payment info
                const items = tempSingleItem ? [tempSingleItem] : getCartItems();
                const subtotal = items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
                const shipping = subtotal < 50000 ? 5000 : 0;
                const total = subtotal + shipping;
                const orderCode = 'DH' + Date.now().toString().slice(-8);
                updateHomeQRInfo(total, orderCode);
                
                qrSection.style.display = 'block';
                qrSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
              } else if (this.value === 'bank' && bankSection) {
                bankSection.style.display = 'block';
                bankSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
              }
            });
          }
        }
      });
    });
    
    const modalElement = document.getElementById('homeCheckoutModal');
    if (modalElement) {
      observer.observe(modalElement, { attributes: true, attributeFilter: ['class'] });
    }
  })();

  // Handle form submission
  document.addEventListener('submit', function(e){
    const form = e.target && e.target.id === 'homeCheckoutForm' ? e.target : null;
    if (!form) return;
    e.preventDefault();
    
    console.log('Form submitted!');
    
    const fd = new FormData(form);
    
    // If a single-item buy was initiated from the homepage, use that item
    const itemsToOrder = tempSingleItem ? [tempSingleItem] : getCartItems();
    if (itemsToOrder.length === 0) {
      alert('Giỏ hàng đang trống. Vui lòng thêm sản phẩm.');
      return;
    }

    // Calculate totals based on the items being ordered (tempSingleItem or cart)
    let subtotal, shipping, discount, total;
    if (tempSingleItem) {
      // For Buy Now: calculate from single item
      subtotal = tempSingleItem.price * tempSingleItem.quantity;
      shipping = subtotal < 50000 ? 5000 : 0;
      discount = parseInt(document.getElementById('giam_giaTC')?.value || 0);
      total = subtotal + shipping - discount;
    } else {
      // For cart checkout: calculate from all items
      const totals = calculateTotals();
      subtotal = totals.subtotal;
      shipping = totals.shipping;
      discount = parseInt(document.getElementById('giam_giaTC')?.value || 0);
      total = subtotal + shipping - discount;
    }

    const voucherCode = document.getElementById('ma_voucherTC')?.value || '';

    const order = {
      items: itemsToOrder,
      customer: {
        fullname: fd.get('fullname'),
        phone: fd.get('phone'),
        email: fd.get('email'),
        address: fd.get('address'),
        city: fd.get('city'),
        payment: fd.get('payment'),
        note: fd.get('note')
      },
      voucher: {
        code: voucherCode,
        discount: discount
      },
      totals: { subtotal, shipping, discount, total },
      timestamp: new Date().toISOString()
    };

    console.log('Order data:', order);
    
    // Gửi đơn hàng lên server
    fetch('xu_ly_don_hang.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(order)
    })
    .then(response => {
      console.log('Response status:', response.status);
      return response.json();
    })
    .then(result => {
      console.log('Result:', result);
      if (result.success) {
        // Lưu thông tin khách hàng để dùng cho lần sau
        saveCustomerInfo(order);
        alert(`✅ Đặt hàng thành công!\nMã đơn hàng: ${result.orderCode}\nTổng: ${formatPrice(total)}\n\nChúng tôi sẽ liên hệ với bạn sớm nhất!`);
        
        // If this was a single-item instant buy, do not modify cart storage; otherwise clear cart
        if (!tempSingleItem) {
          saveCartItems([]);
          updateCartUI();
        }
        // reset tempSingleItem and close
        tempSingleItem = null;
        closeHomeCheckout();
        form.reset();
      } else {
        alert('❌ Lỗi: ' + result.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('❌ Có lỗi xảy ra khi đặt hàng: ' + error.message);
    });
  });

  // Main click handler for add-to-cart
  document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.add-cart-btn');
    if (!btn) return;
    e.preventDefault();

    // Kiểm tra đăng nhập
    <?php if (!isset($_SESSION['user_id'])): ?>
    if (confirm('Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng. Đăng nhập ngay?')) {
      window.location.href = 'dangnhap.php';
    }
    return;
    <?php endif; ?>

    const card = btn.closest('.product-card');
    if (!card) return;
    const id = card.dataset.id || card.getAttribute('data-id');
    const nameEl = card.querySelector('.product-name');
    const priceEl = card.querySelector('.product-price');
    const imgEl = card.querySelector('.product-image img');
    const categoryEl = card.querySelector('.product-category');
    const qtyInput = card.querySelector('.qty-input');

    const name = nameEl ? nameEl.textContent.trim() : '';
    const priceRaw = priceEl ? priceEl.textContent.trim() : '0';
    const price = parseInt((priceRaw || '').replace(/[^0-9]/g,'')) || 0;
    const image = imgEl ? imgEl.src : '';
    const category = categoryEl ? categoryEl.textContent.trim() : '';
    const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    // Add to cart
    const items = getCartItems();
    const existingIndex = items.findIndex(item => item.id === Number(id));
    
    if (existingIndex > -1) {
      // Item exists, increase quantity
      items[existingIndex].quantity += quantity;
    } else {
      // Add new item
      items.push({
        id: Number(id),
        name: name,
        price: price,
        image: image,
        quantity: quantity,
        category: category || ''
      });
    }
    
    saveCartItems(items);
    updateCartUI();
    showCartNotification(name, quantity);
    
    // Reset quantity to 1
    if (qtyInput) qtyInput.value = 1;
  });

  // Handler for "Buy Now" button - redirect to thanhtoan.php
  document.querySelectorAll('.buy-now-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      // Kiểm tra đăng nhập
      <?php if (!isset($_SESSION['user_id'])): ?>
      if (confirm('Bạn cần đăng nhập để mua hàng. Đăng nhập ngay?')) {
        window.location.href = 'dangnhap.php';
      }
      return;
      <?php endif; ?>
      const card = this.closest('.product-card');
      if (!card) {
        alert('Không tìm thấy sản phẩm!');
        return;
      }
      const id = card.dataset.id || card.getAttribute('data-id');
      const name = card.dataset.name || card.querySelector('.product-name')?.textContent.trim() || '';
      const priceRaw = card.querySelector('.product-price')?.textContent.trim() || '0';
      const price = parseInt(priceRaw.replace(/[^0-9]/g,'')) || 0;
      const image = card.querySelector('.product-image img')?.src || '';
      const category = card.dataset.category || '';
      const qtyInput = card.querySelector('.qty-input');
      const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
      // Thêm vào giỏ hàng
      const cartItems = getCartItems();
      const existingIndex = cartItems.findIndex(item => item.id === Number(id));
      if (existingIndex >= 0) {
        cartItems[existingIndex].quantity = Math.min(99, cartItems[existingIndex].quantity + quantity);
      } else {
        cartItems.push({
          id: Number(id),
          name: name,
          price: price,
          image: image,
          category: category,
          quantity: quantity
        });
      }
      saveCartItems(cartItems);
      // Chuyển đến trang giỏ hàng
      window.location.href = 'giohang.php';
    });
  });

  // initialize badge
  try { updateCartUI(); } catch(e){}

  // ==========================================
  // QUICK VIEW MODAL
  // ==========================================
  (function() {
    'use strict';
    
    window.openHomeQuickView = function(productId) {
      console.log('openHomeQuickView called with productId:', productId);
      const card = document.querySelector(`.product-card[data-id="${productId}"]`);
      if (!card) {
        console.error('Product card not found:', productId);
        return;
      }
      
      const name = card.dataset.name || 'Sản phẩm';
      const price = parseFloat(card.dataset.price || 0);
      const description = card.dataset.description || 'Sản phẩm chất lượng cao, thiết kế đẹp mắt và sang trọng.';
      const img = card.querySelector('img')?.src || '';
      
      console.log('Product data:', { name, price, description, img });
      
      const hqvImg = document.getElementById('hqvImg');
      const hqvName = document.getElementById('hqvName');
      const hqvPrice = document.getElementById('hqvPrice');
      const hqvDesc = document.getElementById('hqvDesc');
      
      if (hqvImg) hqvImg.src = img;
      if (hqvName) hqvName.textContent = name;
      if (hqvPrice) hqvPrice.textContent = formatPrice(price);
      if (hqvDesc) hqvDesc.textContent = description;
      
      console.log('Description set to:', description);
      
      const modal = document.getElementById('homeQuickViewModal');
      if (modal) modal.classList.add('active');
      
      // Store current product ID for later use
      window.currentQuickViewProductId = productId;
    };

    window.closeHomeQuickView = function() {
      const modal = document.getElementById('homeQuickViewModal');
      if (modal) modal.classList.remove('active');
    };

    // Close on overlay click
    const qvModal = document.getElementById('homeQuickViewModal');
    if (qvModal) {
      qvModal.addEventListener('click', function(e) {
        if (e.target === this) closeHomeQuickView();
      });
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeHomeQuickView();
    });
  })();

})();

// ==========================================
// ANALYTICS (Optional)
// ==========================================
window.addEventListener('load', function() {
  // Track page view
  console.log('Page loaded:', {
    url: window.location.href,
    title: document.title,
    timestamp: new Date().toISOString()
  });
});

// Voucher functions for trangchu.php
let originalTotalTC = 0;
let currentDiscountTC = 0;

window.applyVoucherTC = function() {
    const code = document.getElementById('voucherCodeTC').value.trim().toUpperCase();
    
    if (!code) {
        showVoucherMessageTC('Vui lòng nhập mã voucher', 'error');
        return;
    }
    
    const subtotalEl = document.getElementById('homeModalSubtotal');
    if (!subtotalEl) {
        console.error('homeModalSubtotal element not found');
        showVoucherMessageTC('Lỗi: Không tìm thấy element tổng tiền', 'error');
        return;
    }
    
    const subtotalText = subtotalEl.textContent;
    originalTotalTC = parseInt(subtotalText.replace(/[^\d]/g, '')) || 0;
    
    console.log('Applying voucher:', code, 'Original total:', originalTotalTC);
    
    fetch('check_voucher.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ma_voucher=' + encodeURIComponent(code)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
        }
    })
    .then(res => {
        console.log('Voucher response:', res);
        if (res.success) {
            const voucher = res.voucher;
            let discount = 0;
            
            if (voucher.loai_giam === 'phan_tram') {
                discount = Math.floor(originalTotalTC * voucher.gia_tri_giam / 100);
            } else {
                discount = voucher.gia_tri_giam;
            }
            
            if (discount > originalTotalTC) {
                discount = originalTotalTC;
            }
            
            currentDiscountTC = discount;
            
            console.log('Discount calculated:', discount);
            
            document.getElementById('ma_voucherTC').value = code;
            document.getElementById('giam_giaTC').value = discount;
            
            document.getElementById('homeModalDiscount').textContent = '-' + formatPrice(discount);
            document.getElementById('discountRowTC').style.display = 'flex';
            
            updateTotalTC();
            
            showVoucherMessageTC('✓ ' + res.message, 'success');
        } else {
            showVoucherMessageTC('✗ ' + res.message, 'error');
            resetVoucherTC();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showVoucherMessageTC('✗ Có lỗi xảy ra: ' + error.message, 'error');
    });
}

window.resetVoucherTC = function() {
    currentDiscountTC = 0;
    document.getElementById('ma_voucherTC').value = '';
    document.getElementById('giam_giaTC').value = 0;
    document.getElementById('discountRowTC').style.display = 'none';
    updateTotalTC();
}

window.updateTotalTC = function() {
    const subtotalText = document.getElementById('homeModalSubtotal')?.textContent || '0';
    const shippingText = document.getElementById('homeModalShipping')?.textContent || '0';
    
    const subtotal = parseInt(subtotalText.replace(/[^\d]/g, '')) || 0;
    const shipping = parseInt(shippingText.replace(/[^\d]/g, '')) || 0;
    
    const total = subtotal - currentDiscountTC + shipping;
    
    if (document.getElementById('homeModalTotal')) {
        document.getElementById('homeModalTotal').textContent = formatPrice(total);
    }
}

window.showVoucherMessageTC = function(message, type) {
    const msgEl = document.getElementById('voucherMessageTC');
    if (!msgEl) return;
    
    msgEl.textContent = message;
    msgEl.style.display = 'block';
    
    if (type === 'success') {
        msgEl.style.background = '#d4edda';
        msgEl.style.color = '#155724';
        msgEl.style.border = '1px solid #c3e6cb';
    } else {
        msgEl.style.background = '#f8d7da';
        msgEl.style.color = '#721c24';
        msgEl.style.border = '1px solid #f5c6cb';
    }
    
    setTimeout(() => {
        msgEl.style.display = 'none';
    }, 3000);
}

window.formatMoneyTC = function(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

// Show available vouchers
window.showAvailableVouchers = function() {
    const modal = document.getElementById('voucherListModal');
    const content = document.getElementById('voucherListContent');
    
    if (!modal || !content) return;
    
    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align: center; padding: 30px; color: #999;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><p style="margin-top: 10px;">Đang tải...</p></div>';
    
    fetch('get_vouchers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.vouchers && data.vouchers.length > 0) {
                let html = '';
                data.vouchers.forEach(voucher => {
                    html += `
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 18px; border-radius: 12px; position: relative; overflow: hidden; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                            <div style="position: relative; z-index: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div>
                                        <div style="font-size: 1.5rem; font-weight: 700; margin-bottom: 5px;">${voucher.discount}</div>
                                        <div style="font-size: 0.875rem; opacity: 0.9;">${voucher.description}</div>
                                    </div>
                                    <button onclick="copyVoucherCode('${voucher.code}')" style="background: rgba(255,255,255,0.3); color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-size: 0.875rem; font-weight: 600; backdrop-filter: blur(10px); transition: all 0.3s;">
                                        <i class="fas fa-copy"></i> Sao chép
                                    </button>
                                </div>
                                <div style="background: rgba(255,255,255,0.2); padding: 10px 12px; border-radius: 8px; margin-bottom: 10px; backdrop-filter: blur(10px);">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span style="font-family: 'Courier New', monospace; font-weight: 700; font-size: 1.1rem; letter-spacing: 1px;">${voucher.code}</span>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 15px; font-size: 0.813rem; opacity: 0.9;">
                                    ${voucher.minOrder ? `<div><i class="fas fa-shopping-cart"></i> ${voucher.minOrder}</div>` : ''}
                                    ${voucher.expiry ? `<div><i class="fas fa-clock"></i> HSD: ${voucher.expiry}</div>` : ''}
                                    ${voucher.quantity ? `<div><i class="fas fa-box"></i> ${voucher.quantity}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div style="text-align: center; padding: 30px; color: #999;"><i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i><p style="font-size: 1.1rem;">Hiện tại không có mã giảm giá nào</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading vouchers:', error);
            content.innerHTML = '<div style="text-align: center; padding: 30px; color: #dc3545;"><i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i><p>Không thể tải danh sách mã giảm giá</p></div>';
        });
}

window.closeVoucherList = function() {
    const modal = document.getElementById('voucherListModal');
    if (modal) modal.style.display = 'none';
}

window.copyVoucherCode = function(code) {
    // Copy to clipboard
    navigator.clipboard.writeText(code).then(() => {
        // Auto-fill voucher input
        const voucherInput = document.getElementById('voucherCodeTC') || 
                            document.getElementById('voucherCodeSP') || 
                            document.getElementById('voucherCodeSale');
        if (voucherInput) {
            voucherInput.value = code;
        }
        
        // Close modal
        closeVoucherList();
        
        // Show success message
        alert('✓ Đã sao chép mã: ' + code);
    }).catch(err => {
        console.error('Copy failed:', err);
        // Fallback: just fill the input
        const voucherInput = document.getElementById('voucherCodeTC') || 
                            document.getElementById('voucherCodeSP') || 
                            document.getElementById('voucherCodeSale');
        if (voucherInput) {
            voucherInput.value = code;
            closeVoucherList();
            alert('✓ Đã điền mã: ' + code);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const voucherInput = document.getElementById('voucherCodeTC');
    if (voucherInput) {
        voucherInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyVoucherTC();
            }
        });
    }
});

}); // End DOMContentLoaded

</script>

<!-- Quick View Modal for Homepage -->
<div class="hc-modal-overlay" id="homeQuickViewModal">
  <div class="hc-checkout-modal" style="max-width: 800px;">
    <div class="hc-modal-header">
      <div class="hc-modal-title" id="hqvTitle">Xem nhanh</div>
      <button class="hc-modal-close" onclick="closeHomeQuickView()" aria-label="Đóng">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="hc-modal-body">
      <div style="display:flex;gap:1.5rem;align-items:flex-start;flex-wrap:wrap;">
        <img id="hqvImg" src="" alt="" style="width:280px;height:auto;border-radius:8px;object-fit:cover;" />
        <div style="flex:1;min-width:300px;">
          <div style="font-weight:700;font-size:1.25rem;margin-bottom:0.5rem;" id="hqvName"></div>
          <div style="font-family:Georgia,serif;font-size:1.5rem;font-weight:700;margin-bottom:1rem;color:#2d2a26;" id="hqvPrice"></div>
          <div id="hqvDesc" style="color:#666;line-height:1.8;font-size:0.95rem;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Voucher List Modal -->
<div class="hc-modal-overlay" id="voucherListModal" style="display: none;">
  <div class="hc-checkout-modal" style="max-width: 700px;">
    <div class="hc-modal-header">
      <div class="hc-modal-title"><i class="fas fa-tags"></i> Mã giảm giá khả dụng</div>
      <button class="hc-modal-close" onclick="closeVoucherList()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="hc-modal-body">
      <div id="voucherListContent" style="display: flex; flex-direction: column; gap: 12px;">
        <div style="text-align: center; padding: 30px; color: #999;">
          <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
          <p style="margin-top: 10px;">Đang tải...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="assets/chatbot.css">
<link rel="stylesheet" href="assets/notifications.css">
<?php include 'assets/chatbot_session.php'; ?>
<script src="assets/notification_bell.js" defer></script>
<script src="assets/chatbot.js" defer></script>
</body>
</html>
