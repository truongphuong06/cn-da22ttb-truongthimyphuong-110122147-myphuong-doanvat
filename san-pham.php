<link rel="stylesheet" href="assets/notification_bell.css">
<?php
session_start();
require_once __DIR__ . '/connect.php';

// Shop payment info used to generate QR for payments
$shop_bank = 'Vietcombank';
$shop_account = '0123456789';
$shop_owner = 'MY SHOP';

// Lấy thông tin user nếu đã đăng nhập
$user_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT ten_dang_nhap, ho_ten, email FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Lấy danh sách voucher còn hiệu lực
$today = date('Y-m-d');
$vouchers_result = $conn->query("SELECT * FROM voucher WHERE trang_thai = 'hoat_dong' AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= '$today') AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= '$today') AND (so_luong_con_lai IS NULL OR so_luong_con_lai > 0) ORDER BY gia_tri_giam DESC");
$vouchers = [];
while ($row = $vouchers_result->fetch(PDO::FETCH_ASSOC)) {
    $vouchers[] = $row;
}

// Lấy filter từ query
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genderRaw = isset($_GET['gender']) ? trim($_GET['gender']) : 'all';
$gender = strtolower($genderRaw); // 'all', 'nam', 'nu'
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : 0;

// Lấy danh sách danh mục
$dmStmt = $conn->query("SELECT id, ten_san_pham AS ten_danh_muc FROM danh_muc ORDER BY ten_san_pham ASC");
$danh_muc_list = $dmStmt ? $dmStmt->fetchAll(PDO::FETCH_ASSOC) : [];


// Lấy sản phẩm (SQL đã xử lý filter/search nếu có)
$sql = "SELECT sp.*, dm.ten_san_pham AS ten_danh_muc
        FROM san_pham sp
        LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id";
$where = [];
$params = [];
if ($categoryId > 0) {
    $where[] = "sp.danh_muc_id = :category";
    $params[':category'] = $categoryId;
}
if ($search !== '') {
    $where[] = "(sp.ten_san_pham LIKE :q OR sp.mo_ta LIKE :q2)";
    $params[':q'] = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
}

// Price range filter
if ($minPrice > 0) {
    $where[] = "sp.gia >= :min_price";
    $params[':min_price'] = $minPrice;
}
if ($maxPrice > 0) {
    $where[] = "sp.gia <= :max_price";
    $params[':max_price'] = $maxPrice;
}

// Map tham chiếu gender sang giá trị trong DB (chỉnh nếu DB lưu khác)
$genderMap = [
    'nam' => 'Nam',
    'nu'  => 'Nữ',
    'all' => null
];

if ($gender !== 'all' && isset($genderMap[$gender]) && $genderMap[$gender] !== null) {
    // giả sử cột trong bảng sản phẩm là `gioi_tinh`
    $where[] = "sp.gioi_tinh = :gender";
    $params[':gender'] = $genderMap[$gender];
}


// Hiển thị sản phẩm SALE: chỉ lấy sản phẩm có giảm giá hợp lệ
$isSalePage = isset($_GET['promo']) && $_GET['promo'] === 'sale';
if ($isSalePage) {
  $where[] = "sp.gia_giam IS NOT NULL AND sp.gia_giam > 0 AND sp.gia_giam < sp.gia";
}
// Nếu không phải trang sale -> hiển thị TẤT CẢ sản phẩm (cả có sale và không sale)
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY sp.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$san_pham_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy đánh giá cho tất cả sản phẩm
$commentSummary = [];
if (!empty($san_pham_list)) {
    $productIds = array_column($san_pham_list, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    
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
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Shop </title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
/* ==========================================
   PREMIUM MINIMALIST DESIGN SYSTEM
   ========================================== */

:root {
  /* Embers Color Palette */
  --primary: #3D4457;        /* Indigo */
  --secondary: #A64674;      /* Berry */
  --accent: #FFA177;         /* Peach */
  --highlight: #F25D63;      /* Coral */
  --muted: #3D4457;          /* Indigo muted */
  
  /* Neutral Colors */
  --white: #ffffff;
  --gray-50: #fafafa;
  --gray-100: #f5f5f5;
  --gray-200: #e5e5e5;
  --gray-300: #d4d4d4;
  --gray-400: #a3a3a3;
  --gray-500: #737373;
  --gray-600: #525252;
  --gray-700: #404040;
  --gray-800: #262626;
  --gray-900: #171717;
  
  /* Legacy compatibility */
  --cream-50: var(--white);
  --cream-100: var(--gray-50);
  --cream-200: var(--gray-100);
  --beige-100: var(--gray-100);
  --beige-200: var(--gray-200);
  --beige-300: var(--gray-300);
  --taupe-400: var(--gray-400);
  --taupe-500: var(--gray-500);
  --charcoal: var(--gray-800);
  --black: var(--gray-900);
  --accent-gold: var(--accent);
  --accent-rose: var(--highlight);
  --accent-sage: var(--secondary);
  --accent-terracotta: var(--accent);
  
  /* Typography */
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-serif: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  
  /* Layout */
  --container-max: 1400px;
  
  /* Spacing */
  --space-xs: 0.25rem;
  --space-sm: 0.5rem;
  --space-md: 1rem;
  --space-lg: 1.5rem;
  --space-xl: 2rem;
  --space-2xl: 3rem;
  
  /* Border Radius */
  --radius-sm: 0.25rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 1rem;
  --radius-full: 9999px;
  
  /* Shadows */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
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
  font-family: var(--font-sans);
  font-size: 16px;
  line-height: 1.6;
  color: var(--charcoal);
  background: linear-gradient(to bottom, var(--cream-50), var(--cream-200));
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a {
  color: inherit;
  text-decoration: none;
  transition: all 0.2s ease;
}

button {
  font-family: inherit;
  cursor: pointer;
  border: none;
  background: none;
  transition: all 0.2s ease;
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
  padding: 0 var(--space-md);
}

@media (min-width: 640px) {
  .container { padding: 0 var(--space-lg); }
}

@media (min-width: 1024px) {
  .container { padding: 0 var(--space-2xl); }
}

/* ==========================================
   HEADER - LUXURY MINIMAL
   ========================================== */

.header {
  position: sticky;
  top: 0;
  z-index: 50;
  background: rgba(254, 253, 251, 0.98);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--beige-200);
}

.header .container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 5rem;
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: var(--font-serif);
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--black);
  background: linear-gradient(135deg, var(--black), var(--charcoal));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.brand-logo img {
  height: 3.5rem;
  width: auto;
  -webkit-text-fill-color: initial;
}

.nav {
  display: flex;
  pointer-events: auto;
  align-items: center;
  gap: var(--space-2xl);
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
  border: 2px solid #000;
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
  border-color: #000;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.nav a.active {
  color: var(--stone-900);
  background: white;
  border-color: #000;
  font-weight: 600;
  box-shadow: 0 2px 12px rgba(0,0,0,0.12);
}

.nav a:hover::after,
.nav a.active::after {
  width: 100%;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.icon-btn {
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--taupe-500);
  border-radius: var(--radius-lg);
  position: relative;
}

.icon-btn:hover {
  color: var(--black);
  background-color: var(--beige-100);
}

.icon-btn i {
  font-size: 1.25rem;
}

.cart-badge {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  background: var(--black);
  color: var(--cream-50);
  font-size: 0.625rem;
  font-weight: 700;
  width: 1.125rem;
  height: 1.125rem;
  border-radius: 50%;
  display: none;
  align-items: center;
  justify-content: center;
}

/* ==========================================
   BANNER SLIDER
   ========================================== */

.banner-slider {
  position: relative;
  height: 70vh;
  min-height: 500px;
  overflow: hidden;
  margin-bottom: var(--space-2xl);
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
  background: linear-gradient(to right, rgba(0,0,0,0.6), transparent 60%);
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
  font-family: var(--font-serif);
  font-size: 3.5rem;
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
  color: var(--black);
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

@media (max-width: 768px) {
  .banner-slider {
    height: 60vh;
    min-height: 400px;
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
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem 2rem;
  font-size: 1rem;
  font-weight: 600;
  border-radius: var(--radius-md);
  transition: all 0.3s ease;
}

.btn-primary {
  background: var(--black);
  color: var(--cream-50);
}

.btn-primary:hover {
  background: var(--charcoal);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.btn-white {
  background: white;
  color: var(--black);
  border: 2px solid white;
}

.btn-white:hover {
  background: transparent;
  color: white;
}

/* ==========================================
   FILTER SECTION
   ========================================== */

.filter-section {
  padding: var(--space-2xl) 0;
}

.shop-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 2rem;
  align-items: start;
}

@media (max-width: 1024px) {
  .shop-layout {
    grid-template-columns: 1fr;
  }
  
  .filter-sidebar {
    display: none;
  }
}

/* Filter Sidebar */
.filter-sidebar {
  background: white;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 1.5rem;
  position: sticky;
  top: 6rem;
  max-height: 70vh;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #bdbdbd #f5f5f5;
}

/* Custom scrollbar for Webkit browsers */
.filter-sidebar::-webkit-scrollbar {
  width: 8px;
}
.filter-sidebar::-webkit-scrollbar-thumb {
  background: #bdbdbd;
  border-radius: 6px;
}
.filter-sidebar::-webkit-scrollbar-track {
  background: #f5f5f5;
}
}

.filter-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 1.5rem;
}

.filter-header i {
  font-size: 1.25rem;
  color: #333;
}

.filter-header h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: #000;
  margin: 0;
}

.filter-group {
  margin-bottom: 2rem;
}

.filter-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #000;
  margin: 0 0 1rem 0;
}

.filter-options {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.filter-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 6px;
  transition: background 0.2s;
}

.filter-option:hover {
  background: #f9f9f9;
}

.filter-option input[type="radio"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #000;
}

.filter-label {
  font-size: 0.9375rem;
  color: #666;
  flex: 1;
}

.filter-option input[type="radio"]:checked + .filter-label {
  color: #000;
  font-weight: 600;
}

/* Price Range */
.price-inputs {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.75rem;
}

.price-input-wrapper {
  flex: 1;
  position: relative;
}

.price-input-wrapper::before {
  content: '₫';
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #999;
  font-size: 0.875rem;
  font-weight: 600;
  pointer-events: none;
}

.price-input {
  width: 100%;
  padding: 0.75rem 0.75rem 0.75rem 2rem;
  border: 2px solid #e5e5e5;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  background: #fafafa;
  transition: all 0.3s ease;
}

.price-input::placeholder {
  color: #aaa;
  font-weight: 400;
}

.price-input:focus {
  outline: none;
  border-color: #0066ff;
  background: white;
  box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.1);
}

.price-separator {
  color: #999;
  font-weight: 600;
  font-size: 1rem;
}

/* Filter Actions */
.filter-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #f0f0f0;
}

.btn-apply,
.btn-clear {
  width: 100%;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-apply {
  background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(0, 102, 255, 0.2);
}

.btn-apply:hover {
  background: linear-gradient(135deg, #0052cc 0%, #0041a3 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 102, 255, 0.35);
}

.btn-apply:active {
  transform: translateY(0);
}

.btn-clear {
  background: white;
  color: #666;
  border: 2px solid #e5e5e5;
}

.btn-clear:hover {
  background: #f9f9f9;
  border-color: #ff4444;
  color: #ff4444;
  transform: translateY(-1px);
}

/* Products Main */
.products-main {
  min-width: 0;
}

.products-header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #f0f0f0;
}

.products-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #000;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.products-count {
  font-size: 0.875rem;
  color: #999;
  font-weight: 400;
}

.search-wrapper {
  position: relative;
  max-width: 400px;
  flex: 1;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #999;
  font-size: 1rem;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 3rem;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  font-size: 0.9375rem;
  transition: all 0.3s;
}

.search-input:focus {
  outline: none;
  border-color: #333;
  box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
}

.filter-bar {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-xl);
  margin-bottom: var(--space-xl);
  box-shadow: var(--shadow-sm);
}

.search-wrapper {
  position: relative;
  margin-bottom: var(--space-lg);
}

.category-filter {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-sm);
  align-items: center;
}

.category-btn {
  padding: 0.625rem 1.5rem;
  border: 2px solid #e5e5e5;
  border-radius: 25px;
  background: white;
  color: #666;
  font-size: 0.9375rem;
  font-weight: 500;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}

.category-btn i {
  font-size: 0.875rem;
}

.category-btn:hover {
  border-color: #333;
  color: #000;
  background: #f9f9f9;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.category-btn.active {
  background: #000;
  color: white;
  border-color: #000;
  font-weight: 600;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}

/* ==========================================
   STATS BAR
   ========================================== */

.stats-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-md);
  margin-bottom: var(--space-xl);
}

.stat-item {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
  text-align: center;
  transition: all 0.3s ease;
}

.stat-item:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: var(--accent-gold);
}

.stat-value {
  font-family: var(--font-serif);
  font-size: 2rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-xs);
}

.stat-label {
  font-size: 0.875rem;
  color: var(--taupe-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* ==========================================
   PRODUCTS SECTION
   ========================================== */

.products-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-xl);
}

.products-title {
  font-family: var(--font-serif);
  font-size: 2rem;
  color: var(--black);
}

.products-count {
  font-size: 0.875rem;
  color: var(--taupe-500);
  font-weight: 500;
}

.products-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-xl);
  margin-bottom: var(--space-2xl);
}

@media (min-width: 640px) {
  .products-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .products-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 1280px) {
  .products-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

/* ==========================================
   PRODUCT CARD - PREMIUM DESIGN
   ========================================== */

.product-card {
  background: #e8f2d8;
  border: 3px solid #a0c75f;
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}

.product-card:hover {
  transform: translateY(-8px);
  box-shadow: var(--shadow-xl);
  border-color: var(--beige-300);
}

.product-image-wrapper {
  position: relative;
  aspect-ratio: 3/4;
  background: var(--beige-100);
  overflow: hidden;
}

.product-image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-card:hover .product-image-wrapper img {
  transform: scale(1.08);
}

.product-badge {
  position: absolute;
  top: var(--space-md);
  left: var(--space-md);
  background: var(--accent-terracotta);
  color: var(--cream-50);
  padding: var(--space-xs) var(--space-md);
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  z-index: 2;
}

.product-locked .product-badge {
  background: #dc3545;
  color: white;
}

.product-locked .product-image-wrapper::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 1;
}

.product-locked .product-image-wrapper img {
  filter: grayscale(50%) opacity(0.7);
}

.product-locked:hover {
  transform: none;
  cursor: not-allowed;
}

.product-locked .add-to-cart-btn {
  background: #6c757d;
  cursor: not-allowed;
  pointer-events: none;
}

.product-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.5) 0%, transparent 50%);
  opacity: 0;
  transition: opacity 0.4s ease;
  pointer-events: none;
}

.product-card:hover .product-overlay {
  opacity: 1;
}


.quick-actions {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  z-index: 3;
  opacity: 1;
  transform: none;
  pointer-events: none;
}

.quick-btn {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.95);
  border-radius: 50%;
  color: var(--charcoal);
  box-shadow: 0 2px 8px rgba(0,0,0,0.10);
  font-size: 1.25rem;
  border: none;
  margin-bottom: 0.25rem;
  position: relative;
  cursor: pointer;
  pointer-events: all;
  transition: all 0.2s;
}

.quick-btn:hover {
  background: var(--accent);
  color: #fff;
  transform: scale(1.08);
}

.quick-btn .like-count {
  position: absolute;
  top: 0.15rem;
  right: 0.15rem;
  background: #ef4444;
  color: #fff;
  font-size: 0.85rem;
  padding: 0 0.4em;
  border-radius: 1em;
  min-width: 1.2em;
  text-align: center;
  font-weight: 700;
  box-shadow: 0 1px 4px rgba(0,0,0,0.10);
  pointer-events: none;
}

.quick-btn {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: var(--radius-md);
  color: var(--charcoal);
  box-shadow: var(--shadow-md);
  transition: all 0.2s ease;
  position: relative;
}

.quick-btn:hover {
  background: var(--cream-50);
  transform: scale(1.1);
}

.quick-btn.favorited {
  background: var(--accent-rose);
  color: var(--cream-50);
}

.quick-btn.liked i {
  color: #ef4444;
}

.like-count {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #ef4444;
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
  display: none;
}

.like-count.show {
  display: block;
}

.quick-btn i {
  font-size: 1rem;
}

/* ==========================================
   PRODUCT INFO
   ========================================== */

.product-info {
  padding: var(--space-lg);
}

.product-category {
  font-size: 0.75rem;
  color: var(--taupe-400);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  margin-bottom: var(--space-xs);
}

.product-name {
  font-size: 1rem;
  font-weight: 600;
  color: var(--black);
  line-height: 1.4;
  min-height: 2.8rem;
  margin-bottom: var(--space-md);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-desc {
  font-size: 0.9375rem;
  color: var(--taupe-400);
  margin-bottom: var(--space-sm);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-rating {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  margin-bottom: var(--space-md);
}

.rating-stars {
  display: flex;
  gap: 0.125rem;
}

.rating-stars i {
  font-size: 0.875rem;
  color: var(--accent-gold);
}

.rating-stars i.empty {
  color: var(--beige-300);
}

.rating-count {
  font-size: 0.75rem;
  color: var(--taupe-400);
}

.product-price {
  font-family: var(--font-serif);
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-lg);
}

.product-old-price {
  text-decoration: line-through;
  color: #999;
  font-size: 0.9em;
  margin-left: 8px;
  font-weight: 400;
}

/* ==========================================
   SIZE SELECTOR
   ========================================== */

.size-selector {
  margin-bottom: var(--space-lg);
}

.size-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--taupe-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: var(--space-sm);
  display: block;
}

.size-options {
  display: flex;
  gap: var(--space-sm);
}

/* ==========================================
   ADD TO CART SECTION
   ========================================== */

.add-to-cart-section {
  display: block;
  margin-bottom: var(--space-sm);
}

.qty-selector,
.quantity-selector {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--beige-200);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--cream-100);
  margin-bottom: var(--space-sm);
  width: 100%;
}

.quantity-selector .qty-btn {
  width: 2.5rem;
  height: 2rem;
  border: none;
  background: transparent;
  cursor: pointer;
}

.quantity-selector .qty-btn:hover {
  background: var(--beige-100);
}

.qty-btn {
  width: 3rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--taupe-500);
  background: transparent;
  transition: all 0.2s ease;
  flex: 0 0 auto;
}

.qty-btn:hover {
  background: var(--beige-100);
  color: var(--black);
}

.qty-input {
  flex: 1;
  text-align: center;
  border: none;
  background: transparent;
  font-weight: 600;
  color: var(--black);
  height: 2.5rem;
}

.qty-input:focus {
  outline: none;
}

.add-to-cart-btn {
  width: 100%;
  padding: var(--space-md);
  background: #000;
  color: #fff;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: all 0.3s ease;
  text-transform: none;
  letter-spacing: normal;
}

.add-to-cart-btn:hover {
  background: #333;
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.add-to-cart-btn:active {
  transform: translateY(0);
}

/* ==========================================
   ADD CART BUTTON (alternative style)
   ========================================== */

.add-cart-btn {
  width: 100%;
  background-color: #171717;
  color: #fff;
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
  border: none;
}

.add-cart-btn:hover {
  background-color: #404040;
  transform: translateY(-2px);
}

.add-cart-btn:disabled {
  background-color: #6c757d;
  cursor: not-allowed;
  transform: none;
}

/* ==========================================
   BUY NOW BUTTON
   ========================================== */

.buy-now-btn {
  width: 100%;
  padding: var(--space-md);
  background: #E8DCC8;
  color: #2d2a26;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: all 0.3s ease;
  text-transform: none;
  letter-spacing: normal;
}

.buy-now-btn:hover {
  background: #D4C5B0;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(212, 197, 176, 0.4);
}

.buy-now-btn:disabled {
  background: #ccc;
  color: #666;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* ==========================================
   CHECKOUT MODAL
   ========================================== */

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 100;
  display: none;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

.modal-overlay.active {
  display: flex;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.checkout-modal {
  background: var(--cream-50);
  border-radius: var(--radius-xl);
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: var(--shadow-xl);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from { 
    opacity: 0;
    transform: translateY(2rem);
  }
  to { 
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-xl);
  border-bottom: 1px solid var(--beige-200);
}

.modal-title {
  font-family: var(--font-serif);
  font-size: 1.75rem;
  color: var(--black);
}

.modal-close {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: var(--taupe-500);
  transition: all 0.2s;
}

.modal-close:hover {
  background: var(--beige-100);
  color: var(--black);
}

  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  background: none;
  border: none;
  font-size: 2rem;
  color: var(--taupe-500);
  cursor: pointer;
  z-index: 9999;
  padding: 0.25rem 0.5rem;
  border-radius: 50%;
  transition: background 0.2s, color 0.2s;
  pointer-events: auto;
}
  width: 100px;
  height: 130px;
  object-fit: cover;
  border-radius: var(--radius-md);
}

.checkout-product-info {
  flex: 1;
}

.checkout-product-name {
  font-weight: 600;
  color: var(--black);
  margin-bottom: var(--space-xs);
}

.checkout-product-details {
  font-size: 0.875rem;
  color: var(--taupe-500);
  margin-bottom: var(--space-sm);
}

.checkout-product-price {
  font-family: var(--font-serif);
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--black);
}

.checkout-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.form-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--black);
}

.form-input,
.form-select,
.form-textarea {
  padding: var(--space-md);
  border: 2px solid var(--beige-200);
  border-radius: var(--radius-md);
  background: var(--cream-100);
  font-size: 1rem;
  color: var(--charcoal);
  transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--accent-gold);
  background: var(--cream-50);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
}

.checkout-summary {
  background: var(--cream-100);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
  margin-top: var(--space-lg);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: var(--space-sm) 0;
  font-size: 0.875rem;
}

.summary-row.total {
  border-top: 2px solid var(--beige-200);
  margin-top: var(--space-sm);
  padding-top: var(--space-md);
  font-size: 1.125rem;
  font-weight: 700;
}

.submit-order-btn {
  width: 100%;
  padding: var(--space-lg);
  background: var(--black);
  color: var(--cream-50);
  border-radius: var(--radius-md);
  font-size: 1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: var(--space-lg);
  transition: all 0.3s ease;
}

.submit-order-btn:hover {
  background: var(--charcoal);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

/* ==========================================
   FEATURES
   ========================================== */

.features-section {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-2xl);
  margin: var(--space-2xl) 0;
}

.features-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-xl);
}

@media (min-width: 768px) {
  .features-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-lg);
}

.feature-icon {
  width: 3.5rem;
  height: 3.5rem;
  background: var(--black);
  color: var(--cream-50);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.feature-icon i {
  font-size: 1.5rem;
}

.feature-content h3 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-xs);
}

.feature-content p {
  font-size: 0.875rem;
  color: var(--taupe-500);
}

/* ==========================================
   FOOTER
   ========================================== */

.footer {
  background: var(--beige-100);
  padding: var(--space-2xl) 0;
  text-align: center;
  margin-top: var(--space-2xl);
}

.footer-text {
  font-size: 0.875rem;
  color: var(--taupe-500);
}

/* ==========================================
   UTILITIES
   ========================================== */

@media (max-width: 1023px) {
  .hide-mobile { display: none !important; }
}

@media (max-width: 640px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}

/* ==========================================
   VOUCHER STYLES
   ========================================== */

.voucher-item-sp {
  display: flex;
  align-items: center;
  padding: 12px;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  background: white;
}

.voucher-item-sp:hover {
  border-color: #007bff;
  background: #f8f9ff;
}

.voucher-item-sp.selected {
  border-color: #28a745;
  background: #f8fff8;
}

.voucher-badge-sp {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: bold;
  font-size: 12px;
  margin-right: 12px;
  min-width: 60px;
  text-align: center;
}

.voucher-info-sp {
  flex: 1;
}

.voucher-title-sp {
  font-weight: 600;
  color: #333;
  font-size: 14px;
  margin-bottom: 2px;
}

.voucher-desc-sp {
  color: #28a745;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 2px;
}

.voucher-condition-sp {
  color: #666;
  font-size: 12px;
  margin-bottom: 2px;
}

.voucher-expire-sp {
  color: #dc3545;
  font-size: 11px;
  font-weight: 500;
}

.voucher-action-sp {
  color: #007bff;
  font-size: 16px;
  margin-left: 12px;
}

.voucher-item-sp.selected .voucher-action-sp {
  color: #28a745;
}
</style>
</head>
<body>

<!-- Header -->
<header class="header">
  <div class="container">
    <div class="brand">
      <a href="trangchu.php" class="brand-logo">
        <img src="images/logo.png?v=<?php echo time(); ?>" alt="Logo">
      </a>
    </div>

    <nav class="nav">
      <a href="trangchu.php">
        <i class="fas fa-home"></i>
        <span>Trang Chủ</span>
      </a>
      
      <a href="san-pham.php" class="active">Sản Phẩm</a>
      <a href="don_hang_cua_toi.php">Theo Dõi Đơn Hàng</a>
      <a href="lienhe.php">Liên Hệ</a>
    </nav>

    <div class="header-actions">
      
      <a href="giohang.php" class="icon-btn" title="Giỏ hàng">
        <i class="fas fa-shopping-bag"></i>
        <span class="cart-badge">0</span>
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
    </div>
  </div>
</header>

<!-- Banner Slider -->
<section class="banner-slider">
  <div class="slider-track">
    <div class="slide active">
      <div class="slide-bg" style="background-image: url('images/banner1.jpeg');"></div>
      <div class="slide-content">
        <div class="slide-badge"></div>
      
         
            
          </a>
        </div>
      </div>
    </div>

    <!-- Slide 2 -->
    <div class="slide">
      <div class="slide-bg" style="background-image: url('images/banner2.jpeg');"></div>
      <div class="slide-content">
        
        </p>
        <div class="slide-cta">
          <a href="#products" class="btn btn-primary">

        
          </a>
        </div>
      </div>
    </div>

    <!-- Slide 3 -->
    <div class="slide">
      <div class="slide-bg" style="background-image: url('images/banner3.jpeg');"></div>
      <div class="slide-content">
        <div class="slide-badge"></div>
       
       
          
            
          </a>
        </div>
      </div>
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

<!-- Main Content -->
<div class="container">
  <section class="filter-section" id="products">
    <div class="shop-layout">
      <!-- Left Sidebar Filter -->
      <aside class="filter-sidebar">
        <div class="filter-header">
          <i class="fas fa-filter"></i>
          <h3>Bộ Lọc</h3>
        </div>
        
        <!-- Category Filter -->
        <div class="filter-group">
          <h4 class="filter-title">Danh Mục</h4>
          <div class="filter-options">
            <label class="filter-option">
              <input type="radio" name="category" value="all" <?php echo $categoryId === 0 ? 'checked' : ''; ?> onchange="filterByCategory('all')">
              <span class="filter-label">Tất cả</span>
            </label>
            <?php foreach($danh_muc_list as $dm): ?>
            <label class="filter-option">
              <input type="radio" name="category" value="<?php echo (int)$dm['id']; ?>" <?php echo $categoryId === (int)$dm['id'] ? 'checked' : ''; ?> onchange="filterByCategory(<?php echo (int)$dm['id']; ?>)">
              <span class="filter-label"><?php echo htmlspecialchars($dm['ten_danh_muc']); ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        
        <!-- Price Range Filter -->
        <div class="filter-group">
          <h4 class="filter-title">Khoảng Giá</h4>
          <div class="price-inputs">
            <div class="price-input-wrapper">
              <input type="number" class="price-input" placeholder="Từ" id="priceMin" min="0" step="1000">
            </div>
            <span class="price-separator">—</span>
            <div class="price-input-wrapper">
              <input type="number" class="price-input" placeholder="Đến" id="priceMax" min="0" step="1000">
            </div>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="filter-actions">
          <button class="btn-apply" onclick="applyFilters()">
            <i class="fas fa-check-circle"></i> Áp Dụng
          </button>
          <button class="btn-clear" onclick="clearFilters()">
            <i class="fas fa-times-circle"></i> Xóa Bộ Lọc
          </button>
        </div>
      </aside>
      
      <!-- Main Content -->
      <div class="products-main">
        <!-- Search Bar -->
        <div class="products-header-bar">
          <h2 class="products-title">Sản Phẩm <span class="products-count"><?php echo count($san_pham_list); ?> sản phẩm</span></h2>
          <div class="search-wrapper">
            <input 
              type="text" 
              id="productSearch" 
              class="search-input" 
              placeholder="Tìm kiếm sản phẩm..." 
              autocomplete="off"
            >
            <span id="searchResultCount" style="display:none; position:absolute; right:15px; top:50%; transform:translateY(-50%); font-size:0.875rem; color:#999;"></span>
          </div>
        </div>

        <!-- Products Grid -->
    <div class="products-grid" id="productsContainer">
      <?php 
      
      foreach($san_pham_list as $index => $p):
        // Check if product is locked or out of stock
        $isLocked = (isset($p['trang_thai']) && $p['trang_thai'] == 0) || ((int)($p['so_luong'] ?? 0) <= 0);
        $img = '';
        if (!empty($p['hinh_anh'])) {
          if (strpos($p['hinh_anh'], 'http') === 0) $img = $p['hinh_anh'];
          elseif (file_exists(__DIR__ . '/uploads/' . $p['hinh_anh'])) $img = 'uploads/' . $p['hinh_anh'];
        }
        if (!$img) $img = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600';
        $name = htmlspecialchars($p['ten_san_pham'] ?? 'Sản phẩm');
        $price = isset($p['gia']) ? (float)$p['gia'] : 0;
        $salePrice = isset($p['gia_giam']) && $p['gia_giam'] > 0 && $p['gia_giam'] < $price ? (float)$p['gia_giam'] : 0;
        $priceFormatted = number_format($price,0,',','.') . '₫';
        $salePriceFormatted = $salePrice > 0 ? number_format($salePrice,0,',','.') . '₫' : '';
        $category = htmlspecialchars($p['ten_danh_muc'] ?? 'Thời trang');
        $productId = (int)$p['id'];
        $summary = isset($commentSummary[$productId]) ? $commentSummary[$productId] : null;
        $rating = $summary ? (float)$summary['avg'] : 0;
        $reviews = $summary ? (int)$summary['count'] : 0;
        $badge = ($index < 3) ? 'New' : '';
        if ($isLocked) {
          $badge = 'Hết hàng';
        }
      ?>
        <article class="product-card<?php echo $isLocked ? ' product-locked' : ''; ?>" id="prod-<?php echo $productId; ?>" data-id="<?php echo $productId; ?>" 
           data-name="<?php echo htmlspecialchars($p['ten_san_pham'] ?? ''); ?>"
           data-price="<?php echo $salePrice > 0 ? $salePrice : $price; ?>"
           data-img="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>"
           data-category="<?php echo htmlspecialchars($p['ten_danh_muc'] ?? 'Thực phẩm'); ?>"
           data-description="<?php 
             $desc = isset($p['mo_ta']) ? trim($p['mo_ta']) : '';
             if ($desc === '' || $desc === null) {
               // Mô tả mẫu cho Mứt Me Rim
               if (isset($p['ten_san_pham']) && mb_strtolower($p['ten_san_pham'], 'UTF-8') === 'mứt me rim') {
                 echo 'Mứt me chua ngọt được làm từ me tươi, có vị cay nhẹ hòa quyện với gia vị và ớt. Sản phẩm này không chỉ là món ăn vặt thú vị mà còn mang lại cảm giác ấm áp, thích hợp cho những ngày se lạnh hoặc khi bạn cần một chút hương vị khác lạ.';
               } else {
                 echo 'Sản phẩm chất lượng cao, thiết kế đẹp và sang trọng.';
               }
             } else {
               // Encode xuống dòng và ký tự đặc biệt cho HTML attribute
               echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
             }
           ?>">
          <div class="product-image-wrapper">
            <a href="chitiet_san_pham.php?id=<?php echo $productId; ?>" style="display: block;">
              <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($p['ten_san_pham'] ?? 'Sản phẩm'); ?>">
            </a>
            <?php if ($isSalePage && $p['gia_giam'] > 0 && $p['gia_giam'] < $p['gia']): ?>
              <span class="product-badge" style="background:#e53e3e;color:#fff;">SALE</span>
            <?php endif; ?>
            <div class="quick-actions">
              <!-- Xóa nút xem nhanh -->
              <button class="quick-btn like-btn" data-id="<?php echo $productId; ?>" onclick="toggleLike(<?php echo $productId; ?>)" title="Yêu thích">
                <i class="far fa-heart"></i>
                <span class="like-count" style="position:absolute;top:0.2rem;right:0.2rem;background:#ef4444;color:#fff;font-size:0.85rem;padding:0 0.4em;border-radius:1em;min-width:1.2em;text-align:center;">0</span>
              </button>
            </div>
          </div>
          <div class="product-info">
            <div class="product-name">
              <a href="chitiet_san_pham.php?id=<?php echo $productId; ?>" style="color: inherit; text-decoration: none;">
                <?php echo htmlspecialchars($p['ten_san_pham'] ?? ''); ?>
              </a>
            </div>
            <div class="product-desc" style="font-size:0.95em;color:#666;line-height:1.5;margin-bottom:0.5em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?php 
                $desc = isset($p['mo_ta']) ? trim($p['mo_ta']) : '';
                if ($desc === '' || $desc === null) {
                  echo 'Sản phẩm chất lượng cao, thiết kế đẹp và sang trọng.';
                } else {
                  echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
                }
              ?>
            </div>
            <div class="product-price-row">
              <?php if (isset($p['gia_giam']) && $p['gia_giam'] > 0 && $p['gia_giam'] < $p['gia']): ?>
                <span class="product-price" style="color:#e53e3e;font-weight:700;"><?php echo number_format((float)($p['gia_giam']), 0, ',', '.'); ?>₫</span>
                <span class="product-old-price"><?php echo number_format((float)($p['gia']), 0, ',', '.'); ?>₫</span>
              <?php else: ?>
                <span class="product-price"><?php echo number_format((float)($p['gia']), 0, ',', '.'); ?>₫</span>
              <?php endif; ?>
            </div>
            <!-- Đánh giá sao -->
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
    </div>
    
    <!-- No Results Message -->
    <div id="noResults" style="display:none; text-align:center; padding:60px 20px; grid-column: 1/-1;">
      <i class="fas fa-search" style="font-size:64px; color:#ddd; margin-bottom:20px;"></i>
      <h3 style="color:#666; font-size:1.5rem; margin-bottom:10px;">Không tìm thấy sản phẩm</h3>
      <p style="color:#999;">Thử tìm kiếm với từ khóa khác</p>
    </div>
    
      </div><!-- .products-main -->
    </div><!-- .shop-layout -->
  </section>

  <!-- Features -->
  <section class="features-section">
    <div class="features-grid">
      <div class="feature-item">
        <div class="feature-icon">
          <i class="fas fa-truck"></i>
        </div>
        <div class="feature-content">
          <h3>Giao hàng miễn phí</h3>
          <p>Đơn hàng từ 500.000₫ trở lên</p>
        </div>
      </div>

      <div class="feature-item">
        <div class="feature-icon">
          <i class="fas fa-shield-alt"></i>
        </div>
        <div class="feature-content">
          <h3>Thanh toán an toàn</h3>
          <p>Bảo mật thông tin 100%</p>
        </div>
      </div>

      <div class="feature-item">
        <div class="feature-icon">
          <i class="fas fa-sync-alt"></i>
        </div>
        <div class="feature-content">
          <h3>Đổi trả dễ dàng</h3>
          <p>Trong vòng 30 ngày</p>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <p class="footer-text">© <?php echo date('Y'); ?> My Shop — Thiết kế bởi đam mê</p>
  </div>
</footer>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkoutModal">
  <div class="checkout-modal">
    <div class="modal-header">
      <h3 class="modal-title">Thanh Toán</h3>
      <button class="modal-close" onclick="closeCheckoutModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <!-- Product Preview -->
      <div class="checkout-product" id="checkoutProduct">
        <img src="" alt="" class="checkout-product-img" id="checkoutImg">
        <div class="checkout-product-info">
          <h4 class="checkout-product-name" id="checkoutName">Tên sản phẩm</h4>
          <div class="checkout-product-details">
            <span id="checkoutQty">Số lượng: 
              <div style="display: inline-flex; align-items: center; gap: 8px;">
                <button type="button" onclick="adjustCheckoutQty(-1)" style="background: #f0f0f0; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">-</button>
                <span id="checkoutQtyValue" style="min-width: 20px; text-align: center; font-weight: 600;">1</span>
                <button type="button" onclick="adjustCheckoutQty(1)" style="background: #f0f0f0; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">+</button>
              </div>
            </span>
          </div>
          <div class="checkout-product-price" id="checkoutPrice">0₫</div>
        </div>
      </div>

      <!-- Checkout Form -->
      <form class="checkout-form" id="checkoutForm" onsubmit="return handleCheckoutSubmit(event)">
        <div class="form-group">
          <label class="form-label">Họ và tên *</label>
          <input type="text" class="form-input" name="fullname" required placeholder="Nguyễn Văn A" value="<?php echo isset($user_info['ten_dang_nhap']) ? htmlspecialchars($user_info['ten_dang_nhap']) : ''; ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Số điện thoại *</label>
            <input type="tel" class="form-input" name="phone" required placeholder="0123456789" value="<?php echo isset($user_info['dien_thoai']) ? htmlspecialchars($user_info['dien_thoai']) : ''; ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-input" name="email" placeholder="email@example.com" value="<?php echo isset($user_info['email']) ? htmlspecialchars($user_info['email']) : ''; ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Địa chỉ giao hàng *</label>
          <input type="text" class="form-input" name="address" required placeholder="Số nhà, tên đường" value="<?php echo isset($user_info['dia_chi']) ? htmlspecialchars($user_info['dia_chi']) : ''; ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Thành phố *</label>
            <select class="form-select" name="city" required>
              <option value="">Chọn thành phố</option>
              <option value="Hồ Chí Minh">Hồ Chí Minh</option>
              <option value="Hà Nội">Hà Nội</option>
              <option value="Đà Nẵng">Đà Nẵng</option>
              <option value="Cần Thơ">Cần Thơ</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Phương thức thanh toán</label>
            <select class="form-select" name="payment" id="paymentSelect">
              <option value="cod">Thanh toán khi nhận hàng</option>
              <option value="bank">Chuyển khoản ngân hàng</option>
              <option value="qr">Quét mã QR</option>
            </select>
            <input type="hidden" name="order_id" id="orderIdInput" value="">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Ghi chú</label>
          <textarea class="form-textarea" name="note" placeholder="Ghi chú đơn hàng (tùy chọn)"></textarea>
        </div>

        <!-- Voucher Section -->
        <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #dee2e6;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <label class="form-label" style="margin: 0;"><i class="fas fa-ticket-alt"></i> Mã giảm giá</label>
            <button type="button" onclick="window.showAvailableVouchers()" style="padding: 5px 12px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;">
              <i class="fas fa-tags"></i> Xem mã khả dụng
            </button>
          </div>
          
          <!-- Danh sách voucher có sẵn -->
          <?php if (!empty($vouchers)): ?>
          <div id="voucherListSP" style="margin-bottom: 15px; display: none;">
            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
              <i class="fas fa-gift"></i> Chọn mã giảm giá có sẵn:
            </p>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 5px; padding: 10px;">
              <?php foreach ($vouchers as $v): ?>
                <?php
                    $discountText = $v['loai_giam'] === 'phan_tram' 
                        ? $v['gia_tri_giam'] . '%' 
                        : number_format($v['gia_tri_giam']) . '₫';
                    $minOrderText = $v['gia_tri_don_hang_toi_thieu'] > 0 
                        ? 'Đơn tối thiểu ' . number_format($v['gia_tri_don_hang_toi_thieu']) . '₫' 
                        : 'Không giới hạn';
                    $expireText = $v['ngay_ket_thuc'] ? 'HSD: ' . date('d/m/Y', strtotime($v['ngay_ket_thuc'])) : '';
                ?>
                <div class="voucher-item-sp" onclick="selectVoucherSP('<?= htmlspecialchars($v['ma_voucher']) ?>', '<?= $v['loai_giam'] ?>', <?= $v['gia_tri_giam'] ?>, <?= $v['gia_tri_don_hang_toi_thieu'] ?>)">
                  <div class="voucher-badge-sp"><?= htmlspecialchars($v['ma_voucher']) ?></div>
                  <div class="voucher-info-sp">
                    <div class="voucher-title-sp"><?= htmlspecialchars($v['ten_voucher']) ?></div>
                    <div class="voucher-desc-sp">Giảm <?= $discountText ?></div>
                    <div class="voucher-condition-sp"><?= $minOrderText ?></div>
                    <?php if ($expireText): ?>
                      <div class="voucher-expire-sp"><?= $expireText ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="voucher-action-sp">
                    <i class="fas fa-plus-circle"></i>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          
          <!-- Hoặc nhập mã thủ công -->
          <div style="border-top: 1px dashed #ddd; padding-top: 15px;">
            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
              <i class="fas fa-keyboard"></i> Hoặc nhập mã thủ công:
            </p>
            <div style="display: flex; gap: 10px;">
              <input type="text" class="form-input" id="voucherCodeSP" placeholder="Nhập mã voucher" style="flex: 1;">
              <button type="button" onclick="applyVoucherSP()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; white-space: nowrap;">
                <i class="fas fa-check"></i> Áp dụng
              </button>
            </div>
          </div>
          
          <div id="voucherMessageSP" style="margin-top: 10px; padding: 8px; border-radius: 5px; display: none; font-size: 14px;"></div>
          <input type="hidden" name="ma_voucher" id="ma_voucherSP" value="">
          <input type="hidden" name="giam_gia" id="giam_giaSP" value="0">
        </div>

        <!-- Summary -->
        <div class="checkout-summary">
          <div class="summary-row">
            <span>Tạm tính</span>
            <span id="summarySubtotal">0₫</span>
          </div>
          <div class="summary-row" id="discountRowSP" style="display: none; color: #28a745;">
            <span><i class="fas fa-tag"></i> Giảm giá</span>
            <span id="summaryDiscountSP">0₫</span>
          </div>
          <div class="summary-row">
            <span>Phí vận chuyển</span>
            <span id="summaryShipping">30.000₫</span>
          </div>
          <div class="summary-row total">
            <span>Tổng cộng</span>
            <span id="summaryTotal">0₫</span>
          </div>
        </div>

        <!-- Bank Transfer Info Section (hidden by default) -->
        <div id="bankInfoSection" style="display: none; margin-top: 1.5rem;">
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
        <div id="qrCodeSection" style="display: none; margin-top: 1.5rem;">
          <div style="background: #f8fafc; border: 2px solid #3b82f6; border-radius: 12px; padding: 1.5rem; max-width: 420px; margin: 0 auto;">
            <h4 style="font-weight: 700; font-size: 1rem; margin-bottom: 1rem; color: #1e40af; text-align: center;">
              📱 Quét mã QR để thanh toán
            </h4>
            <div style="background: white; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
              <img id="qrCodeImage" src="" alt="QR Code Thanh Toán" style="max-width: 280px; width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 8px;">
            </div>
          </div>
        </div>

        <button type="submit" class="submit-order-btn">
          <i class="fas fa-check-circle"></i>
          Đặt Hàng Ngay
        </button>
      </form>
    </div>
  </div>
</div>

    <!-- Quick View Modal -->
    <div class="modal-overlay" id="quickViewModal">
      <div class="checkout-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
          <h3 class="modal-title" id="qvTitle">Xem nhanh</h3>
          <button class="modal-close" onclick="closeQuickView()">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body" id="qvBody">
          <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
            <img id="qvImg" src="" alt="" style="width:220px;height:auto;border-radius:8px;object-fit:cover;" />
            <div style="flex:1;min-width:240px;">
              <div style="font-weight:700;font-size:1.125rem;margin-bottom:0.25rem;" id="qvName"></div>
              <div style="color:var(--taupe-500);margin-bottom:0.75rem;" id="qvCategory"></div>
              <div style="font-family:var(--font-serif);font-size:1.25rem;font-weight:700;margin-bottom:0.75rem;" id="qvPrice"></div>
              <div id="qvDesc" style="color:var(--taupe-500);margin-bottom:1rem;line-height:1.8;font-size:0.95rem;text-align:left;white-space:pre-line;"></div>
            </div>
          </div>
        </div>

            <!-- QR Scanner Modal -->
            <div class="modal-overlay" id="qrScannerModal">
              <div class="checkout-modal">
                <div class="modal-header">
                  <h3 class="modal-title">Quét mã QR</h3>
                  <button class="modal-close" onclick="closeQrScanner()">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div id="qr-reader" style="width:100%;max-width:520px;margin:0 auto;"></div>
                  <div style="text-align:center;margin-top:0.5rem;">
                    <label for="qrCameraSelect" style="font-size:0. ninerem;color:var(--taupe-500);">Chọn camera:</label>
                    <select id="qrCameraSelect" style="margin-left:0.5rem;padding:0.25rem 0.5rem;"></select>
                  </div>
                  <div style="margin-top:1rem;display:flex;gap:0.5rem;justify-content:center;">
                    <button type="button" class="btn btn-white" id="btnStopQr">Dừng quét</button>
                    <button type="button" class="btn btn-primary" id="btnUseQr" style="display:none;">Sử dụng mã đã quét</button>
                  </div>
                  <div id="qrStatus" style="margin-top:0.75rem;color:var(--taupe-500);text-align:center;">Hãy cho phép truy cập camera và hướng camera vào mã QR.</div>
                </div>
              </div>
            </div>
            <!-- QR Create Modal -->
            <div class="modal-overlay" id="qrCreateModal">
              <div class="checkout-modal" style="max-width:720px;">
                <div class="modal-header">
                  <h3 class="modal-title">Thanh Toán QR Code</h3>
                  <button class="modal-close" onclick="closeQrCreator()">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="modal-body">
                  <div style="display:flex;gap:1.5rem;align-items:center;justify-content:center;flex-direction:column;padding:1rem;">
                    <div id="qrFormFields" style="flex:1;min-width:280px;display:none;">
                      <h4 style="margin-bottom:0.5rem;font-weight:700;">Thông Tin Thanh Toán</h4>
                      <div class="form-group">
                        <label class="form-label">Số tiền (VND)</label>
                        <input id="qrCreateAmount" class="form-input" type="number" step="1000" min="0" value="" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Tên ngân hàng</label>
                        <input id="qrCreateBank" class="form-input" type="text" placeholder="Vietcombank, Techcombank, MBBank..." />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Số tài khoản</label>
                        <input id="qrCreateAccount" class="form-input" type="text" placeholder="0123456789" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Tên chủ tài khoản</label>
                        <input id="qrCreateName" class="form-input" type="text" placeholder="NGUYEN VAN A" />
                      </div>
                      <div class="form-group">
                        <label class="form-label">Nội dung chuyển khoản</label>
                        <input id="qrCreateNote" class="form-input" type="text" placeholder="Thanh toán đơn hàng #123" />
                      </div>

                      <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
                        <button type="button" class="btn btn-primary" id="btnGenerateQr" style="display:none;">Tạo Mã QR</button>
                        <button type="button" class="btn btn-white" id="btnClearQr" style="display:none;">Xóa</button>
                      </div>
                    </div>

                    <div style="text-align:center;">
                      <div id="qrCanvas" style="background:white;padding:1.5rem;border-radius:12px;display:inline-block;box-shadow:var(--shadow-lg);"></div>
                      <div id="qrCreateInfo" style="margin-top:1rem;color:var(--taupe-500);font-size:1rem;"></div>
                      <div style="margin-top:0.75rem;display:flex;gap:0.5rem;justify-content:center;">
                        <a id="qrDownload" class="btn btn-white" style="display:none;">Tải ảnh</a>
                        <button id="qrUseForPayment" class="btn btn-primary" style="display:none;">Sử dụng mã này</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
      </div>
    </div>

<script src="https://unpkg.com/html5-qrcode@2.4.9/minified/html5-qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Quick View (Xem nhanh) sản phẩm
function openHomeQuickView(productId) {
  var card = document.getElementById('prod-' + productId);
  if (!card) return;
  var name = card.getAttribute('data-name') || '';
  var price = card.getAttribute('data-price') || '';
  var img = card.getAttribute('data-img') || '';
  var desc = card.getAttribute('data-description');
  var category = card.getAttribute('data-category') || '';
  document.getElementById('qvName').textContent = name;
  document.getElementById('qvPrice').innerHTML = formatPrice(Number(price)) + ' ₫';
  document.getElementById('qvImg').src = img;
  var qvDesc = document.getElementById('qvDesc');
  if (qvDesc) {
    if (desc && desc.trim()) {
      qvDesc.style.fontStyle = '';
      // Giữ xuống dòng nếu có
      qvDesc.innerHTML = desc.replace(/\n/g, '<br>');
    } else {
      qvDesc.style.fontStyle = 'italic';
      qvDesc.innerHTML = 'Không có mô tả.';
    }
  }
  document.getElementById('qvCategory').textContent = category;
  var modal = document.getElementById('quickViewModal');
  modal.style.display = 'block';
  setTimeout(function(){ modal.classList.add('active'); }, 10);
  // ...existing code...
}

function closeQuickView() {
  var modal = document.getElementById('quickViewModal');
  if (!modal) return;
  modal.classList.remove('active');
  setTimeout(function(){ modal.style.display = 'none'; }, 300);
}
// ==========================================
// GLOBAL FORMAT PRICE FUNCTION
// ==========================================
function formatPrice(price) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
}

(function(){
  'use strict';
  
  // ===== HIGHLIGHT ACTIVE NAV LINK =====
  (function() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav a');
    
    navLinks.forEach(link => {
      const linkPath = new URL(link.href).pathname;
      
      if (currentPath === linkPath || 
          (currentPath === '/' && linkPath === '/') ||
          (currentPath.includes('trangchu.php') && linkPath === '/') ||
          (currentPath.includes('sale.php') && link.href.includes('sale.php')) ||
          (currentPath.includes('san-pham.php') && link.href.includes('san-pham.php')) ||
          (currentPath.includes('lienhe.php') && link.href.includes('lienhe.php'))) {
        link.classList.add('active');
      }
    });
  })();
  
  // shop payment info from PHP
  const SHOP_BANK = '<?php echo addslashes($shop_bank); ?>';
  const SHOP_ACCOUNT = '<?php echo addslashes($shop_account); ?>';
  const SHOP_OWNER = '<?php echo addslashes($shop_owner); ?>';
  let currentOrderId = null;
  
  // ===== BANNER SLIDER =====
  (function() {
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const totalSlides = slides.length;
    let slideInterval;

    function showSlide(index) {
      slides.forEach(slide => {
        slide.classList.remove('active', 'prev');
      });
      dots.forEach(dot => {
        dot.classList.remove('active');
      });

      if (slides[currentSlide]) {
        slides[currentSlide].classList.add('prev');
      }

      currentSlide = index;
      if (currentSlide >= totalSlides) currentSlide = 0;
      if (currentSlide < 0) currentSlide = totalSlides - 1;

      slides[currentSlide].classList.add('active');
      dots[currentSlide].classList.add('active');
    }

    window.nextSlide = function() {
      showSlide(currentSlide + 1);
      resetSlideInterval();
    };

    window.prevSlide = function() {
      showSlide(currentSlide - 1);
      resetSlideInterval();
    };

    window.goToSlide = function(index) {
      showSlide(index);
      resetSlideInterval();
    };

    function resetSlideInterval() {
      clearInterval(slideInterval);
      slideInterval = setInterval(() => {
        showSlide(currentSlide + 1);
      }, 5000);
    }

    // Start auto-slide
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
  })();

  // ===== SIDEBAR FILTER FUNCTIONS =====
  
  // Filter by category (radio button change)
  window.filterByCategory = function(categoryId) {
    const params = new URLSearchParams(window.location.search);
    
    if (categoryId === 'all') {
      params.delete('category');
    } else {
      params.set('category', categoryId);
    }
    
    // Preserve other filters
    const priceMin = document.getElementById('priceMin');
    const priceMax = document.getElementById('priceMax');
    if (priceMin && priceMin.value) params.set('min_price', priceMin.value);
    if (priceMax && priceMax.value) params.set('max_price', priceMax.value);
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
      params.set('search', searchInput.value.trim());
    }
    
    window.location.search = params.toString();
  };
  
  // Apply price range filter
  window.applyFilters = function() {
    const priceMin = document.getElementById('priceMin');
    const priceMax = document.getElementById('priceMax');
    const params = new URLSearchParams(window.location.search);
    
    // Validate numeric inputs
    if (priceMin && priceMin.value) {
      const min = parseInt(priceMin.value);
      if (!isNaN(min) && min >= 0) {
        params.set('min_price', min);
      }
    } else {
      params.delete('min_price');
    }
    
    if (priceMax && priceMax.value) {
      const max = parseInt(priceMax.value);
      if (!isNaN(max) && max >= 0) {
        params.set('max_price', max);
      }
    } else {
      params.delete('max_price');
    }
    
    // Preserve category filter
    const categoryRadio = document.querySelector('input[name="category"]:checked');
    if (categoryRadio && categoryRadio.value !== 'all') {
      params.set('category', categoryRadio.value);
    }
    
    // Preserve search
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
      params.set('search', searchInput.value.trim());
    }
    
    window.location.search = params.toString();
  };
  
  // Clear all filters
  window.clearFilters = function() {
    // Clear price inputs
    const priceMin = document.getElementById('priceMin');
    const priceMax = document.getElementById('priceMax');
    if (priceMin) priceMin.value = '';
    if (priceMax) priceMax.value = '';
    
    // Reset category to 'all'
    const allRadio = document.querySelector('input[name="category"][value="all"]');
    if (allRadio) allRadio.checked = true;
    
    // Clear search
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    
    // Reload page without any filters
    window.location.href = window.location.pathname;
  };
  
  // Search products (debounced)
  let searchTimeout;
  window.searchProducts = function() {
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(function() {
      const searchInput = document.getElementById('searchInput');
      const params = new URLSearchParams(window.location.search);
      
      if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
      } else {
        params.delete('search');
      }
      
      // Preserve category filter
      const categoryRadio = document.querySelector('input[name="category"]:checked');
      if (categoryRadio && categoryRadio.value !== 'all') {
        params.set('category', categoryRadio.value);
      }
      
      // Preserve price filters
      const priceMin = document.getElementById('priceMin');
      const priceMax = document.getElementById('priceMax');
      if (priceMin && priceMin.value) params.set('min_price', priceMin.value);
      if (priceMax && priceMax.value) params.set('max_price', priceMax.value);
      
      window.location.search = params.toString();
    }, 500); // Wait 500ms after user stops typing
  };
  
  // Initialize filter values from URL on page load
  (function() {
    const params = new URLSearchParams(window.location.search);
    
    // Set category radio
    const category = params.get('category');
    if (category) {
      const radio = document.querySelector(`input[name="category"][value="${category}"]`);
      if (radio) radio.checked = true;
    }
    
    // Set price range
    const minPrice = params.get('min_price');
    const maxPrice = params.get('max_price');
    const priceMin = document.getElementById('priceMin');
    const priceMax = document.getElementById('priceMax');
    if (priceMin && minPrice) priceMin.value = minPrice;
    if (priceMax && maxPrice) priceMax.value = maxPrice;
    
    // Set search input
    const search = params.get('search');
    const searchInput = document.getElementById('searchInput');
    if (searchInput && search) searchInput.value = search;
  })();

  // ===== QUANTITY SELECTOR =====
  document.querySelectorAll('.qty-minus').forEach(btn => {
    btn.addEventListener('click', function() {
      const productId = this.dataset.product;
      const input = document.querySelector(`.qty-input[data-product="${productId}"]`);
      if (input) {
        const val = parseInt(input.value) || 1;
        if (val > 1) input.value = val - 1;
      }
    });
  });

  document.querySelectorAll('.qty-plus').forEach(btn => {
    btn.addEventListener('click', function() {
      const productId = this.dataset.product;
      const input = document.querySelector(`.qty-input[data-product="${productId}"]`);
      if (input) {
        const val = parseInt(input.value) || 1;
        if (val < 99) input.value = val + 1;
      }
    });
  });

  // Favorites removed — feature disabled per request

  // ===== QUANTITY FUNCTIONS (for onclick handlers) =====
  window.decreaseQty = function(btn) {
    const input = btn.parentElement.querySelector('.qty-input');
    if (input) {
      const val = parseInt(input.value) || 1;
      if (val > 1) input.value = val - 1;
    }
  };
  
  window.increaseQty = function(btn) {
    const input = btn.parentElement.querySelector('.qty-input');
    if (input) {
      const val = parseInt(input.value) || 1;
      if (val < 99) input.value = val + 1;
    }
  };

  // ===== CART =====
  const cartKey = 'myshop_cart_count';
  const cartItemsKey = 'myphuongshop_cart_items';
  
  function getCart() {
    return parseInt(localStorage.getItem(cartKey) || '0');
  }
  
  function getCartItems() {
    try {
      return JSON.parse(localStorage.getItem(cartItemsKey) || '[]');
    } catch(e) {
      return [];
    }
  }
  
  function setCart(n) {
    localStorage.setItem(cartKey, String(n));
    updateCartBadge();
  }
  
  function saveCartItems(items) {
    localStorage.setItem(cartItemsKey, JSON.stringify(items));
    const count = items.reduce((sum, item) => sum + item.quantity, 0);
    setCart(count);
  }
  
  function updateCartBadge() {
    // Đọc trực tiếp từ cart items để đảm bảo chính xác
    const items = getCartItems();
    const n = items.reduce((sum, item) => sum + (item.quantity || 0), 0);
    const badge = document.querySelector('.cart-badge');
    if (badge) {
      badge.textContent = n;
      badge.style.display = n > 0 ? 'flex' : 'none';
    }
    // Đồng bộ lại cartKey
    localStorage.setItem(cartKey, String(n));
  }
  
  // Alias for updateCartBadge (compatibility)
  function updateCartUI() {
    updateCartBadge();
  }

  // Show cart notification
  function showCartNotification(productName, qty) {
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 6rem;
      right: 2rem;
      background: linear-gradient(135deg, #2ecc71, #27ae60);
      color: white;
      padding: 1rem 1.5rem;
      border-radius: var(--radius-lg);
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      z-index: 1000;
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
    
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    }, 4000);
  }

  // Add animation styles
  if (!document.getElementById('cart-animations')) {
    const style = document.createElement('style');
    style.id = 'cart-animations';
    style.textContent = `
      @keyframes slideInRight {
        from { opacity: 0; transform: translateX(100%); }
        to { opacity: 1; transform: translateX(0); }
      }
      @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
      }
    `;
    document.head.appendChild(style);
  }

  // ===== ADD TO CART =====
  document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      // Kiểm tra đăng nhập
      <?php if (!isset($_SESSION['user_id'])): ?>
      if (confirm('Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng. Đăng nhập ngay?')) {
        window.location.href = 'dangnhap.php';
      }
      return;
      <?php endif; ?>
      
      const id = Number(this.dataset.id);
      const container = document.getElementById('prod-' + id);
      
      const qtyInput = container.querySelector('.qty-input');
      const qty = qtyInput ? parseInt(qtyInput.value || 1) : 1;
      
      // Get product data
      const name = container.dataset.name || 'Sản phẩm';
      const price = parseFloat(container.dataset.price || 0);
      const img = container.dataset.img || '';
      const category = container.dataset.category || 'Thời trang';
      
      // Add to cart items
      const cartItems = getCartItems();
      const existingIndex = cartItems.findIndex(item => 
        item.id === id
      );
      
      if (existingIndex >= 0) {
        cartItems[existingIndex].quantity = Math.min(99, cartItems[existingIndex].quantity + qty);
      } else {
        cartItems.push({
          id,
          name,
          price,
          image: img,
          category,
          quantity: qty
        });
      }
      
      saveCartItems(cartItems);
      
      // Show notification
      showCartNotification(name, qty);
      
      // Visual feedback on button
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="fas fa-check"></i> Đã thêm!';
      this.style.background = 'var(--accent-sage)';
      
      setTimeout(() => {
        this.innerHTML = originalText;
        this.style.background = '';
      }, 1500);
      
      // Reset quantity to 1
      if (qtyInput) qtyInput.value = 1;
    });
  });

  // ===== ADD TO CART (for .add-cart-btn) =====
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
    const id = Number(card.dataset.id || card.getAttribute('data-id'));
    const name = card.dataset.name || card.querySelector('.product-name')?.textContent?.trim() || 'Sản phẩm';
    const priceEl = card.querySelector('.product-price');
    const priceRaw = priceEl ? priceEl.textContent.trim() : '0';
    const price = parseInt((priceRaw || '').replace(/[^0-9]/g,'')) || 0;
    const imgEl = card.querySelector('.product-image img');
    const image = imgEl ? imgEl.src : (card.dataset.img || '');
    const category = card.dataset.category || '';
    const qtyInput = card.querySelector('.qty-input');
    const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    // Add to cart
    const items = getCartItems();
    const existingIndex = items.findIndex(item => item.id === id);
    
    if (existingIndex > -1) {
      items[existingIndex].quantity = Math.min(99, items[existingIndex].quantity + quantity);
    } else {
      items.push({
        id: id,
        name: name,
        price: price,
        image: image,
        quantity: quantity,
        category: category
      });
    }
    
    saveCartItems(items);
    updateCartUI();
    showCartNotification(name, quantity);
    
    // Visual feedback on button
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Đã thêm!';
    btn.style.background = 'var(--accent-sage, #28a745)';
    
    setTimeout(() => {
      btn.innerHTML = originalText;
      btn.style.background = '';
    }, 1500);
    
    // Reset quantity to 1
    if (qtyInput) qtyInput.value = 1;
  });

  // ===== BUY NOW / CHECKOUT =====
  let checkoutData = {};

  window.closeCheckoutModal = function() {
    document.getElementById('checkoutModal').classList.remove('active');
    const qrSection = document.getElementById('qrCodeSection');
    if (qrSection) qrSection.style.display = 'none';
  };



  // Handler for buy-now-btn using event delegation
  document.addEventListener('click', function(e) {
    const btn = e.target.closest && e.target.closest('.buy-now-btn');
    if (!btn) return;
    e.preventDefault();
    
    // Kiểm tra đăng nhập
    <?php if (!isset($_SESSION['user_id'])): ?>
    if (confirm('Bạn cần đăng nhập để mua hàng. Đăng nhập ngay?')) {
      window.location.href = 'dangnhap.php';
    }
    return;
    <?php endif; ?>
    
    const card = btn.closest('.product-card');
    if (!card) return;
    
    const id = Number(card.dataset.id || card.getAttribute('data-id'));
    const name = card.dataset.name || card.querySelector('.product-name')?.textContent?.trim() || 'Sản phẩm';
    const priceEl = card.querySelector('.product-price');
    const priceRaw = priceEl ? priceEl.textContent.trim() : '0';
    const price = parseInt((priceRaw || '').replace(/[^0-9]/g,'')) || 0;
    const imgEl = card.querySelector('.product-image img');
    const image = imgEl ? imgEl.src : (card.dataset.img || '');
    const category = card.dataset.category || '';
    const qtyInput = card.querySelector('.qty-input');
    const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
    
    // Thêm vào giỏ hàng
    const cartItems = getCartItems();
    const existingIndex = cartItems.findIndex(item => item.id === id);
    
    if (existingIndex >= 0) {
      cartItems[existingIndex].quantity = Math.min(99, cartItems[existingIndex].quantity + quantity);
    } else {
      cartItems.push({
        id: id,
        name: name,
        price: price,
        image: image,
        category: category,
        quantity: quantity
      });
    }
    saveCartItems(cartItems);

    // Đánh dấu sản phẩm vừa mua
    sessionStorage.setItem('justBoughtId', id);
    // Chuyển đến trang giỏ hàng
    window.location.href = 'giohang.php';
  });

  function openCheckoutModal() {
    const modal = document.getElementById('checkoutModal');
    
    document.getElementById('checkoutImg').src = checkoutData.image;
    document.getElementById('checkoutName').textContent = checkoutData.name;
    document.getElementById('checkoutQtyValue').textContent = checkoutData.quantity;
    
    updateCheckoutSummary();
    
    // generate a short unique order id
    const orderId = 'ORD' + String(Date.now()).slice(-8) + Math.floor(Math.random() * 900 + 100);
    currentOrderId = orderId;
    const orderInput = document.getElementById('orderIdInput');
    if (orderInput) orderInput.value = orderId;

    // Hide QR and Bank sections when modal opens
    const qrSection = document.getElementById('qrCodeSection');
    const bankSection = document.getElementById('bankInfoSection');
    if (qrSection) qrSection.style.display = 'none';
    if (bankSection) bankSection.style.display = 'none';

    // Add payment method change listener
    const paymentSelect = document.getElementById('paymentSelect');
    if (paymentSelect) {
      // Remove old listener if exists
      const newSelect = paymentSelect.cloneNode(true);
      paymentSelect.parentNode.replaceChild(newSelect, paymentSelect);
      
      newSelect.addEventListener('change', function() {
        const qrSection = document.getElementById('qrCodeSection');
        const bankSection = document.getElementById('bankInfoSection');
        
        // Hide both first
        if (qrSection) qrSection.style.display = 'none';
        if (bankSection) bankSection.style.display = 'none';
        
        // Show appropriate section
        if (this.value === 'qr' && qrSection) {
          // Update QR payment info
          const subtotal = checkoutData.price * checkoutData.quantity;
          const shipping = subtotal < 50000 ? 5000 : 0;
          const total = subtotal + shipping;
          const productCode = 'SP' + checkoutData.id + Date.now().toString().slice(-6);
          updateQRInfo(total, productCode);
          
          qrSection.style.display = 'block';
          qrSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else if (this.value === 'bank' && bankSection) {
          bankSection.style.display = 'block';
          bankSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    }

    // Tự động điền thông tin khách hàng đã lưu
    loadCustomerInfo();

    // Reset voucher khi mở modal
    resetVoucherSP();
    
    // Hide voucher list
    const voucherList = document.getElementById('voucherListSP');
    if (voucherList) voucherList.style.display = 'none';

    modal.classList.add('active');
  }

  function loadCustomerInfo() {
    try {
      const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
      if (!userId) return;
      
      const form = document.getElementById('checkoutForm');
      if (!form) return;
      
      // Lấy thông tin đã lưu từ localStorage
      const savedInfo = localStorage.getItem('customer_info_' + userId);
      if (savedInfo) {
        const info = JSON.parse(savedInfo);
        if (info.fullname) form.fullname.value = info.fullname;
        if (info.phone) form.phone.value = info.phone;
        if (info.email) form.email.value = info.email;
        if (info.address) form.address.value = info.address;
        if (info.city) form.city.value = info.city;
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
        fullname: data.fullname || '',
        phone: data.phone || '',
        email: data.email || '',
        address: data.address || '',
        city: data.city || ''
      };
      localStorage.setItem('customer_info_' + userId, JSON.stringify(info));
    } catch (e) {
      console.error('Lỗi khi lưu thông tin:', e);
    }
  }

  // QR code is now displayed as a static image when user clicks the button

  window.adjustCheckoutQty = function(change) {
    const newQty = checkoutData.quantity + change;
    if (newQty >= 1 && newQty <= 99) {
      checkoutData.quantity = newQty;
      document.getElementById('checkoutQtyValue').textContent = newQty;
      updateCheckoutSummary();
      // Reset voucher when quantity changes
      if (typeof resetVoucherSP === 'function') {
        resetVoucherSP();
      }
    }
  };

  function updateCheckoutSummary() {
    const data = window.checkoutData || checkoutData;
    const subtotal = data.price * data.quantity;
    const shipping = subtotal < 50000 ? 30000 : 0;
    const discount = parseInt(document.getElementById('giam_giaSP')?.value || 0);
    const total = subtotal + shipping - discount;
    
    document.getElementById('checkoutPrice').textContent = formatPrice(subtotal);
    document.getElementById('summarySubtotal').textContent = formatPrice(subtotal);
    document.getElementById('summaryShipping').textContent = shipping === 0 ? 'Miễn phí' : formatPrice(shipping);
    document.getElementById('summaryTotal').textContent = formatPrice(total);
  }

  // Expose to global scope for voucher functions
  window.updateCheckoutSummary = updateCheckoutSummary;

  window.handleCheckoutSubmit = function(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Calculate totals
    const subtotal = checkoutData.price * checkoutData.quantity;
    const shipping = subtotal < 50000 ? 30000 : 0;
    
    // Get voucher discount
    const voucherDiscount = parseInt(document.getElementById('giam_giaSP')?.value || 0);
    const voucherCode = document.getElementById('ma_voucherSP')?.value || '';
    
    // Calculate total with discount
    const total = subtotal + shipping - voucherDiscount;
    
    const orderData = {
      items: [{
        id: checkoutData.id,
        name: checkoutData.name,
        price: checkoutData.price,
        quantity: checkoutData.quantity,
        size: checkoutData.size,
        image: checkoutData.image,
        category: checkoutData.category
      }],
      customer: {
        fullname: formData.get('fullname'),
        phone: formData.get('phone'),
        email: formData.get('email'),
        address: formData.get('address'),
        city: formData.get('city'),
        payment: formData.get('payment'),
        note: formData.get('note')
      },
      voucher: {
        code: voucherCode,
        discount: voucherDiscount
      },
      totals: { subtotal, shipping, discount: voucherDiscount, total },
      timestamp: new Date().toISOString()
    };

    console.log('Order Data:', orderData);
    
    // Gửi đơn hàng lên server
    fetch('xu_ly_don_hang.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(result => {
      console.log('Result:', result);
      if (result.success) {
        // Lưu thông tin khách hàng để dùng cho lần sau
        saveCustomerInfo(orderData.customer);
        alert(`✅ Đặt hàng thành công!\n\nMã đơn hàng: ${result.orderCode}\nTổng thanh toán: ${formatPrice(total)}\n\nCảm ơn bạn đã mua hàng!\nChúng tôi sẽ liên hệ với bạn sớm nhất!`);
        closeCheckoutModal();
        event.target.reset();
      } else {
        alert('❌ Lỗi: ' + result.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('❌ Có lỗi xảy ra khi đặt hàng: ' + error.message);
    });
    
    return false;
  };

  // Update QR payment info
  function updateQRInfo(amount, content) {
    const amountNum = Math.round(amount);
    const productCode = content || 'SP' + Date.now().toString().slice(-8);
    
    // Generate VietQR URL
    const accountNo = '0325048679';
    const accountName = 'TRUONG%20THI%20MY%20PHUONG';
    const bankCode = 'MB';
    const description = encodeURIComponent(productCode);
    const qrUrl = `https://img.vietqr.io/image/${bankCode}-${accountNo}-compact2.png?amount=${amountNum}&addInfo=${description}&accountName=${accountName}`;
    
    // Update QR image
    const qrImage = document.getElementById('qrCodeImage');
    if (qrImage) qrImage.src = qrUrl;
  }

  // Khởi tạo và đồng bộ cart badge khi trang load
  (function initCart() {
    const items = getCartItems();
    const actualCount = items.reduce((sum, item) => sum + (item.quantity || 0), 0);
    const storedCount = parseInt(localStorage.getItem(cartKey) || '0');
    
    // Nếu count không khớp, đồng bộ lại
    if (actualCount !== storedCount) {
      localStorage.setItem(cartKey, String(actualCount));
    }
    
    updateCartBadge();
  })();

  // ===== QUICK VIEW (full description + reviews) =====
  window.openQuickView = function(productId) {
    const container = document.getElementById('prod-' + productId);
    if (!container) return;
    const img = container.dataset.img || '';
    const name = container.dataset.name || '';
    const category = container.dataset.category || '';
    const priceVal = parseFloat(container.dataset.price || 0) || 0;
    const priceFormatted = formatPrice(priceVal);

    document.getElementById('qvImg').src = img;
    document.getElementById('qvName').textContent = name;
    document.getElementById('qvCategory').textContent = category;
    document.getElementById('qvPrice').textContent = priceFormatted;

    // full description HTML is inside hidden .product-full-desc inside the card
    const fullDescEl = container.querySelector('.product-full-desc');
    const qvDesc = document.getElementById('qvDesc');
    if (fullDescEl && qvDesc) {
      qvDesc.innerHTML = fullDescEl.innerHTML || '<em>Không có mô tả.</em>';
    } else if (qvDesc) {
      qvDesc.innerHTML = '<em>Không có mô tả.</em>';
    }

    document.getElementById('quickViewModal').classList.add('active');
  };

  window.closeQuickView = function() {
    document.getElementById('quickViewModal').classList.remove('active');
  };

  // bind quick-view buttons only
  // Đã xóa chức năng quick-view

  // close quick view on overlay click or Esc
  const quickViewOverlay = document.getElementById('quickViewModal');
  if (quickViewOverlay) {
    quickViewOverlay.addEventListener('click', function(e) {
      if (e.target === quickViewOverlay) closeQuickView();
    });
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeQuickView();
    }
  });

  // ===== QR SCANNER =====
  let html5QrcodeScanner = null;
  let lastScanned = null;
  let lastFailureTime = 0;

  function openQrScanner() {
    document.getElementById('qrStatus').textContent = 'Đang chuẩn bị camera...';
    document.getElementById('qrScannerModal').classList.add('active');
    startQrScanner();
  }

  function closeQrScanner() {
    stopQrScanner();
    document.getElementById('qrScannerModal').classList.remove('active');
  }

  function startQrScanner() {
    const qrReader = document.getElementById('qr-reader');
    const camSelect = document.getElementById('qrCameraSelect');
    if (!qrReader || typeof Html5Qrcode === 'undefined') {
      document.getElementById('qrStatus').textContent = 'Trình duyệt không hỗ trợ quét QR hoặc thiếu quyền camera.';
      return;
    }

    // If instance exists, stop it first to re-init with chosen camera
    if (html5QrcodeScanner) {
      try { html5QrcodeScanner.clear(); } catch(e) {}
      html5QrcodeScanner = null;
    }

    // list cameras and prefer back/rear if available
    Html5Qrcode.getCameras().then(cameras => {
      if (!cameras || cameras.length === 0) {
        document.getElementById('qrStatus').textContent = 'Không tìm thấy camera trên thiết bị này.';
        return;
      }

      // populate select
      camSelect.innerHTML = '';
      cameras.forEach(cam => {
        const opt = document.createElement('option');
        opt.value = cam.id;
        opt.textContent = cam.label || cam.id;
        camSelect.appendChild(opt);
      });

      // try to pick a back-facing camera by label if possible
      let preferred = cameras[0].id;
      for (const cam of cameras) {
        if (/back|rear|environment/i.test(cam.label || '')) { preferred = cam.id; break; }
      }

      // start scanner
      html5QrcodeScanner = new Html5Qrcode('qr-reader');
      const startWith = () => {
        const cameraId = camSelect.value || preferred;
        document.getElementById('qrStatus').textContent = 'Đang mở camera...';
        html5QrcodeScanner.start(cameraId, { fps: 10, qrbox: 250 }, onScanSuccess, onScanFailure)
          .then(() => {
            document.getElementById('qrStatus').textContent = 'Hướng camera vào mã QR để quét.';
          })
          .catch(err => {
            document.getElementById('qrStatus').textContent = 'Lỗi camera: ' + String(err);
          });
      };

      // start initially
      startWith();

      // changing camera selection restarts scanner
      camSelect.onchange = function() {
        if (!html5QrcodeScanner) return;
        html5QrcodeScanner.stop().then(() => {
          try { html5QrcodeScanner.clear(); } catch(e) {}
          html5QrcodeScanner = null;
          startQrScanner();
        }).catch(err => {
          document.getElementById('qrStatus').textContent = 'Không thể chuyển camera: ' + err;
        });
      };

    }).catch(err => {
      document.getElementById('qrStatus').textContent = 'Lỗi khi truy vấn camera: ' + String(err);
    });
    
  }

  function stopQrScanner() {
    if (html5QrcodeScanner) {
      html5QrcodeScanner.stop().then(() => {
        // clear scanner
      }).catch(()=>{});
      // destroy instance to free camera
      try { html5QrcodeScanner.clear(); } catch(e) {}
      html5QrcodeScanner = null;
    }
  }

  function onScanSuccess(decodedText, decodedResult) {
    // called when a QR code is scanned successfully
    lastScanned = decodedText;
    document.getElementById('qrStatus').textContent = 'Đã quét: ' + decodedText;
    document.getElementById('qrPreview').textContent = decodedText;
    document.getElementById('qrData').value = decodedText;
    document.getElementById('btnUseQr').style.display = 'inline-block';
  }

  function onScanFailure(error) {
    // Throttle failure messages to avoid spamming the UI
    const now = Date.now();
    if (now - lastFailureTime < 800) return;
    lastFailureTime = now;

    const statusEl = document.getElementById('qrStatus');
    if (!statusEl) return;

    // Show helpful message — not all failures are important (frame noise),
    // but when repeated show a hint for the user.
    statusEl.textContent = 'Chưa quét được mã. Hãy giữ camera ổn định và đảm bảo mã nằm trong khung.';
  }

  // UI bindings - initial attempt (will rebind when modal opens)
  const btnOpenQr = document.getElementById('btnOpenQrScanner');
  if (btnOpenQr) {
    console.log('btnOpenQrScanner found initially, binding click');
    btnOpenQr.addEventListener('click', function(e){ 
      console.log('btnOpenQrScanner clicked (initial binding)!');
      e.preventDefault();
      e.stopPropagation();
      openShopQr(); 
    });
  } else {
    console.warn('btnOpenQrScanner not found initially (will bind when modal opens)');
  }

  // Tạo QR button removed - Quét QR now shows shop QR

  const btnStopQr = document.getElementById('btnStopQr');
  if (btnStopQr) btnStopQr.addEventListener('click', function(){ closeQrScanner(); });

  const btnUseQr = document.getElementById('btnUseQr');
  if (btnUseQr) btnUseQr.addEventListener('click', function(){
    // keep scanned data and close
    if (lastScanned) {
      document.getElementById('qrData').value = lastScanned;
      document.getElementById('qrPreview').textContent = lastScanned;
    }
    closeQrScanner();
  });

  // also close scanner on overlay click and Esc
  const qrOverlay = document.getElementById('qrScannerModal');
  if (qrOverlay) {
    qrOverlay.addEventListener('click', function(e) {
      if (e.target === qrOverlay) closeQrScanner();
    });
  }
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeQrScanner(); });

  // When payment select changes to QR, open scanner as shortcut
  const paymentSelect = document.getElementById('paymentSelect');
  if (paymentSelect) paymentSelect.addEventListener('change', function(){ if (this.value === 'qr') openQrScanner(); });

  // ===== QR CREATOR =====
  let qrCodeInstance = null;

  // Open QR modal with shop payment QR pre-generated
  window.openShopQr = function() {
    console.log('openShopQr called', checkoutData);
    
    if (!checkoutData) {
      alert('Vui lòng chọn sản phẩm trước');
      return;
    }

    const amountEl = document.getElementById('qrCreateAmount');
    const bankEl = document.getElementById('qrCreateBank');
    const accEl = document.getElementById('qrCreateAccount');
    const nameEl = document.getElementById('qrCreateName');
    const noteEl = document.getElementById('qrCreateNote');

    const subtotal = checkoutData.price * (checkoutData.qty || 1);
    if (amountEl) amountEl.value = subtotal;
    if (bankEl) bankEl.value = SHOP_BANK;
    if (accEl) accEl.value = SHOP_ACCOUNT;
    if (nameEl) nameEl.value = SHOP_OWNER;

    const orderId = currentOrderId || ('ORD'+String(Date.now()).slice(-8)+Math.floor(Math.random()*900+100));
    const nameField = document.querySelector('input[name="fullname"]');
    const custName = (nameField && nameField.value.trim()) ? nameField.value.trim() : 'Khách hàng';
    if (noteEl) noteEl.value = 'Đơn hàng ' + orderId + ' - KH: ' + custName;

    // hide form fields completely
    const formFields = document.getElementById('qrFormFields');
    if (formFields) formFields.style.display = 'none';

    // clear previous QR
    const canvas = document.getElementById('qrCanvas');
    if (canvas) canvas.innerHTML = '';
    const info = document.getElementById('qrCreateInfo');
    if (info) info.textContent = 'Đang tạo mã QR...';

    console.log('Opening modal...');
    // show modal
    const modal = document.getElementById('qrCreateModal');
    if (modal) {
      modal.classList.add('active');
      modal.style.display = 'flex';
      console.log('Modal opened');
    } else {
      console.error('Modal not found!');
      alert('Không tìm thấy modal QR!');
      return;
    }

    // generate QR immediately
    setTimeout(() => {
      console.log('Generating QR...');
      try { generateQr(); } catch(e) { console.error('generateQr failed', e); }
    }, 150);
  }

  function openQrCreator() {
    // prefill amount with subtotal if available
    const subtotalEl = document.getElementById('summarySubtotal');
    const amountEl = document.getElementById('qrCreateAmount');
    if (subtotalEl && amountEl && checkoutData && checkoutData.price) {
      const subtotal = checkoutData.price * (checkoutData.qty || 1);
      amountEl.value = subtotal;
    }
    document.getElementById('qrCreateModal').classList.add('active');
  }

  function closeQrCreator() {
    document.getElementById('qrCreateModal').classList.remove('active');
  }

  function clearQrCreator() {
    const canvas = document.getElementById('qrCanvas');
    canvas.innerHTML = '';
    document.getElementById('qrCreateInfo').textContent = '';
    document.getElementById('qrDownload').style.display = 'none';
    document.getElementById('qrUseForPayment').style.display = 'none';
    qrCodeInstance = null;
  }

  function generateQr() {
    const amount = document.getElementById('qrCreateAmount').value || '';
    const bank = document.getElementById('qrCreateBank').value || '';
    const account = document.getElementById('qrCreateAccount').value || '';
    const name = document.getElementById('qrCreateName').value || '';
    const note = document.getElementById('qrCreateNote').value || '';

    if (!amount || Number(amount) <= 0) {
      alert('Vui lòng nhập số tiền hợp lệ.');
      return;
    }

    // Build a shorter payload - just essential info
    const payload = bank + '|' + account + '|' + name + '|' + amount + '|' + note;

    // render QR using qrcodejs into #qrCanvas
    const canvasEl = document.getElementById('qrCanvas');
    canvasEl.innerHTML = '';
    qrCodeInstance = new QRCode(canvasEl, {
      text: payload,
      width: 320,
      height: 320,
      colorDark : '#000000',
      colorLight : '#ffffff',
      correctLevel : QRCode.CorrectLevel.L
    });

    document.getElementById('qrCreateInfo').textContent = bank + ' • ' + account + ' • ' + name + ' • ' + new Intl.NumberFormat('vi-VN').format(Number(amount)) + '₫';

    // prepare download link (convert canvas or img to dataURL)
    setTimeout(() => {
      // qrcodejs generates a child <img> or <canvas> inside qrCanvas
      const img = canvasEl.querySelector('img');
      let dataUrl = null;
      if (img) dataUrl = img.src;
      else {
        const canvasChild = canvasEl.querySelector('canvas');
        if (canvasChild) dataUrl = canvasChild.toDataURL('image/png');
      }
      if (dataUrl) {
        const dl = document.getElementById('qrDownload');
        dl.href = dataUrl;
        dl.download = 'payment-qr.png';
        dl.style.display = 'inline-block';
        document.getElementById('qrUseForPayment').style.display = 'inline-block';
      }
    }, 300);
  }

  // wire creator buttons
  const btnGen = document.getElementById('btnGenerateQr');
  if (btnGen) btnGen.addEventListener('click', generateQr);
  const btnClear = document.getElementById('btnClearQr');
  if (btnClear) btnClear.addEventListener('click', clearQrCreator);

  const dlLink = document.getElementById('qrDownload');
  if (dlLink) dlLink.addEventListener('click', function(){ /* native download via href */ });

  const btnUseQrPay = document.getElementById('qrUseForPayment');
  if (btnUseQrPay) btnUseQrPay.addEventListener('click', function(){
    // take the generated payload and set into qrData, then close
    const canvasEl = document.getElementById('qrCanvas');
    const img = canvasEl.querySelector('img');
    let dataUrl = null;
    if (img) dataUrl = img.src;
    else {
      const canvasChild = canvasEl.querySelector('canvas');
      if (canvasChild) dataUrl = canvasChild.toDataURL('image/png');
    }
    if (dataUrl) {
      document.getElementById('qrData').value = dataUrl;
      document.getElementById('qrPreview').textContent = 'Mã QR đã tạo (dữ liệu hình ảnh)';
    }
    closeQrCreator();
  });

  // close creator on overlay click / Esc
  const creatorOverlay = document.getElementById('qrCreateModal');
  if (creatorOverlay) creatorOverlay.addEventListener('click', function(e){ if (e.target === creatorOverlay) closeQrCreator(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeQrCreator(); });
})();

// Search functionality
function focusSearch() {
  const searchInput = document.getElementById('productSearch');
  if (searchInput) {
    searchInput.focus();
    searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

function searchProducts() {
  // Hàm này không còn dùng nữa, để tránh lỗi nếu có code gọi
  console.log('Search is now handled by real-time input event');
}

// Tìm kiếm real-time khi gõ (debounce 500ms)
let searchTimeout;
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('productSearch');
  const productsContainer = document.getElementById('productsContainer');
  const noResults = document.getElementById('noResults');
  const searchResultCount = document.getElementById('searchResultCount');
  
  if (searchInput && productsContainer) {
    // Lưu tất cả sản phẩm
    const allProducts = Array.from(productsContainer.children);
    
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase().trim();
      
      if (searchTerm === '') {
        // Hiện tất cả sản phẩm
        allProducts.forEach(product => {
          product.style.display = '';
        });
        noResults.style.display = 'none';
        searchResultCount.style.display = 'none';
        return;
      }
      
      // Tìm kiếm
      let visibleCount = 0;
      allProducts.forEach(product => {
        const productName = product.querySelector('.product-name')?.textContent.toLowerCase() || '';
        const productCategory = product.querySelector('.product-category')?.textContent.toLowerCase() || '';
        const productDesc = product.querySelector('.product-desc')?.textContent.toLowerCase() || '';
        
        // Tìm trong tên, danh mục, hoặc mô tả
        if (productName.includes(searchTerm) || 
            productCategory.includes(searchTerm) || 
            productDesc.includes(searchTerm)) {
          product.style.display = '';
          visibleCount++;
        } else {
          product.style.display = 'none';
        }
      });
      
      // Hiện thông báo nếu không tìm thấy
      if (visibleCount === 0) {
        noResults.style.display = 'block';
        searchResultCount.style.display = 'none';
      } else {
        noResults.style.display = 'none';
        searchResultCount.textContent = `${visibleCount} kết quả`;
        searchResultCount.style.display = 'block';
      }
    });
    
    // Xóa khi focus
    searchInput.addEventListener('focus', function() {
      this.select();
    });
  }
});

// Voucher functions for san-pham.php
let originalTotalSP = 0;
let currentDiscountSP = 0;

function applyVoucherSP() {
    const code = document.getElementById('voucherCodeSP').value.trim().toUpperCase();
    
    if (!code) {
        showVoucherMessageSP('Vui lòng nhập mã voucher', 'error');
        return;
    }
    
    // Get current subtotal
    const subtotalText = document.getElementById('summarySubtotal').textContent;
    originalTotalSP = parseInt(subtotalText.replace(/[^\d]/g, ''));
    
    // Use fetch instead of jQuery
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
                discount = Math.floor(originalTotalSP * voucher.gia_tri_giam / 100);
            } else {
                discount = voucher.gia_tri_giam;
            }
            
            if (discount > originalTotalSP) {
                discount = originalTotalSP;
            }
            
            currentDiscountSP = discount;
            
            document.getElementById('ma_voucherSP').value = code;
            document.getElementById('giam_giaSP').value = discount;
            
            document.getElementById('summaryDiscountSP').textContent = '-' + formatPrice(discount);
            document.getElementById('discountRowSP').style.display = 'flex';
            
            updateTotalSP();
            
            showVoucherMessageSP('✓ ' + res.message, 'success');
        } else {
            showVoucherMessageSP('✗ ' + res.message, 'error');
            resetVoucherSP();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showVoucherMessageSP('✗ Có lỗi xảy ra', 'error');
    });
}

function resetVoucherSP() {
    currentDiscountSP = 0;
    document.getElementById('ma_voucherSP').value = '';
    document.getElementById('giam_giaSP').value = 0;
    document.getElementById('discountRowSP').style.display = 'none';
    updateTotalSP();
}

function updateTotalSP() {
    updateCheckoutSummary();
}

function showVoucherMessageSP(message, type) {
    const msgEl = document.getElementById('voucherMessageSP');
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

function selectVoucherSP(code, type, value, minOrder) {
    // Get current subtotal to check minimum order
    const subtotalText = document.getElementById('summarySubtotal').textContent;
    const subtotal = parseInt(subtotalText.replace(/[^\d]/g, ''));
    
    if (minOrder > 0 && subtotal < minOrder) {
        showVoucherMessageSP(`Đơn hàng tối thiểu ${formatPrice(minOrder)} để sử dụng voucher này`, 'error');
        return;
    }
    
    // Fill the input and apply
    document.getElementById('voucherCodeSP').value = code;
    applyVoucherSP();
    
    // Hide voucher list if shown
    const voucherList = document.getElementById('voucherListSP');
    if (voucherList) voucherList.style.display = 'none';
}

function formatMoneySP(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

// Enter key to apply voucher
document.addEventListener('DOMContentLoaded', function() {
    const voucherInput = document.getElementById('voucherCodeSP');
    if (voucherInput) {
        voucherInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyVoucherSP();
            }
        });
    }
});
</script>

<!-- Voucher List Modal -->
<div class="modal-overlay" id="voucherListModal" style="display: none;">
  <div class="checkout-modal" style="max-width: 700px;">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fas fa-tags"></i> Mã giảm giá khả dụng</h3>
      <button class="modal-close" onclick="closeVoucherList()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <div id="voucherListContent" style="display: flex; flex-direction: column; gap: 12px;">
        <div style="text-align: center; padding: 30px; color: #999;">
          <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
          <p style="margin-top: 10px;">Đang tải...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Voucher list functions (shared across pages)
window.showAvailableVouchers = function() {
    const modal = document.getElementById('voucherListModal');
    const content = document.getElementById('voucherListContent');
    
    if (!modal || !content) return;
    
    modal.classList.add('active');
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
                                    <button onclick="copyVoucherCode('${voucher.code}')" style="background: rgba(255,255,255,0.3); color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-size: 0.875rem; font-weight: 600; backdrop-filter: blur(10px);">
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
    if (modal) modal.classList.remove('active');
}

window.copyVoucherCode = function(code) {
    navigator.clipboard.writeText(code).then(() => {
        const voucherInput = document.getElementById('voucherCodeTC') || 
                            document.getElementById('voucherCodeSP') || 
                            document.getElementById('voucherCodeSale');
        if (voucherInput) {
            voucherInput.value = code;
        }
        closeVoucherList();
        alert('✓ Đã sao chép mã: ' + code);
    }).catch(err => {
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

// ===== YÊU THÍCH SẢN PHẨM =====
window.toggleLike = function(productId) {
    fetch('xu_ly_yeu_thich.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'toggle',
            san_pham_id: productId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const btn = document.querySelector(`.like-btn[data-id="${productId}"]`);
            const icon = btn.querySelector('i');
            const count = btn.querySelector('.like-count');
            
            if (data.liked) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                btn.classList.add('liked');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                btn.classList.remove('liked');
            }
            
            count.textContent = data.count;
            if (data.count > 0) {
                count.classList.add('show');
            } else {
                count.classList.remove('show');
            }
        }
    })
    .catch(err => console.error('Error:', err));
};

// Load like counts on page load
document.addEventListener('DOMContentLoaded', function() {
    const likeButtons = document.querySelectorAll('.like-btn');
    likeButtons.forEach(btn => {
        const productId = btn.dataset.id;
        
        // Get count
        fetch('xu_ly_yeu_thich.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'get_count',
                san_pham_id: productId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.count > 0) {
                const count = btn.querySelector('.like-count');
                count.textContent = data.count;
                count.classList.add('show');
            }
        });
        
        // Check if user liked
        fetch('xu_ly_yeu_thich.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'check_liked',
                san_pham_id: productId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.liked) {
                const icon = btn.querySelector('i');
                icon.classList.remove('far');
                icon.classList.add('fas');
                btn.classList.add('liked');
            }
        });
    });
});
</script>

<link rel="stylesheet" href="assets/chatbot.css">
<link rel="stylesheet" href="assets/chatbot_auto.css">
<link rel="stylesheet" href="assets/notifications.css">
<?php include 'assets/chatbot_session.php'; ?>
<script src="assets/notification_bell.js" defer></script>
<script src="assets/chatbot.js" defer></script>
<script src="assets/chatbot_auto.js" defer></script>
</body>
</html>