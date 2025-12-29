<link rel="stylesheet" href="assets/notification_bell.css">
<?php
/**
 * Shopping Cart Page
 * Trang giỏ hàng
 */

// Load database connection
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/auth_gate.php';

// Xử lý thêm sản phẩm vào giỏ từ chitiet_san_pham.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $san_pham_id = (int)($_POST['san_pham_id'] ?? 0);
    $so_luong = (int)($_POST['so_luong'] ?? 1);
    $redirect_to_checkout = isset($_POST['redirect_to_checkout']) && $_POST['redirect_to_checkout'] === '1';
    
    if ($san_pham_id > 0 && $so_luong > 0) {
        // Lấy thông tin sản phẩm
        $stmt = $conn->prepare("SELECT id, ten_san_pham, gia, hinh_anh FROM san_pham WHERE id = ?");
        $stmt->execute([$san_pham_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $_SESSION['cart_message'] = "Đã thêm {$so_luong} sản phẩm vào giỏ hàng!";
            
            // Nếu có flag redirect_to_checkout, chuyển đến trang thanh toán
            if ($redirect_to_checkout) {
                header('Location: thanhtoan.php');
                exit;
            }
        }
    }
    
    // Nếu không redirect, quay lại giỏ hàng
    header('Location: giohang.php');
    exit;
}

// Xử lý đặt hàng từ giỏ hàng
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['checkout_cart'])) {
    header('Content-Type: application/json');
    
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $items = $data['items'] ?? [];
        $customer = $data['customer'] ?? [];
        $totals = $data['totals'] ?? [];
        
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
            exit;
        }
        
        // Bắt đầu transaction
        $conn->beginTransaction();
        
        // Tạo mã đơn hàng
        $ma_don_hang = 'DH' . date('YmdHis') . rand(100, 999);
        
        // Thêm đơn hàng vào database
        $stmt = $conn->prepare("
            INSERT INTO don_hang (
                ma_don_hang, nguoi_dung_id, ten_khach_hang, so_dien_thoai, 
                email, dia_chi, phuong_thuc_thanh_toan, ghi_chu,
                tong_tien, phi_van_chuyen, tong_thanh_toan, trang_thai, ngay_dat
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Chờ xác nhận', NOW())
        ");
        
        $address = ($customer['address'] ?? '') . ', ' . ($customer['city'] ?? '');
        
        $stmt->execute([
            $ma_don_hang,
            $user_id,
            $customer['fullname'] ?? '',
            $customer['phone'] ?? '',
            $customer['email'] ?? '',
            $address,
            $customer['payment'] ?? 'cod',
            $customer['note'] ?? '',
            $totals['subtotal'] ?? 0,
            $totals['shipping'] ?? 0,
            $totals['total'] ?? 0
        ]);
        
        $order_id = $conn->lastInsertId();
        
        // Thêm chi tiết đơn hàng
        $stmt = $conn->prepare("
            INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, gia, size, thanh_tien)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            // Ghi log trước khi xử lý
            file_put_contents(__DIR__ . '/log_giohang.txt', date('Y-m-d H:i:s') . " - Đặt hàng: id=" . $item['id'] . ", qty=" . $item['quantity'] . "\n", FILE_APPEND);
            // Kiểm tra tồn kho trước khi trừ
            $check = $conn->prepare("SELECT so_luong FROM san_pham WHERE id = ? FOR UPDATE");
            $check->execute([$item['id']]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            $so_luong_con = (int)($row['so_luong'] ?? 0);
            if ($item['quantity'] > $so_luong_con) {
              throw new Exception('Sản phẩm ID ' . $item['id'] . ' chỉ còn ' . $so_luong_con . ' sản phẩm trong kho.');
            }
            // Thêm chi tiết đơn hàng
            $stmt->execute([
              $order_id,
              $item['id'],
              $item['quantity'],
              $item['price'],
              $item['size'] ?? 'M',
              $item['price'] * $item['quantity']
            ]);
            // Trừ số lượng sản phẩm trong kho
            $update = $conn->prepare("UPDATE san_pham SET so_luong = so_luong - :qty WHERE id = :id");
            $update->bindValue(':qty', $item['quantity'], PDO::PARAM_INT);
            $update->bindValue(':id', $item['id'], PDO::PARAM_INT);
            $update->execute();
            file_put_contents(__DIR__ . '/log_giohang.txt', date('Y-m-d H:i:s') . " - UPDATE: id=" . $item['id'] . ", trừ=" . $item['quantity'] . ", affected=" . $update->rowCount() . "\n", FILE_APPEND);
            // Đảm bảo không bị âm
            $fix = $conn->prepare("UPDATE san_pham SET so_luong = 0 WHERE so_luong < 0 AND id = ?");
            $fix->execute([$item['id']]);
            file_put_contents(__DIR__ . '/log_giohang.txt', date('Y-m-d H:i:s') . " - FIX âm: id=" . $item['id'] . ", affected=" . $fix->rowCount() . "\n", FILE_APPEND);
        }
        
        // Commit transaction nếu mọi thứ thành công
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Đặt hàng thành công',
            'order_code' => $ma_don_hang
        ]);
        exit;
        
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Lấy thông tin user nếu đã đăng nhập
$user_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT ten_dang_nhap, ho_ten, email FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Lấy danh sách voucher công khai
$vouchers = [];
try {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT ma_voucher, ten_voucher, mo_ta, loai_giam, gia_tri_giam, 
               gia_tri_don_hang_toi_thieu, ngay_ket_thuc, so_luong_con_lai
        FROM voucher 
        WHERE trang_thai = 'hoat_dong' 
        AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= ?)
        AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= ?)
        AND (so_luong_con_lai IS NULL OR so_luong_con_lai > 0)
        ORDER BY gia_tri_giam DESC
    ");
    $stmt->execute([$today, $today]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading vouchers: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Giỏ Hàng — My Shop</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>

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
  min-height: 100vh;
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
   HEADER
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
  display: none;
  align-items: center;
  gap: var(--space-2xl);
}

@media (min-width: 1024px) {
  .nav { display: flex; }
}

.nav a {
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--taupe-500);
  position: relative;
}

.nav a::after {
  content: '';
  position: absolute;
  bottom: -0.5rem;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--accent-gold);
  transition: width 0.3s ease;
}

.nav a:hover,
.nav a.active {
  color: var(--black);
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
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ==========================================
   PAGE HERO
   ========================================== */

.page-hero {
  background: linear-gradient(135deg, var(--cream-100) 0%, var(--beige-100) 100%);
  padding: var(--space-2xl) 0;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.page-hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at 30% 50%, rgba(201, 169, 97, 0.08) 0%, transparent 50%);
  pointer-events: none;
}

.page-hero .container {
  position: relative;
  z-index: 1;
}

.hero-icon {
  width: 5rem;
  height: 5rem;
  margin: 0 auto var(--space-lg);
  background: var(--black);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--cream-50);
}

.hero-icon i {
  font-size: 2rem;
}

.page-title {
  font-family: var(--font-serif);
  font-size: 3rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-md);
  line-height: 1.1;
}

.page-subtitle {
  font-size: 1.125rem;
  color: var(--taupe-500);
}

/* ==========================================
   CART SECTION
   ========================================== */

.cart-section {
  padding: var(--space-2xl) 0;
}

.cart-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-xl);
}

@media (min-width: 1024px) {
  .cart-layout {
    grid-template-columns: 1fr 400px;
  }
}

/* ==========================================
   CART ITEMS
   ========================================== */

.cart-items {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-xl);
}

.cart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-xl);
  padding-bottom: var(--space-lg);
  border-bottom: 2px solid var(--beige-200);
}

.cart-title {
  font-family: var(--font-serif);
  font-size: 1.75rem;
  color: var(--black);
}

.cart-count {
  font-size: 0.875rem;
  color: var(--taupe-500);
  font-weight: 500;
}

.cart-item {
  display: grid;
  grid-template-columns: 40px 120px 1fr auto;
  gap: var(--space-lg);
  padding: var(--space-lg);
  background: var(--cream-100);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-lg);
  margin-bottom: var(--space-md);
  transition: all 0.3s ease;
}

.cart-item.selected {
  background: #f0f9ff;
  border-color: #57534e;
}

.cart-item-checkbox {
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-item-checkbox input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
  accent-color: #57534e;
}

.cart-item:hover {
  transform: translateX(4px);
  box-shadow: var(--shadow-md);
}

.cart-item-image {
  width: 120px;
  height: 150px;
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--beige-100);
}

.cart-item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cart-item-info {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.cart-item-name {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--black);
  margin-bottom: var(--space-xs);
}

.cart-item-details {
  display: flex;
  gap: var(--space-md);
  font-size: 0.875rem;
  color: var(--taupe-500);
  margin-bottom: var(--space-sm);
}

.cart-item-detail {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
}

.cart-item-price {
  font-family: var(--font-serif);
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--black);
}

.cart-item-actions {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-end;
}

.remove-btn {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--taupe-400);
  border-radius: var(--radius-md);
  transition: all 0.2s ease;
}

.remove-btn:hover {
  background: var(--accent-rose);
  color: var(--cream-50);
  transform: scale(1.1);
}

.qty-control {
  display: flex;
  align-items: center;
  border: 2px solid var(--beige-200);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: var(--cream-50);
}

.qty-btn {
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--taupe-500);
  background: transparent;
  transition: all 0.2s ease;
}

.qty-btn:hover {
  background: var(--beige-100);
  color: var(--black);
}

.qty-value {
  width: 3rem;
  text-align: center;
  font-weight: 600;
  color: var(--black);
  font-size: 1rem;
}

/* ==========================================
   EMPTY CART
   ========================================== */

.empty-cart {
  text-align: center;
  padding: var(--space-2xl) var(--space-xl);
}

.empty-icon {
  width: 8rem;
  height: 8rem;
  margin: 0 auto var(--space-xl);
  background: var(--beige-100);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--taupe-400);
}

.empty-icon i {
  font-size: 3.5rem;
}

.empty-title {
  font-family: var(--font-serif);
  font-size: 2rem;
  color: var(--black);
  margin-bottom: var(--space-md);
}

.empty-desc {
  font-size: 1.125rem;
  color: var(--taupe-500);
  margin-bottom: var(--space-xl);
}

/* ==========================================
   CART SUMMARY
   ========================================== */

.cart-summary {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-xl);
  position: sticky;
  top: 6rem;
}

.summary-title {
  font-family: var(--font-serif);
  font-size: 1.5rem;
  color: var(--black);
  margin-bottom: var(--space-xl);
  padding-bottom: var(--space-lg);
  border-bottom: 2px solid var(--beige-200);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-md) 0;
  font-size: 1rem;
  color: var(--charcoal);
}

.summary-row.subtotal {
  font-size: 1.125rem;
}

.summary-row.shipping {
  padding-bottom: var(--space-lg);
  border-bottom: 2px solid var(--beige-200);
}

.summary-row.total {
  padding-top: var(--space-lg);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--black);
}

.summary-row.total .label {
  font-family: var(--font-serif);
}

.shipping-free {
  color: var(--accent-sage);
  font-weight: 600;
}

.promo-section {
  margin: var(--space-xl) 0;
  padding: var(--space-lg) 0;
  border-top: 1px solid var(--beige-200);
  border-bottom: 1px solid var(--beige-200);
}

.promo-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--black);
  margin-bottom: var(--space-sm);
}

.promo-input-wrapper {
  display: flex;
  gap: var(--space-sm);
}

.promo-input {
  flex: 1;
  padding: var(--space-md);
  border: 2px solid var(--beige-200);
  border-radius: var(--radius-md);
  background: var(--cream-100);
  font-size: 0.875rem;
  transition: all 0.3s ease;
}

.promo-input:focus {
  outline: none;
  border-color: var(--accent-gold);
  background: var(--cream-50);
}

.promo-btn {
  padding: var(--space-md) var(--space-lg);
  background: var(--black);
  color: var(--cream-50);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  transition: all 0.3s ease;
}

.promo-btn:hover {
  background: var(--charcoal);
  transform: translateY(-2px);
}

.checkout-btn {
  width: 100%;
  padding: var(--space-lg);
  background: var(--black);
  color: var(--cream-50);
  border-radius: var(--radius-md);
  font-size: 1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  margin-bottom: var(--space-md);
  transition: all 0.3s ease;
}

.checkout-btn:hover {
  background: var(--charcoal);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.continue-shopping {
  width: 100%;
  padding: var(--space-md);
  border: 2px solid var(--beige-200);
  background: transparent;
  color: var(--charcoal);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  transition: all 0.3s ease;
}

.continue-shopping:hover {
  background: var(--cream-100);
  border-color: var(--beige-300);
}

/* ==========================================
   FEATURES
   ========================================== */

.features-section {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-2xl);
  margin-top: var(--space-2xl);
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
   BUTTONS
   ========================================== */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-md) var(--space-xl);
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

.modal-body {
  padding: var(--space-xl);
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

/* Voucher Cards in Modal */
.voucher-card-gh {
  background: white;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  padding: 10px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.voucher-card-gh:hover {
  border-color: #667eea;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
  transform: translateY(-1px);
}

.voucher-card-gh.selected {
  border-color: #28a745;
  background: linear-gradient(135deg, #f0f9f4, #e8f5e9);
  box-shadow: 0 3px 10px rgba(40, 167, 69, 0.2);
}

.voucher-card-gh.selected .voucher-check-gh {
  color: #28a745 !important;
}

.checkout-summary-modal {
  background: var(--cream-100);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
  margin-top: var(--space-lg);
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
   RESPONSIVE
   ========================================== */

@media (max-width: 768px) {
  .cart-item {
    grid-template-columns: 100px 1fr;
  }

  .cart-item-actions {
    grid-column: 1 / -1;
    flex-direction: row;
    justify-content: space-between;
    margin-top: var(--space-md);
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 1023px) {
  .hide-mobile { display: none !important; }
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
      
      <a href="san-pham.php">Sản Phẩm</a>
      <a href="don_hang_cua_toi.php">Theo Dõi Đơn Hàng</a>
      <a href="lienhe.php">Liên Hệ</a>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" title="Tìm kiếm">
        <i class="fas fa-search"></i>
      </button>
      <a href="dangnhap.php" class="icon-btn hide-mobile" title="Yêu thích">
        <i class="far fa-heart"></i>
      </a>
      <a href="giohang.php" class="icon-btn active" title="Giỏ hàng">
        <i class="fas fa-shopping-bag"></i>
        <span class="cart-badge" id="cartBadge">0</span>
      </a>
      <a href="dangnhap.php" class="icon-btn hide-mobile" title="Tài khoản">
        <i class="far fa-user"></i>
      </a>
    </div>
  </div>
</header>

<!-- Page Hero -->
<section class="page-hero">
  <div class="container">
    <div class="hero-icon">
      <i class="fas fa-shopping-bag"></i>
    </div>
    <h1 class="page-title">Giỏ Hàng</h1>
    <p class="page-subtitle">Kiểm tra và hoàn tất đơn hàng của bạn</p>
  </div>
</section>

<!-- Cart Section -->
<div class="container">
  <section class="cart-section">
    <div class="cart-layout">
      <!-- Cart Items -->
      <div class="cart-items">
        <div class="cart-header">
          <h2 class="cart-title">Sản phẩm</h2>
          <div style="display: flex; align-items: center; gap: 1rem;">
            <!-- Search in cart -->
            <form id="cartSearchForm" onsubmit="event.preventDefault(); applyCartSearch();" style="display:flex;align-items:center;gap:0.5rem;">
              <input type="search" id="cartSearchInput" name="search_cart" placeholder="Tìm trong giỏ hàng..." style="padding:6px 8px;border:1px solid var(--beige-200);border-radius:6px;"> 
              <button type="button" onclick="applyCartSearch()" title="Tìm" style="background:transparent;border:none;cursor:pointer;color:var(--taupe-500)"><i class="fas fa-search"></i></button>
            </form>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: var(--taupe-500);">
              <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer; accent-color: #57534e;">
              <span>Chọn tất cả</span>
            </label>
            <span class="cart-count"><span id="itemCount">0</span> sản phẩm | <span id="selectedCount">0</span> đã chọn</span>
          </div>
        </div>

        <div id="cartItemsContainer">
          <!-- Cart items will be dynamically inserted here -->
        </div>

        <!-- Empty Cart Message -->
        <div id="emptyCart" class="empty-cart" style="display: none;">
          <div class="empty-icon">
            <i class="fas fa-shopping-bag"></i>
          </div>
          <h3 class="empty-title">Giỏ hàng trống</h3>
          <p class="empty-desc">Hãy thêm sản phẩm yêu thích vào giỏ hàng để tiếp tục mua sắm</p>
          <a href="san-pham.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i>
            Tiếp tục mua sắm
          </a>
        </div>
      </div>

      <!-- Cart Summary -->
      <div class="cart-summary">
        <h3 class="summary-title">Tổng đơn hàng</h3>

        <div class="summary-row subtotal">
          <span class="label">Tạm tính:</span>
          <span class="value" id="subtotalAmount">0₫</span>
        </div>

        <div class="summary-row shipping">
          <span class="label">Phí vận chuyển:</span>
          <span class="value" id="shippingAmount">30.000₫</span>
        </div>

        <div class="summary-row total">
          <span class="label">Tổng cộng:</span>
          <span class="value" id="totalAmount">0₫</span>
        </div>


        <!-- Checkout Button -->
        <button class="checkout-btn" id="checkoutBtn" onclick="openCheckout()">
          <i class="fas fa-lock"></i>
          Tiến hành thanh toán
        </button>

        <a href="san-pham.php" class="continue-shopping">
          <i class="fas fa-arrow-left"></i>
          Tiếp tục mua sắm
        </a>
      </div>
    </div>

    <!-- Features -->
    <div class="features-section">
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
    </div>
  </section>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <p class="footer-text">© <?php echo date('Y'); ?> My Phuong Shop — Thiết kế bởi đam mê</p>
  </div>
</footer>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkoutModal">
  <div class="checkout-modal">
    <div class="modal-header">
      <h3 class="modal-title">Thông tin giao hàng</h3>
      <button class="modal-close" onclick="closeCheckout()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <!-- Checkout Form -->
      <form class="checkout-form" id="checkoutForm" onsubmit="return handleCheckout(event)">
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
              <option value="Vĩnh Long">Vĩnh Long</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Phương thức thanh toán</label>
            <select class="form-select" name="payment" id="cartPaymentSelect">
              <option value="cod">Thanh toán khi nhận hàng</option>
              <option value="bank">Chuyển khoản ngân hàng</option>
              <option value="qr">Quét mã QR</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Ghi chú</label>
          <textarea class="form-textarea" name="note" placeholder="Ghi chú đơn hàng (tùy chọn)"></textarea>
        </div>



        <!-- Bank Transfer Info Section (hidden by default) -->
        <div id="cartBankInfoSection" style="display: none; margin-top: 1.5rem;">
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
        <div id="cartQrCodeSection" style="display: none; margin-top: 1.5rem;">
          <div style="background: #f8fafc; border: 2px solid #3b82f6; border-radius: 12px; padding: 1.5rem; max-width: 420px; margin: 0 auto;">
            <h4 style="font-weight: 700; font-size: 1rem; margin-bottom: 1rem; color: #1e40af; text-align: center;">
              📱 Quét mã QR để thanh toán
            </h4>
            <div style="background: white; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
              <img id="cartQrCodeImage" src="" alt="QR Code Thanh Toán" style="max-width: 280px; width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 8px;">
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div class="checkout-summary-modal">
          <div class="summary-row">
            <span>Tạm tính</span>
            <span id="modalSubtotal">0₫</span>
          </div>

          <div class="summary-row">
            <span>Phí vận chuyển</span>
            <span id="modalShipping">30.000₫</span>
          </div>
          <div class="summary-row total">
            <span>Tổng cộng</span>
            <span id="modalTotal">0₫</span>
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

<script>
(function(){
  'use strict';
  
  // ===== CART MANAGEMENT =====
  const CART_KEY = 'myphuongshop_cart_items';
  const CART_COUNT_KEY = 'myphuongshop_cart_count';
  
  // Get cart items from localStorage
  function getCartItems() {
    try {
      return JSON.parse(localStorage.getItem(CART_KEY) || '[]');
    } catch(e) {
      return [];
    }
  }

  // Current search filter (client-side)
  let cartSearchQuery = '';

  // Apply search from input and re-render
  window.applyCartSearch = function() {
    const inp = document.getElementById('cartSearchInput');
    cartSearchQuery = (inp && inp.value) ? inp.value.trim().toLowerCase() : '';
    renderCart();
  };
  
  // Save cart items to localStorage
  function saveCartItems(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    updateCartCount();
    renderCart();
  }
  
  // Update cart count
  function updateCartCount() {
    const items = getCartItems();
    const count = items.reduce((sum, item) => sum + item.quantity, 0);
    localStorage.setItem(CART_COUNT_KEY, String(count));
    
    const badge = document.getElementById('cartBadge');
    const itemCount = document.getElementById('itemCount');
    
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
    if (itemCount) itemCount.textContent = items.length;
  }
  
  // Format price
  function formatPrice(price) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
  }
  
  // Remove item from cart
  window.removeFromCart = function(index) {
    const items = getCartItems();
    items.splice(index, 1);
    saveCartItems(items);
  };
  
  // Update quantity
  window.updateQuantity = function(index, delta) {
    const items = getCartItems();
    if (items[index]) {
      items[index].quantity += delta;
      if (items[index].quantity < 1) items[index].quantity = 1;
      if (items[index].quantity > 99) items[index].quantity = 99;
      saveCartItems(items);
    }
  };
  
  // Calculate totals
  function calculateTotals() {
    const items = getCartItems();
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const selectedIndexes = Array.from(checkboxes).map(cb => parseInt(cb.dataset.index));
    
    const subtotal = items.reduce((sum, item, index) => {
      if (selectedIndexes.includes(index)) {
        return sum + (item.price * item.quantity);
      }
      return sum;
    }, 0);
    
    // Đơn dưới 50.000đ phí 5.000đ, từ 50.000đ trở lên miễn phí
    const shipping = subtotal > 0 && subtotal < 50000 ? 5000 : (subtotal >= 50000 ? 0 : 0);
    const total = subtotal + shipping;
    
    return { subtotal, shipping, total, selectedCount: selectedIndexes.length };
  }
  
  // Render cart
  function renderCart() {
    let items = getCartItems();
    if (cartSearchQuery) {
      items = items.filter(i => (i.name || '').toString().toLowerCase().includes(cartSearchQuery));
    }
    const container = document.getElementById('cartItemsContainer');
    const emptyCart = document.getElementById('emptyCart');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (items.length === 0) {
      container.style.display = 'none';
      emptyCart.style.display = 'block';
      if (checkoutBtn) checkoutBtn.disabled = true;
    } else {
      container.style.display = 'block';
      emptyCart.style.display = 'none';
      if (checkoutBtn) checkoutBtn.disabled = false;
      
      container.innerHTML = items.map((item, index) => `
        <div class="cart-item" data-index="${index}" data-id="${item.id}">
          <div class="cart-item-checkbox">
            <input type="checkbox" class="item-checkbox" data-index="${index}" onchange="updateSelection()">
          </div>
          <div class="cart-item-image">
            <img src="${item.image || 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600'}" alt="${item.name}" onerror="this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600'">
          </div>
          
          <div class="cart-item-info">
            <h4 class="cart-item-name">${item.name}</h4>
            <div class="cart-item-details"></div>
            <div class="cart-item-price">${formatPrice(item.price * item.quantity)}</div>
          </div>
          
          <div class="cart-item-actions">
            <button class="remove-btn" onclick="removeFromCart(${index})" title="Xóa">
              <i class="fas fa-trash-alt"></i>
            </button>
            <div class="qty-control">
              <button class="qty-btn" onclick="updateQuantity(${index}, -1)"><i class="fas fa-minus"></i></button>
              <span class="qty-value">${item.quantity}</span>
              <button class="qty-btn" onclick="updateQuantity(${index}, 1)"><i class="fas fa-plus"></i></button>
            </div>
          </div>
        </div>
      `).join('');
      // Sau khi render, tự động tích sản phẩm vừa mua nếu có lastBuyProductId trong localStorage
      const lastBuyId = localStorage.getItem('lastBuyProductId');
      if (lastBuyId) {
        const items = getCartItems();
        document.querySelectorAll('.item-checkbox').forEach(cb => {
          if (cb.dataset.index && items[cb.dataset.index] && String(items[cb.dataset.index].id) === lastBuyId) {
            cb.checked = true;
            // Scroll tới sản phẩm vừa mua
            const cartItem = cb.closest('.cart-item');
            if (cartItem) {
              cartItem.classList.add('highlight-last-buy');
              cartItem.scrollIntoView({behavior:'smooth',block:'center'});
              setTimeout(()=>cartItem.classList.remove('highlight-last-buy'), 2000);
            }
          } else {
            cb.checked = false;
          }
        });
        localStorage.removeItem('lastBuyProductId');
        updateSelection();
      }
    }
    // CSS cho hiệu ứng highlight sản phẩm vừa mua
    const style = document.createElement('style');
    style.innerHTML = `.highlight-last-buy { box-shadow: 0 0 0 3px #ffb703, 0 2px 8px rgba(0,0,0,0.08); transition: box-shadow 0.3s; }`;
    document.head.appendChild(style);
    
    updateSummary();
    // Sau khi render, chỉ tích sản phẩm vừa mua (nếu có)
    const justBoughtId = sessionStorage.getItem('justBoughtId');
    if (justBoughtId) {
      const items = getCartItems();
      // Chỉ tích sản phẩm vừa mua, giữ nguyên trạng thái các sản phẩm khác
      document.querySelectorAll('.item-checkbox').forEach(cb => {
        if (cb.dataset.index && items[cb.dataset.index] && String(items[cb.dataset.index].id) === justBoughtId) {
          cb.checked = true;
        } else {
          cb.checked = false;
        }
      });
      // Xóa cờ sau khi dùng
      sessionStorage.removeItem('justBoughtId');
      updateSelection();
    }

    // Khi tăng/giảm số lượng, không tự động tích checkbox
    document.querySelectorAll('.qty-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        // Không thay đổi trạng thái checkbox khi tăng/giảm số lượng
        // Giữ nguyên trạng thái đã chọn trước đó
        // (Không làm gì ở đây)
      });
    });
  }
  
  // Toggle select all
  window.toggleSelectAll = function() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelection();
  };
  
  // Update selection state
  window.updateSelection = function() {
    const items = document.querySelectorAll('.cart-item');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const selectAll = document.getElementById('selectAllCheckbox');
    const selectedCountEl = document.getElementById('selectedCount');
    
    let selectedCount = 0;
    checkboxes.forEach((cb, index) => {
      if (cb.checked) {
        selectedCount++;
        items[index].classList.add('selected');
      } else {
        items[index].classList.remove('selected');
      }
    });
    
    // Update select all checkbox
    selectAll.checked = selectedCount === checkboxes.length && selectedCount > 0;
    selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    
    // Update selected count
    if (selectedCountEl) {
      selectedCountEl.textContent = selectedCount;
    }
    
    // Update totals
    updateSummary();
  };
  
  // Update summary
  function updateSummary() {
    const { subtotal, shipping, total, selectedCount } = calculateTotals();
    
    document.getElementById('subtotalAmount').textContent = formatPrice(subtotal);
    document.getElementById('shippingAmount').innerHTML = shipping === 0 
      ? '<span class="shipping-free">Miễn phí</span>' 
      : formatPrice(shipping);
    document.getElementById('totalAmount').textContent = formatPrice(total);
    
    // Update modal summary
    document.getElementById('modalSubtotal').textContent = formatPrice(subtotal);
    document.getElementById('modalShipping').innerHTML = shipping === 0 
      ? '<span class="shipping-free">Miễn phí</span>' 
      : formatPrice(shipping);
    document.getElementById('modalTotal').textContent = formatPrice(total);
  }
  
  // Apply promo code
  window.applyPromo = function() {
    const input = document.getElementById('promoInput');
    const code = input.value.trim().toUpperCase();
    
    if (!code) {
      alert('Vui lòng nhập mã giảm giá!');
      return;
    }
    
    // Call API to check voucher
    fetch('check_voucher.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'ma_voucher=' + encodeURIComponent(code)
    })
    .then(response => response.json())
    .then(res => {
      if (res.success) {
        const voucher = res.voucher;
        let discountText = '';
        
        if (voucher.loai_giam === 'phan_tram') {
          discountText = `Giảm ${voucher.gia_tri_giam}%`;
        } else {
          discountText = `Giảm ${new Intl.NumberFormat('vi-VN').format(voucher.gia_tri_giam)}₫`;
        }
        
        alert(`✅ Áp dụng mã "${code}" thành công!\n${discountText} cho đơn hàng`);
        input.value = code;
        // Voucher saved in session by API
      } else {
        alert('❌ ' + res.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('❌ Có lỗi xảy ra khi kiểm tra mã giảm giá');
    });
  };
  
  // Update QR payment info
  function updateQRInfo(amount, content) {
    const amountNum = Math.round(amount);
    const orderCode = content || 'DH' + Date.now();
    
    // Generate VietQR URL
    const accountNo = '0325048679';
    const accountName = 'TRUONG%20THI%20MY%20PHUONG';
    const bankCode = 'MB';
    const description = encodeURIComponent(orderCode);
    const qrUrl = `https://img.vietqr.io/image/${bankCode}-${accountNo}-compact2.png?amount=${amountNum}&addInfo=${description}&accountName=${accountName}`;
    
    // Update QR image
    const qrImage = document.getElementById('cartQrCodeImage');
    if (qrImage) qrImage.src = qrUrl;
  }
  
  // Open checkout modal
  window.openCheckout = function() {
    const items = getCartItems();
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (items.length === 0) {
      alert('Giỏ hàng trống! Vui lòng thêm sản phẩm.');
      return;
    }
    
    if (checkboxes.length === 0) {
      alert('Vui lòng chọn ít nhất 1 sản phẩm để thanh toán!');
      return;
    }
    
    // Tự động điền thông tin khách hàng đã lưu
    loadCustomerInfo();
    
    // Show modal
    document.getElementById('checkoutModal').classList.add('active');
  };
  
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
  
  // Add payment method display handler for cart checkout modal
  (function() {
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        const modal = document.getElementById('checkoutModal');
        if (modal && modal.classList.contains('active')) {
          // Hide both sections initially
          const qrSection = document.getElementById('cartQrCodeSection');
          const bankSection = document.getElementById('cartBankInfoSection');
          if (qrSection) qrSection.style.display = 'none';
          if (bankSection) bankSection.style.display = 'none';
          
          const paymentSelect = document.getElementById('cartPaymentSelect');
          if (paymentSelect && !paymentSelect.dataset.listenerAdded) {
            paymentSelect.dataset.listenerAdded = 'true';
            paymentSelect.addEventListener('change', function() {
              const qrSection = document.getElementById('cartQrCodeSection');
              const bankSection = document.getElementById('cartBankInfoSection');
              
              // Hide both first
              if (qrSection) qrSection.style.display = 'none';
              if (bankSection) bankSection.style.display = 'none';
              
              // Show appropriate section
                if (this.value === 'qr' && qrSection) {
                  // Update QR payment info với tổng tiền đã trừ giảm giá
                  const subtotalText = document.getElementById('modalSubtotal')?.textContent || '0';
                  const shippingText = document.getElementById('modalShipping')?.textContent || '0';
                  const subtotal = parseInt(subtotalText.replace(/[^\d]/g, '')) || 0;
                  const shipping = parseInt(shippingText.replace(/[^\d]/g, '')) || 0;
                  const discountInput = document.getElementById('giam_giaGH');
                  const discount = discountInput ? parseInt(discountInput.value) || 0 : 0;
                  const totalAfterDiscount = subtotal + shipping - discount;
                  const orderCode = 'DH' + Date.now().toString().slice(-8);
                  updateQRInfo(totalAfterDiscount, orderCode);
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
    
    const modalElement = document.getElementById('checkoutModal');
    if (modalElement) {
      observer.observe(modalElement, { attributes: true, attributeFilter: ['class'] });
    }
  })();
  
  // Close checkout modal
  window.closeCheckout = function() {
    const modal = document.getElementById('checkoutModal');
    modal.classList.remove('active');
    
    // Hide QR section when closing
    const qrSection = document.getElementById('cartQrCodeSection');
    if (qrSection) qrSection.style.display = 'none';
  };
  
  // Handle checkout form submit
  window.handleCheckout = function(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Get only selected items
    const allItems = getCartItems();
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const selectedIndexes = Array.from(checkboxes).map(cb => parseInt(cb.dataset.index));
    const cartItems = allItems.filter((item, index) => selectedIndexes.includes(index));
    
    if (cartItems.length === 0) {
      alert('Vui lòng chọn ít nhất 1 sản phẩm để thanh toán!');
      return;
    }
    
    const { subtotal, shipping } = calculateTotals();
    
    // Get voucher discount
    const voucherDiscount = parseInt(document.getElementById('giam_giaGH')?.value || 0);
    const voucherCode = document.getElementById('ma_voucherGH')?.value || '';
    const total = subtotal + shipping - voucherDiscount;
    
    const orderData = {
      items: cartItems,
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
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(orderData)
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Lưu thông tin khách hàng để dùng cho lần sau
        saveCustomerInfo(orderData.customer);
        alert(`✅ Đặt hàng thành công!\n\nMã đơn hàng: ${data.orderCode}\nTổng tiền: ${formatPrice(total)}\n\nCảm ơn bạn đã mua hàng tại MyPhuong Shop!\nBạn có thể xem đơn hàng tại trang "Đơn hàng của tôi".`);
        
        // Remove only selected items from cart
        const remainingItems = allItems.filter((item, index) => !selectedIndexes.includes(index));
        localStorage.setItem(CART_KEY, JSON.stringify(remainingItems));
        updateCartCount();
        closeCheckout();
        event.target.reset();
        renderCart();
        
        // Chuyển đến trang đơn hàng của tôi
        setTimeout(() => {
          window.location.href = 'don_hang_cua_toi.php';
        }, 1500);
      } else {
        alert('❌ Lỗi: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('❌ Có lỗi xảy ra khi đặt hàng');
    });
    
    return false;
  };
  
  // Initialize
  renderCart();
  updateCartCount();
  // If checkout requested via query param, open checkout modal
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.get('checkout') === '1') setTimeout(openCheckout, 200);
  } catch (e) { console.error('checkout param parse error', e); }
  
  // Close modal on outside click
  document.getElementById('checkoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeCheckout();
    }
  });
  
})();


</script>

<link rel="stylesheet" href="assets/chatbot.css">
<link rel="stylesheet" href="assets/notifications.css">
<?php include 'assets/chatbot_session.php'; ?>
<script src="assets/notification_bell.js" defer></script>
<script src="assets/chatbot.js" defer></script>
</body>
</html>
