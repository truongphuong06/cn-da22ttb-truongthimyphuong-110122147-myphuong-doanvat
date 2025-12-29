<?php
/**
 * Sale Page
 * Trang khuyến mãi
 */

// Load database connection
require_once __DIR__ . '/connect.php';

try {
    // Lấy user
    $user_info = null;
    if (isLoggedIn()) {
        $stmt = $conn->prepare("SELECT ten_dang_nhap, ho_ten, email FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lấy sản phẩm có giảm giá
    $search = '';
    $whereConditions = "sp.gia_giam > 0 AND sp.gia_giam < sp.gia";
    $params = [];
    
    if (!empty($_GET['search'])) {
      $search = trim((string)$_GET['search']);
      $whereConditions .= " AND sp.ten_san_pham LIKE ?";
      $params[] = '%' . $search . '%';
    }
    
  }
.header .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 4rem;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.5rem;
  font-weight: 600;
  letter-spacing: -0.025em;
  color: #1c1917;
  text-decoration: none;
}

.brand-logo img {
  height: 3.8rem;
  width: auto;
}

.nav { display: flex; gap: 2rem; align-items: center; }
.nav a {
  text-decoration: none;
  color: #57534e;
  font-weight: 500;
  padding: 0.625rem 1.25rem;
  border-radius: 25px;
  border: 2px solid #d6d3d1;
  font-size: 0.875rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
  transition: all 0.3s ease;
}
.nav a:hover { 
  color: #1c1917;
  background: #f5f5f4;
  border-color: #a8a29e;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.nav a.active { 
  color: #1c1917;
  background: white;
  border-color: #1c1917;
  font-weight: 600;
  box-shadow: 0 2px 12px rgba(0,0,0,0.12);
}

.hero {
  background: linear-gradient(135deg, #ff3b3b, #ff8c42);
  color: white;
  padding: 3rem 0;
  text-align: center;
}

.hero h1 { font-size: 3rem; margin-bottom: 0.5rem; }

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 2rem;
  padding: 3rem 0;
}

.product-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transition: transform 0.3s;
  position: relative;
}

.product-card:hover { transform: translateY(-8px); }

.discount-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: #ff3b3b;
  color: white;
  padding: 8px 16px;
  border-radius: 25px;
  font-weight: 700;
  z-index: 2;
}

.product-image {
  width: 100%;
  height: 300px;
  object-fit: cover;
  background: #f0f0f0;
  display: block;
}

.product-info { padding: 1.5rem; }

.product-name {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  min-height: 2.5rem;
}

.product-prices {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.original-price {
  text-decoration: line-through;
  color: #999;
  font-size: 0.9rem;
}

.sale-price {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ff3b3b;
}

.quantity-control {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin: 1rem 0;
}

.qty-btn {
  width: 42px;
  height: 42px;
  background: white;
  border: 2px solid #9ca3af;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1.25rem;
  font-weight: 600;
  color: #374151;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.qty-btn:hover {
  border-color: #6b7280;
  background: #f9fafb;
}

.qty-display {
  width: 70px;
  height: 42px;
  background: white;
  border: 2px solid #9ca3af;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
}

.product-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

.add-cart-btn {
  width: 100%;
  padding: 0.875rem;
  background: #111;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.add-cart-btn:hover {
  background: #333;
  transform: translateY(-2px);
}

.buy-now-btn {
  width: 100%;
  padding: 0.875rem;
  background: #f5f5dc;
  color: #111;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.buy-now-btn:hover {
  background: #e6e6cd;
  transform: translateY(-2px);
}

.no-products {
  grid-column: 1 / -1;
  text-align: center;
  padding: 4rem;
  color: #666;
}

.cart-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #ff3b3b;
  color: white;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
}

.icon-btn {
  position: relative;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
  border-radius: 8px;
  text-decoration: none;
  color: #333;
}

.header-actions { display: flex; gap: 0.5rem; align-items: center; }

.icon-btn {
  position: relative;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 2px solid #d6d3d1;
  border-radius: 8px;
  text-decoration: none;
  color: #57534e;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.icon-btn:hover {
  background: #f5f5f4;
  border-color: #a8a29e;
  color: #1c1917;
}

.cart-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #ff3b3b;
  color: white;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
}
</style>
</head>
<body>

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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="logout.php" class="icon-btn"><i class="fas fa-sign-in-alt"></i></a>
      <?php else: ?>
        <a href="dangnhap.php" class="icon-btn"><i class="fas fa-user"></i></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="hero">
  <div class="container">
    <h1>🔥 FLASH SALE 🔥</h1>
    <p>Giảm giá sốc - Nhanh tay kẻo lỡ!</p>
  </div>
</section>

<section class="container">
  <div class="products-grid">
    <?php 
    $hasProducts = false;
    while($sp = $stmt->fetch()): 
        $summary = isset($commentSummary[$sp['id']]) ? $commentSummary[$sp['id']] : null;
        $rating = $summary ? (float)$summary['avg'] : 0;
        $reviews = $summary ? (int)$summary['count'] : 0;
      $hasProducts = true;
      $id = (int)$sp['id'];
      $name = htmlspecialchars($sp['ten_san_pham']);
      $originalPrice = (float)$sp['gia'];
      $salePrice = (float)$sp['gia_giam'];
      $discount = (int)$sp['discount_percent'];
      $isLocked = (isset($sp['trang_thai']) && $sp['trang_thai'] == 0) || ((int)($sp['so_luong'] ?? 0) <= 0);
      // Xử lý đường dẫn hình ảnh
      $image = 'https://via.placeholder.com/300x400?text=No+Image';
      if (!empty($sp['hinh_anh'])) {
        $imgPath = $sp['hinh_anh'];
        if (strpos($imgPath, 'http') === 0) {
          $image = $imgPath;
        } else {
          $image = 'uploads/' . $imgPath;
        }
      }
      $image = htmlspecialchars($image);
    ?>
    <div class="product-card<?php echo $isLocked ? ' product-locked' : ''; ?>" style="position:relative;" data-id="<?php echo $id; ?>">
      <div class="discount-badge" style="background:<?php echo $isLocked ? '#dc3545' : '#ff3b3b'; ?>;">
        <?php echo $isLocked ? 'Hết hàng' : ('-' . $discount . '%'); ?>
      </div>
      <div style="position:relative;">
        <a href="chitiet_san_pham.php?id=<?php echo $id; ?>">
          <img src="<?php echo $image; ?>" alt="<?php echo $name; ?>" class="product-image" style="cursor:pointer;" onerror="this.src='https://via.placeholder.com/300x400?text=No+Image'">
        </a>
        <!-- Quick Actions trên ảnh -->
        <div class="product-quick-actions" style="position:absolute;top:12px;right:12px;display:flex;flex-direction:column;gap:10px;z-index:3;">
          <button class="action-btn quick-view-btn" onclick="openQuickView(<?php echo $id; ?>)" title="Xem nhanh" style="background:#fff;border:1px solid #eee;border-radius:8px;padding:0.5em 0.7em;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <i class="far fa-eye"></i>
          </button>
          <button class="action-btn like-btn" id="like-btn-<?php echo $id; ?>" data-id="<?php echo $id; ?>" onclick="toggleLike(<?php echo $id; ?>)" title="Yêu thích" style="background:#fff;border:1px solid #eee;border-radius:8px;padding:0.5em 0.7em;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <i class="far fa-heart"></i>
            <span class="like-count" id="like-count-<?php echo $id; ?>">0</span>
          </button>
        </div>
      </div>
      <div class="product-info">
        <h3 class="product-name" style="cursor:pointer;" onclick="openQuickView(<?php echo $id; ?>)"><?php echo $name; ?></h3>
          <?php if ($reviews > 0): ?>
          <div class="product-rating" style="margin-bottom:0.5em;display:flex;align-items:center;gap:0.5em;">
            <span>
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= floor($rating)): ?>
                  <i class="fas fa-star" style="color:#f59e0b;font-size:1em;"></i>
                <?php elseif ($i - 0.5 <= $rating): ?>
                  <i class="fas fa-star-half-alt" style="color:#f59e0b;font-size:1em;"></i>
                <?php else: ?>
                  <i class="far fa-star" style="color:#f59e0b;font-size:1em;"></i>
                <?php endif; ?>
              <?php endfor; ?>
            </span>
            <span style="font-size:0.95em;color:#555;">
              <?php echo number_format($rating, 1); ?> (<?php echo $reviews; ?>)
            </span>
          </div>
          <?php endif; ?>
        <?php 
          $desc = '';
          if (!empty($sp['mo_ta_ngan'])) $desc = $sp['mo_ta_ngan'];
          elseif (!empty($sp['mo_ta'])) $desc = $sp['mo_ta'];
        ?>
        <?php if (!empty($desc)): ?>
          <div class="product-desc" style="color:#555; font-size:0.95em; margin-bottom:0.5em;">
            <?php echo htmlspecialchars(strip_tags($desc)); ?>
          </div>
        <?php endif; ?>
        <div class="product-prices">
          <span class="original-price"><?php echo number_format($originalPrice, 0, ',', '.'); ?>đ</span>
          <span class="sale-price"><?php echo number_format($salePrice, 0, ',', '.'); ?>đ</span>
        </div>
        <div class="quantity-control">
          <button class="qty-btn" onclick="changeQty(<?php echo $id; ?>, -1)" <?php echo $isLocked ? 'disabled' : ''; ?>>−</button>
          <div class="qty-display" id="qty-<?php echo $id; ?>">1</div>
          <button class="qty-btn" onclick="changeQty(<?php echo $id; ?>, 1)" <?php echo $isLocked ? 'disabled' : ''; ?>>+</button>
        </div>
        <div class="product-actions" style="display:flex;gap:0.5em;">
          <button class="add-cart-btn" onclick='addToCartWithQty(<?php echo $id; ?>, <?php echo json_encode($name); ?>, <?php echo $salePrice; ?>, <?php echo json_encode($image); ?>)' <?php echo $isLocked ? 'disabled' : ''; ?>>
            <i class="fas fa-shopping-cart"></i> <?php echo $isLocked ? 'Hết hàng' : 'Thêm vào giỏ'; ?>
          </button>
          <button class="buy-now-btn" onclick='buyNow(<?php echo $id; ?>, <?php echo json_encode($name); ?>, <?php echo $salePrice; ?>, <?php echo json_encode($image); ?>)' <?php echo $isLocked ? 'disabled' : ''; ?>>
            <i class="fas fa-bolt"></i> <?php echo $isLocked ? 'Hết hàng' : 'Mua Ngay'; ?>
          </button>
        </div>
      </div>
    </div>
    <?php endwhile; ?>

    <!-- Popup xem nhanh -->
    <div id="quickViewModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
      <div style="background:#fff;border-radius:16px;max-width:500px;width:90vw;box-shadow:0 8px 32px rgba(0,0,0,0.18);padding:2rem;position:relative;">
        <button onclick="closeQuickView()" style="position:absolute;top:12px;right:12px;background:#eee;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:1.2rem;">
          <i class="fas fa-times"></i>
        </button>
        <div id="quickViewContent"></div>
      </div>
    </div>
    
    <?php if (!$hasProducts): ?>
    <div class="no-products">
      <i class="fas fa-tags" style="font-size: 4rem; opacity: 0.3;"></i>
      <h3>Chưa có sản phẩm giảm giá</h3>
      <p>Vui lòng quay lại sau!</p>
    </div>
    <?php endif; ?>
  </div>
</section>

<script>
// ===== YÊU THÍCH SẢN PHẨM =====
function toggleLike(id) {
  var btn = document.getElementById('like-btn-' + id);
  var countEl = document.getElementById('like-count-' + id);
  if (!btn || !countEl) return;
  var liked = btn.classList.contains('favorited');
  if (liked) {
    btn.classList.remove('favorited');
    btn.querySelector('i').classList.remove('fa-heart');
    btn.querySelector('i').classList.add('far','fa-heart');
    let count = parseInt(countEl.textContent) || 0;
    countEl.textContent = Math.max(0, count - 1);
  } else {
    btn.classList.add('favorited');
    btn.querySelector('i').classList.remove('far');
    btn.querySelector('i').classList.add('fa-heart');
    let count = parseInt(countEl.textContent) || 0;
    countEl.textContent = count + 1;
  }
}

// ===== XEM NHANH SẢN PHẨM =====
function openQuickView(id) {
  // Lấy thông tin sản phẩm từ DOM
  var card = document.querySelector('.product-card[data-id="' + id + '"]');
  if (!card) return;
  var img = card.querySelector('img').src;
  var name = card.dataset.name || card.querySelector('.product-name a')?.textContent || '';
  var desc = card.dataset.description || '<em>Không có mô tả.</em>';
  var price = card.dataset.price || card.querySelector('.sale-price')?.textContent || '';
  var oldPrice = card.querySelector('.original-price')?.textContent || '';
  var likeCount = card.querySelector('.like-count')?.textContent || '0';
  var qty = quantities[id] || 1;
  var rating = 0;
  var reviews = 0;
  var ratingEl = card.querySelector('.product-rating .review-count');
  if (ratingEl) {
    var match = ratingEl.textContent.match(/([\d\.]+) \((\d+)\)/);
    if (match) {
      rating = parseFloat(match[1]);
      reviews = parseInt(match[2]);
    }
  }
  var starsHtml = '';
  for (var i = 1; i <= 5; i++) {
    if (i <= Math.floor(rating)) {
      starsHtml += '<i class="fas fa-star" style="color:#f59e0b;"></i>';
    } else if (i - 0.5 <= rating) {
      starsHtml += '<i class="fas fa-star-half-alt" style="color:#f59e0b;"></i>';
    } else {
      starsHtml += '<i class="far fa-star" style="color:#f59e0b;"></i>';
    }
  }
  var quickViewHtml = `
    <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;text-align:center;min-width:220px;">
        <img src="${img}" alt="${name}" style="max-width:260px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);margin-bottom:1rem;">
      </div>
      <div style="flex:2;min-width:220px;">
        <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:0.5em;">${name}</h2>
        <div style="margin-bottom:1em;color:#555;">${desc}</div>
        <div class="product-rating" style="margin-bottom:1em;display:flex;align-items:center;gap:0.5em;">
          <span>${starsHtml}</span>
          <span class="review-count" style="font-size:0.95em;color:#555;">${rating.toFixed(1)} (${reviews})</span>
        </div>
        <div style="font-size:1.1rem;margin-bottom:1em;display:flex;align-items:center;gap:1em;">
          <span style="color:#999;text-decoration:line-through;">${oldPrice}</span>
          <span style="color:#ff3b3b;font-weight:700;">${price}</span>
        </div>
        <div style="margin-bottom:1em;display:flex;align-items:center;gap:1em;">
          <button class="action-btn like-btn" style="background:#fff;border:1px solid #eee;border-radius:8px;padding:0.5em 0.7em;" onclick="toggleLike(${id})">
            <i class="far fa-heart"></i> <span class="like-count">${likeCount}</span>
          </button>
        </div>
        <div style="margin-bottom:1em;display:flex;align-items:center;gap:1em;">
          <span>Số lượng:</span>
          <button class="qty-btn" onclick="changeQty(${id}, -1)">−</button>
          <span class="qty-display" id="quick-qty-${id}">${qty}</span>
          <button class="qty-btn" onclick="changeQty(${id}, 1)">+</button>
        </div>
        <button class="add-cart-btn" style="width:100%;margin-bottom:0.5em;" onclick="addToCartWithQty(${id}, '${name.replace(/'/g,"\'")}', ${price.replace(/\D/g,'')}, '${img}')">
          <i class="fas fa-shopping-cart"></i> Thêm vào giỏ
        </button>
        <button class="buy-now-btn" style="width:100%;" onclick="buyNow(${id}, '${name.replace(/'/g,"\'")}', ${price.replace(/\D/g,'')}, '${img}')">
          <i class="fas fa-bolt"></i> Mua Ngay
        </button>
      </div>
    </div>
  `;
  document.getElementById('quickViewContent').innerHTML = quickViewHtml;
  document.getElementById('quickViewModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
  setTimeout(function(){
    var qtyEl = document.getElementById('quick-qty-' + id);
    if (qtyEl) qtyEl.textContent = quantities[id] || 1;
  }, 100);
}
function closeQuickView() {
  document.getElementById('quickViewModal').style.display = 'none';
  document.body.style.overflow = '';
}
// ...existing code...
const CART_KEY = 'myphuongshop_cart_items'; // Đã đồng bộ key
const quantities = {};

// Badge giỏ hàng đồng bộ
function updateBadge() {
  const cart = getCart();
  const total = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
  const badge = document.querySelector('.cart-badge');
  if (badge) {
    badge.textContent = total;
    badge.style.display = total > 0 ? 'flex' : 'none';
  }
}

// Thêm vào giỏ hàng
function addToCart(id, name, price, image) {
  const cart = getCart();
  const existing = cart.find(item => item.id === id);
  if (existing) {
    existing.quantity++;
  } else {
    cart.push({ id, name, price, image, quantity: 1 });
  }
  saveCart(cart);
  updateBadge();
  alert('Đã thêm ' + name + ' vào giỏ hàng!');
}

function addToCartWithQty(id, name, price, image) {
  const qty = quantities[id] || 1;
  const cart = getCart();
  const existing = cart.find(item => item.id === id);
  if (existing) {
    existing.quantity += qty;
  } else {
    cart.push({ id, name, price, image, quantity: qty });
  }
  saveCart(cart);
  updateBadge();
  alert('Đã thêm ' + qty + ' sản phẩm "' + name + '" vào giỏ hàng!');
  quantities[id] = 1;
  document.getElementById('qty-' + id).textContent = '1';
}

// Mua ngay: thêm vào giỏ rồi chuyển sang giohang.php
function buyNow(id, name, price, image) {
  const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
  if (!isLoggedIn) {
    if (confirm('Bạn cần đăng nhập để mua hàng. Đăng nhập ngay?')) {
      window.location.href = 'dangnhap.php';
    }
    return;
  }
  const qty = quantities[id] || 1;
  const cart = getCart();
  const existing = cart.find(item => item.id === id);
  if (existing) {
    existing.quantity = Math.min(99, existing.quantity + qty);
  } else {
    cart.push({ id, name, price, image, quantity: qty });
  }
  saveCart(cart);
  updateBadge();
  window.location.href = 'giohang.php';
}

document.addEventListener('DOMContentLoaded', updateBadge);

function changeQty(productId, change) {
  if (!quantities[productId]) quantities[productId] = 1;
  quantities[productId] = Math.max(1, quantities[productId] + change);
  document.getElementById('qty-' + productId).textContent = quantities[productId];
}

function getCart() {
  try {
    return JSON.parse(localStorage.getItem(CART_KEY) || '[]');
  } catch(e) { return []; }
}

function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  updateBadge();
}

function updateBadge() {
  const cart = getCart();
  const total = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
  const badge = document.querySelector('.cart-badge');
  if (badge) {
    badge.textContent = total;
    badge.style.display = total > 0 ? 'flex' : 'none';
  }
}

function addToCart(id, name, price, image) {
  const cart = getCart();
  const existing = cart.find(item => item.id === id);
  
  if (existing) {
    existing.quantity++;
  } else {
    cart.push({ id, name, price, image, quantity: 1 });
  }
  
  saveCart(cart);
  alert('Đã thêm ' + name + ' vào giỏ hàng!');
}

function addToCartWithQty(id, name, price, image) {
  const qty = quantities[id] || 1;
  const cart = getCart();
  const existing = cart.find(item => item.id === id);
  
  if (existing) {
    existing.quantity += qty;
  } else {
    cart.push({ id, name, price, image, quantity: qty });
  }
  
  saveCart(cart);
  alert('Đã thêm ' + qty + ' sản phẩm "' + name + '" vào giỏ hàng!');
  quantities[id] = 1;
  document.getElementById('qty-' + id).textContent = '1';
}

function buyNow(id, name, price, image) {
  const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
  if (!isLoggedIn) {
    if (confirm('Bạn cần đăng nhập để mua hàng. Đăng nhập ngay?')) {
      window.location.href = 'dangnhap.php';
    }
    return;
  }
  const qty = quantities[id] || 1;
  const cart = getCart();
  const existing = cart.find(item => item.id === id);
  if (existing) {
    existing.quantity = Math.min(99, existing.quantity + qty);
  } else {
    cart.push({
      id: id,
      name: name,
      price: price,
      image: image,
      quantity: qty
    });
  }
  saveCart(cart);
  // Chuyển đến trang giỏ hàng để xác nhận và thanh toán
  window.location.href = 'giohang.php';
}

let checkoutData = {};

// Adjust quantity in checkout modal
function adjustCheckoutQty(delta) {
  const qtyEl = document.getElementById('checkoutQtyValue');
  if (!qtyEl) return;
  
  let currentQty = parseInt(qtyEl.textContent) || 1;
  currentQty = Math.max(1, currentQty + delta);
  
  qtyEl.textContent = currentQty;
  
  // Update checkoutData
  if (checkoutData) {
    checkoutData.quantity = currentQty;
    
    // Recalculate totals
    const subtotal = checkoutData.price * currentQty;
    const shipping = subtotal < 50000 ? 5000 : 0;
    const total = subtotal + shipping;
    
    document.getElementById('checkoutPrice').textContent = subtotal.toLocaleString('vi-VN') + '₫';
    document.getElementById('summarySubtotal').textContent = subtotal.toLocaleString('vi-VN') + '₫';
    document.getElementById('summaryShipping').textContent = shipping === 0 ? 'Miễn phí' : shipping.toLocaleString('vi-VN') + '₫';
    document.getElementById('summaryTotal').textContent = total.toLocaleString('vi-VN') + '₫';
  }
}

function openCheckoutModal() {
  const modal = document.getElementById('checkoutModal');
  if (!modal || !checkoutData) return;
  
  const { name, price, quantity, image } = checkoutData;
  const subtotal = price * quantity;
  const shipping = subtotal < 50000 ? 5000 : 0;
  const total = subtotal + shipping;
  
  document.getElementById('checkoutImg').src = image;
  document.getElementById('checkoutName').textContent = name;
  document.getElementById('checkoutQtyValue').textContent = quantity;
  document.getElementById('checkoutPrice').textContent = subtotal.toLocaleString('vi-VN') + '₫';
  document.getElementById('summarySubtotal').textContent = subtotal.toLocaleString('vi-VN') + '₫';
  document.getElementById('summaryShipping').textContent = shipping === 0 ? 'Miễn phí' : shipping.toLocaleString('vi-VN') + '₫';
  document.getElementById('summaryTotal').textContent = total.toLocaleString('vi-VN') + '₫';
  
  // Tự động điền thông tin khách hàng đã lưu
  loadCustomerInfo();
  
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Generate QR code for the order
  generateQRCode(checkoutData.name, total);
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
      <?php if ($user_info): ?>
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

function togglePaymentInfo() {
  const payment = document.getElementById('paymentSelect').value;
  const bankInfo = document.getElementById('bankInfoSection');
  const qrCode = document.getElementById('qrCodeSection');
  
  if (payment === 'bank') {
    bankInfo.style.display = 'block';
    qrCode.style.display = 'none';
  } else if (payment === 'qr') {
    bankInfo.style.display = 'none';
    qrCode.style.display = 'block';
  } else {
    bankInfo.style.display = 'none';
    qrCode.style.display = 'none';
  }
}

function generateQRCode(productName, amount) {
  const accountNo = '0325048679';
  const accountName = 'TRUONG THI MY PHUONG';
  const bankCode = 'MB Bank';
  const description = encodeURIComponent(productName);
  
  // Create VietQR URL
  const qrUrl = `https://img.vietqr.io/image/MB-${accountNo}-compact2.png?amount=${amount}&addInfo=${description}&accountName=${accountName}`;
  
  document.getElementById('qrCodeImage').src = qrUrl;
}

function closeCheckoutModal() {
  const modal = document.getElementById('checkoutModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}

function handleCheckoutSubmit(e) {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  const subtotal = checkoutData.price * checkoutData.quantity;
  const shipping = subtotal < 50000 ? 30000 : 0;
  const voucherDiscount = parseInt(document.getElementById('giam_giaSale')?.value || 0);
  const voucherCode = document.getElementById('ma_voucherSale')?.value || '';
  const total = subtotal + shipping - voucherDiscount;
  
  const orderData = {
    items: [{
      id: checkoutData.id,
      name: checkoutData.name,
      price: checkoutData.price,
      quantity: checkoutData.quantity,
      size: 'M',
      image: checkoutData.image || '',
      category: checkoutData.category || ''
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
  
  fetch('xu_ly_don_hang.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(orderData)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Lưu thông tin khách hàng để dùng cho lần sau
      saveCustomerInfo(orderData.customer);
      alert(`✅ Đặt hàng thành công!\n\nMã đơn hàng: ${data.orderCode}\nTổng thanh toán: ${formatPrice(total)}\n\nCảm ơn bạn đã mua hàng!`);
      closeCheckoutModal();
      e.target.reset();
    } else {
      alert('❌ Lỗi: ' + (data.message || 'Không thể đặt hàng'));
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('❌ Lỗi kết nối: ' + err.message);
  });
  
  return false;
}

function formatPrice(price) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
}

// Search functionality
function toggleSearch() {
  const searchTerm = prompt('🔍 Tìm kiếm sản phẩm sale:');
  if (searchTerm && searchTerm.trim()) {
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('search', searchTerm.trim());
      window.location.href = url.toString();
    } catch (e) {
      window.location.href = window.location.pathname + '?search=' + encodeURIComponent(searchTerm.trim());
    }
  }
}

// Initialize
updateBadge();

// Voucher functions for sale.php
let originalTotalSale = 0;
let currentDiscountSale = 0;

function applyVoucherSale() {
    const code = document.getElementById('voucherCodeSale').value.trim().toUpperCase();
    
    if (!code) {
        showVoucherMessageSale('Vui lòng nhập mã voucher', 'error');
        return;
    }
    
    const subtotalText = document.getElementById('summarySubtotal').textContent;
    originalTotalSale = parseInt(subtotalText.replace(/[^\d]/g, '')) || 0;
    
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
            let discount = 0;
            
            if (voucher.loai_giam === 'phan_tram') {
                discount = Math.floor(originalTotalSale * voucher.gia_tri_giam / 100);
            } else {
                discount = voucher.gia_tri_giam;
            }
            
            if (discount > originalTotalSale) {
                discount = originalTotalSale;
            }
            
            currentDiscountSale = discount;
            
            document.getElementById('ma_voucherSale').value = code;
            document.getElementById('giam_giaSale').value = discount;
            
            document.getElementById('summaryDiscountSale').textContent = '-' + formatMoneySale(discount) + '₫';
            document.getElementById('discountRowSale').style.display = 'flex';
            
            updateTotalSale();
            
            showVoucherMessageSale('✓ ' + res.message, 'success');
        } else {
            showVoucherMessageSale('✗ ' + res.message, 'error');
            resetVoucherSale();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showVoucherMessageSale('✗ Có lỗi xảy ra', 'error');
    });
}

function resetVoucherSale() {
    currentDiscountSale = 0;
    document.getElementById('ma_voucherSale').value = '';
    document.getElementById('giam_giaSale').value = 0;
    document.getElementById('discountRowSale').style.display = 'none';
    updateTotalSale();
}

function updateTotalSale() {
    const subtotalText = document.getElementById('summarySubtotal')?.textContent || '0';
    const shippingText = document.getElementById('summaryShipping')?.textContent || '0';
    
    const subtotal = parseInt(subtotalText.replace(/[^\d]/g, '')) || 0;
    const shipping = parseInt(shippingText.replace(/[^\d]/g, '')) || 0;
    
    const total = subtotal - currentDiscountSale + shipping;
    
    if (document.getElementById('summaryTotal')) {
        document.getElementById('summaryTotal').textContent = formatMoneySale(total) + '₫';
    }
}

function showVoucherMessageSale(message, type) {
    const msgEl = document.getElementById('voucherMessageSale');
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

function formatMoneySale(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

document.addEventListener('DOMContentLoaded', function() {
    const voucherInput = document.getElementById('voucherCodeSale');
    if (voucherInput) {
        voucherInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyVoucherSale();
            }
        });
    }
});
</script>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkoutModal" style="display:none;">
  <div class="checkout-modal">
    <div class="modal-header">
      <h3 class="modal-title">Thanh Toán</h3>
      <button class="modal-close" onclick="closeCheckoutModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <div class="checkout-product">
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

      <form class="checkout-form" id="checkoutForm" onsubmit="return handleCheckoutSubmit(event)">
        <div class="form-group">
          <label class="form-label">Họ và tên *</label>
          <input type="text" class="form-input" name="fullname" required placeholder="Nguyễn Văn A" value="<?php echo isset($user_info['ten_dang_nhap']) ? htmlspecialchars($user_info['ten_dang_nhap']) : ''; ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Số điện thoại *</label>
            <input type="tel" class="form-input" name="phone" required placeholder="0123456789">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-input" name="email" placeholder="email@example.com" value="<?php echo isset($user_info['email']) ? htmlspecialchars($user_info['email']) : ''; ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Địa chỉ giao hàng *</label>
          <input type="text" class="form-input" name="address" required placeholder="Số nhà, tên đường">
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
            <select class="form-select" name="payment" id="paymentSelect" onchange="togglePaymentInfo()">
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

        <!-- Voucher Section -->
        <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #dee2e6;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <label class="form-label" style="margin: 0;"><i class="fas fa-ticket-alt"></i> Mã giảm giá</label>
            <button type="button" onclick="showAvailableVouchers()" style="padding: 5px 12px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px;">
              <i class="fas fa-tags"></i> Xem mã khả dụng
            </button>
          </div>
          <div style="display: flex; gap: 10px;">
            <input type="text" class="form-input" id="voucherCodeSale" placeholder="Nhập mã voucher" style="flex: 1;">
            <button type="button" onclick="applyVoucherSale()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; white-space: nowrap;">
              <i class="fas fa-check"></i> Áp dụng
            </button>
          </div>
          <div id="voucherMessageSale" style="margin-top: 10px; padding: 8px; border-radius: 5px; display: none; font-size: 14px;"></div>
          <input type="hidden" name="ma_voucher" id="ma_voucherSale" value="">
          <input type="hidden" name="giam_gia" id="giam_giaSale" value="0">
        </div>

        <!-- Bank Transfer Info Section -->
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

        <!-- QR Code Section -->
        <div id="qrCodeSection" style="display: none; margin-top: 1.5rem; text-align: center;">
          <div style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 2rem 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); max-width: 420px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
              <img id="qrCodeImage" src="" alt="QR Code" style="max-width: 280px; width: 100%; height: auto; border-radius: 8px;">
            </div>
            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e0e0e0;">
              <p style="margin: 0; font-size: 0.9rem; color: #374151; font-weight: 600;">
                <i class="fas fa-mobile-alt" style="color: #3b82f6;"></i> Quét mã QR để thanh toán
              </p>
              <p style="margin: 0.5rem 0 0 0; font-size: 0.8rem; color: #6b7280;">
                Sử dụng app ngân hàng để quét mã
              </p>
            </div>
          </div>
        </div>

        <div class="checkout-summary">
          <div class="summary-row">
            <span>Tạm tính</span>
            <span id="summarySubtotal">0₫</span>
          </div>
          <div class="summary-row" id="discountRowSale" style="display: none; color: #28a745;">
            <span><i class="fas fa-tag"></i> Giảm giá</span>
            <span id="summaryDiscountSale">0₫</span>
          </div>
          <div class="summary-row">
            <span>Phí vận chuyển</span>
            <span id="summaryShipping">5.000₫</span>
          </div>
          <div class="summary-row total">
            <span>Tổng cộng</span>
            <span id="summaryTotal">0₫</span>
          </div>
        </div>

        <button type="submit" class="checkout-submit-btn">
          <i class="fas fa-check-circle"></i> Xác Nhận Đặt Hàng
        </button>
      </form>
    </div>
  </div>
</div>

<style>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
}

.checkout-modal {
  background: white;
  border-radius: 16px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.modal-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: #f3f4f6;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #e5e7eb;
}

.modal-body {
  padding: 1.5rem;
}

.checkout-product {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 12px;
  margin-bottom: 1.5rem;
}

.checkout-product-img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 8px;
}

.checkout-product-info {
  flex: 1;
}

.checkout-product-name {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #1f2937;
}

.checkout-product-details {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.checkout-product-price {
  font-size: 1.25rem;
  font-weight: 700;
  color: #ff3b3b;
}

.checkout-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.form-input, .form-select, .form-textarea {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.checkout-summary {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 8px;
  margin-top: 1rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 0.875rem;
}

.summary-row.total {
  border-top: 2px solid #e5e7eb;
  margin-top: 0.5rem;
  padding-top: 1rem;
  font-size: 1.125rem;
  font-weight: 700;
  color: #1f2937;
}

.checkout-submit-btn {
  width: 100%;
  padding: 1rem;
  background: #111;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-top: 1rem;
  transition: all 0.3s;
}

.checkout-submit-btn:hover {
  background: #333;
  transform: translateY(-2px);
}

@media (max-width: 640px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>

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
// Voucher list functions
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
    if (modal) modal.style.display = 'none';
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
</script>

</body>
</html>
