<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Liên hệ — My Shop</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400…00;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<style>
/* ==========================================
   PREMIUM MINIMALIST DESIGN SYSTEM
   ========================================== */

:root {
  /* Warm Neutral Palette */
  --cream-50: #fefdfb;
  --cream-100: #faf8f5;
  --cream-200: #f5f2ed;
  --beige-100: #ede8e0;
  --beige-200: #e3ddd1;
  --beige-300: #d4cbc0;
  --taupe-400: #a89f94;
  --taupe-500: #8a8179;
  --charcoal: #2d2a26;
  --black: #1a1816;
  
  /* Accent Colors */
  --accent-gold: #c9a961;
  --accent-rose: #d4a5a5;
  --accent-sage: #9ca89a;
  --accent-terracotta: #c17767;
  
  /* Typography */
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-serif: 'Playfair Display', Georgia, serif;
  
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
  max-width: 600px;
  margin: 0 auto;
}

/* ==========================================
   CONTACT SECTION
   ========================================== */

.contact-section {
  padding: var(--space-2xl) 0;
}

.contact-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-2xl);
  margin-bottom: var(--space-2xl);
}

@media (min-width: 1024px) {
  .contact-grid {
    grid-template-columns: 1fr 1fr;
  }
}

/* ==========================================
   INFO CARD
   ========================================== */

.info-card {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-2xl);
  box-shadow: var(--shadow-md);
}

.info-card h2 {
  font-family: var(--font-serif);
  font-size: 2rem;
  color: var(--black);
  margin-bottom: var(--space-xl);
  padding-bottom: var(--space-lg);
  border-bottom: 2px solid var(--beige-200);
}

.info-item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-lg);
  padding: var(--space-lg);
  margin-bottom: var(--space-md);
  background: var(--cream-100);
  border-radius: var(--radius-lg);
  border-left: 4px solid var(--accent-gold);
  transition: all 0.3s ease;
}

.info-item:hover {
  transform: translateX(8px);
  box-shadow: var(--shadow-md);
}

.info-icon {
  width: 3rem;
  height: 3rem;
  background: var(--black);
  color: var(--cream-50);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-icon i {
  font-size: 1.25rem;
}

.info-content h3 {
  font-size: 1rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-xs);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-content p {
  font-size: 1.125rem;
  color: var(--charcoal);
  margin: 0;
}

.info-content a {
  color: var(--accent-gold);
  font-weight: 600;
  transition: all 0.2s ease;
}

.info-content a:hover {
  color: var(--black);
  text-decoration: underline;
}

/* ==========================================
   IMAGE CARD
   ========================================== */

.image-card {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  overflow: hidden;
  box-shadow: var(--shadow-md);
  position: relative;
}

.image-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.3) 100%);
  z-index: 1;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.image-card:hover::before {
  opacity: 1;
}

.image-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}

.image-card:hover img {
  transform: scale(1.05);
}

.image-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: var(--space-xl);
background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);
  color: white;
  z-index: 2;
  transform: translateY(100%);
  transition: transform 0.3s ease;
}

.image-card:hover .image-overlay {
  transform: translateY(0);
}

.image-overlay h3 {
  font-family: var(--font-serif);
  font-size: 1.5rem;
  margin-bottom: var(--space-sm);
}

.image-overlay p {
  font-size: 0.875rem;
  opacity: 0.9;
}

/* ==========================================
   DESCRIPTION SECTION
   ========================================== */

.description-section {
  background: var(--cream-50);
  border: 1px solid var(--beige-200);
  border-radius: var(--radius-xl);
  padding: var(--space-2xl);
  margin-bottom: var(--space-2xl);
  border-left: 6px solid var(--accent-gold);
}

.description-section h3 {
  font-family: var(--font-serif);
  font-size: 1.75rem;
  color: var(--black);
  margin-bottom: var(--space-lg);
}

.description-section p {
  font-size: 1.125rem;
  color: var(--charcoal);
  margin-bottom: var(--space-md);
  line-height: 1.8;
}

.description-section .highlight {
  background: var(--beige-100);
  padding: var(--space-lg);
  border-radius: var(--radius-lg);
  margin: var(--space-lg) 0;
}

.description-section .highlight strong {
  color: var(--black);
  font-size: 1.125rem;
  display: block;
  margin-bottom: var(--space-md);
}

.description-section ul {
  list-style: none;
  padding: 0;
  margin: var(--space-md) 0;
}

.description-section ul li {
  padding: var(--space-md);
  margin-bottom: var(--space-sm);
  background: var(--cream-100);
  border-radius: var(--radius-md);
  border-left: 3px solid var(--accent-sage);
  position: relative;
  padding-left: 3rem;
  transition: all 0.2s ease;
}

.description-section ul li:hover {
  transform: translateX(8px);
  background: var(--cream-50);
  box-shadow: var(--shadow-sm);
}

.description-section ul li::before {
  content: '✓';
  position: absolute;
  left: var(--space-md);
  color: var(--accent-sage);
  font-weight: 700;
  font-size: 1.25rem;
}

/* ==========================================
   FEATURES GRID
   ========================================== */

.features-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-lg);
  margin-bottom: var(--space-2xl);
}

@media (min-width: 768px) {
  .features-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.feature-card {
  background: var(--cream-50);
  border: 2px solid var(--accent-gold);
  border-radius: 2rem;
  box-shadow: 0 2px 16px 0 rgba(201,169,97,0.08);
  padding: var(--space-xl);
  text-align: center;
  transition: all 0.3s ease;
  min-height: 320px;
}

.feature-card:hover {
  transform: translateY(-8px);
  box-shadow: var(--shadow-xl);
  border-color: var(--accent-gold);
}

.feature-icon {
  width: 4rem;
  height: 4rem;
  margin: 0 auto var(--space-lg);
  background: linear-gradient(135deg, var(--black), var(--charcoal));
  border-radius: 50%;
  display: flex;
  align-items: center;
justify-content: center;
  color: white;
}

.feature-icon i {
  font-size: 1.75rem;
}

.feature-card h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-sm);
}

.feature-card p {
  font-size: 0.9375rem;
  color: var(--taupe-500);
  line-height: 1.6;
}

/* ==========================================
   STATS SECTION
   ========================================== */

.stats-section {
  background: linear-gradient(135deg, var(--black) 0%, var(--charcoal) 100%);
  color: white;
  padding: var(--space-2xl);
  border-radius: var(--radius-xl);
  margin-bottom: var(--space-2xl);
}

.stats-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-xl);
}

@media (min-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-item {
  text-align: center;
  padding: var(--space-lg);
  border-radius: var(--radius-lg);
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: all 0.3s ease;
}

.stat-item:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: translateY(-4px);
}

.stat-value {
  font-family: var(--font-serif);
  font-size: 3rem;
  font-weight: 700;
  margin-bottom: var(--space-xs);
  background: linear-gradient(135deg, #fff, var(--accent-gold));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-label {
  font-size: 0.875rem;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

/* ==========================================
   CTA SECTION
   ========================================== */

.cta-section {
  background: #fff;
  color: var(--black);
  padding: var(--space-2xl);
  border-radius: 2rem;
  border: 2px solid var(--accent-gold);
  box-shadow: 0 2px 16px 0 rgba(201,169,97,0.08);
  text-align: center;
  margin-bottom: var(--space-2xl);
  min-height: 320px;
}

.cta-section h3 {
  font-family: var(--font-sans);
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--black);
  margin-bottom: var(--space-sm);
  text-align: center;
}

.cta-section p {
  font-size: 1.125rem;
  margin-bottom: var(--space-xl);
  opacity: 0.95;
}

.cta-buttons {
  display: flex;
  gap: var(--space-md);
  justify-content: center;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  padding: var(--space-lg) var(--space-2xl);
  font-size: 1rem;
  font-weight: 700;
  border-radius: var(--radius-full);
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.btn-primary {
  background: white;
  color: var(--black);
}

.btn-primary:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}

.btn-outline {
  background: transparent;
  color: white;
  border: 2px solid white;
}

.btn-outline:hover {
  background: white;
  color: var(--accent-gold);
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
   RESPONSIVE
   ========================================== */

@media (max-width: 1023px) {
  .hide-mobile { display: none !important; }
}

@media (max-width: 768px) {
  .page-title {
    font-size: 2rem;
  }

  .info-card h2 {
    font-size: 1.5rem;
  }

  .stat-value {
    font-size: 2rem;
  }
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
      <a href="lienhe.php" class="active">Liên hệ</a>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" title="Tìm kiếm">
        <i class="fas fa-search"></i>
      </button>
      <a href="dangnhap.php" class="icon-btn hide-mobile" title="Yêu thích">
        <i class="far fa-heart"></i>
      </a>
      <a href="giohang.php" class="icon-btn" title="Giỏ hàng">
        <i class="fas fa-shopping-bag"></i>
        <span class="cart-badge" id="cartBadge">0</span>
      </a>
      <!-- Notification Bell will be auto-inserted by notifications.js after this -->
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
      <i class="fas fa-store"></i>
    </div>
    <h1 class="page-title">Về Chúng Tôi</h1>
    <p class="page-subtitle">
      Hãy để  Shop trở thành người bạn đồng hành của bạn!
    </p>
  </div>
</section>

<!-- Main Content -->
<div class="container">
  <section class="contact-section">
    <!-- Stats -->
    <div class="stats-section">
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value">44+</div>
          <div class="stat-label">Sản Phẩm</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">10+</div>
          <div class="stat-label">Khách Hàng</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">4.9★</div>
          <div class="stat-label">Đánh Giá</div>
        </div>
        <div class="stat-item">
          <div class="stat-value">24/7</div>
          <div class="stat-label">Hỗ Trợ</div>
        </div>
      </div>
    </div>

    <!-- Contact Grid -->
    <div class="contact-grid">
      <!-- Info Card -->
      <div class="info-card">
<h2>Thông tin liên hệ</h2>

        <div class="info-item">
          <div class="info-icon">
            <i class="fas fa-store"></i>
          </div>
          <div class="info-content">
            <h3>Tên cửa hàng</h3>
            <p>Mỹ Phương- Đồ Ăn Vặt</p>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon">
            <i class="fas fa-phone-alt"></i>
          </div>
          <div class="info-content">
            <h3>Hotline / Zalo</h3>
            <p><a href="tel:0325048679">0325048679</a></p>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div class="info-content">
            <h3>Địa chỉ</h3>
            <p>Ngọc Nguyên, Phương 5, Vĩnh Long</p>
          </div>
        </div>

        <!-- Google Maps Embed -->
        <div class="info-item" style="width:100%;margin-top:1rem;">
          <iframe
            src="https://www.google.com/maps?q=Ngọc+Nguyên,+Phường+5,+Vĩnh+Long&output=embed"
            width="100%" height="320" style="border:0;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.08);" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>

        <div class="info-item">
          <div class="info-icon">
            <i class="fas fa-envelope"></i>
          </div>
          <div class="info-content">
            <h3>Email</h3>
            <p><a href="mailto:contact@myphuong.vn">contact@myphuong.vn</a></p>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon">
            <i class="fas fa-clock"></i>
          </div>
          <div class="info-content">
            <h3>Giờ làm việc</h3>
            <p>T2 - CN: 8:00 - 22:00</p>
          </div>
        </div>
      </div>

      <!-- Image Card -->
      <div class="image-card">
        <img src="https://images.unsplash.com/photo-1599490659213-e2b9527bd087?w=800&q=80" alt="My Shop Store" style="min-height: 600px;">
        <div class="image-overlay">
          <h3>Đồ Ăn Vặt Ngon & Chất Lượng</h3>
          <p>Trải nghiệm hương vị tuyệt vời tại Mỹ Phương - Đồ Ăn Vặt</p>
        </div>
      </div>
    </div>

    <!-- Description Section -->
    <div class="description-section">
      <h3>Shop</h3>
      <p>
        <strong>Cảm ơn bạn đã ghé thăm shop</strong>
      </p>
      <p>
  

      <div class="highlight">
        <strong>⚠️ Quý khách lưu ý khi mua hàng:</strong>
        <ul>
          <li>Shop không nhận đặt hàng qua tin nhắn và ghi chú, quý khách vui lòng đặt hàng trên website để đảm bảo quyền lợi</li>
          <li>Khi nhận hàng, vui lòng quay lại video mở hàng để bảo vệ quyền lợi của cả hai bên</li>
<li>Nếu có bất kỳ thắc mắc hoặc khiếu nại gì, hãy nhắn tin cho shop ngay, chúng tôi sẽ hỗ trợ tận tình và nhanh chóng nhất</li>
          
        </ul>
      </div>
    </div>

    <!-- Features Grid + CTA Section đồng bộ khung -->
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-shipping-fast"></i>
        </div>
        <h3>Giao Hàng Nhanh</h3>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h3>Thanh Toán An Toàn</h3>
        <p>Hỗ trợ đa dạng hình thức thanh toán: COD, chuyển khoản, quét mã QR</p>
      </div>
      <div class="cta-section">
        <h3>Sẵn sàng mua đồ ăn vặt cùng chúng tôi?</h3>
        <div class="cvặtbuttons">
          <a href="san-pham.php" class="btn btn-primary">
            <i class="fas fa-shopping-bag"></i>
            Xem Sản Phẩm
          </a>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <p class="footer-text">© <?php echo date('Y'); ?> My Shop — ăn vì đam mê với những món ăn ngon</p>
    <p class="footer-text" style="margin-top: 0.5rem;">
      Liên hệ: <a href="tel:0325048679" style="color: var(--accent-gold); font-weight: 600;">0325048679</a> | 
      Email: <a href="mailto:contact@myshop.vn" style="color: var(--accent-gold); font-weight: 600;">contact@myshop.vn</a>
    </p>
  </div>
</footer>

<script>
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

  // ===== CART BADGE =====
  function updateCartBadge() {
    try {
      const items = JSON.parse(localStorage.getItem('myshop_cart_items') || '[]');
      const count = items.reduce((s, i) => s + (i.quantity || 0), 0);
      const badge = document.getElementById('cartBadge');
      if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
      }
    } catch(e) {
      console.error('Error updating cart badge:', e);
    }
  }

  // Initialize
  updateCartBadge();

  // ===== SMOOTH SCROLL =====
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ===== ANIMATE ON SCROLL =====
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, observerOptions);

  document.querySelectorAll('.info-item, .feature-card, .stat-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
  });

})();
</script>

</body>
</html>
