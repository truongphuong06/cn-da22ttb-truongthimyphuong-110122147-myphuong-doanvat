<link rel="stylesheet" href="assets/notification_bell.css">
<?php
require_once __DIR__ . '/auth_gate.php';
require_once __DIR__ . '/connect.php';

// Lấy thông tin user nếu đã đăng nhập
$user_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt_user = $conn->prepare("SELECT ho_ten, email FROM nguoi_dung WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('Thiếu hoặc sai ID sản phẩm');
}

// Lấy thông tin sản phẩm
try {
    $stmt = $conn->prepare(
        "SELECT sp.*, dm.ten_san_pham AS ten_danh_muc
         FROM san_pham sp
         LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id
         WHERE sp.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $san_pham = $stmt->fetch();
} catch (Throwable $e) {
    $san_pham = false;
}

if (!$san_pham) {
    http_response_code(404);
    die('Không tìm thấy sản phẩm');
}

// Check if product is locked
$isLocked = isset($san_pham['trang_thai']) && $san_pham['trang_thai'] == 0;

    // Kiểm tra người dùng đã mua sản phẩm này chưa
    $hasPurchased = false;
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    if ($isAdmin) {
        $hasPurchased = true;
    } elseif (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        try {
            $purchaseStmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM chi_tiet_don_hang cd
                INNER JOIN don_hang d ON cd.don_hang_id = d.id
                WHERE cd.san_pham_id = ? 
                AND d.nguoi_dung_id = ?
                AND d.trang_thai = 'Đã giao'
            ");
            $purchaseStmt->execute([$id, $user_id]);
            $purchaseResult = $purchaseStmt->fetch();
            if ($purchaseResult && $purchaseResult['count'] > 0) {
                $hasPurchased = true;
            }
        } catch (Exception $e) {
            $hasPurchased = false;
        }
        
        // Nếu chưa tìm thấy và có email, thử tìm theo email
        if (!$hasPurchased && isset($_SESSION['email'])) {
            try {
                $purchaseStmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM chi_tiet_don_hang cd
                    INNER JOIN don_hang d ON cd.don_hang_id = d.id
                    WHERE cd.san_pham_id = ? 
                    AND d.email = ?
                    AND d.trang_thai = 'Đã giao'
                ");
                $purchaseStmt->execute([$id, $_SESSION['email']]);
                $purchaseResult = $purchaseStmt->fetch();
                if ($purchaseResult && $purchaseResult['count'] > 0) {
                    $hasPurchased = true;
                }
            } catch (Exception $e) {
                $hasPurchased = false;
            }
        }
    }

    // Lấy bình luận và đánh giá cho sản phẩm này
    try {
        $cstmt = $conn->prepare("SELECT id, user_name, rating, comment, admin_reply, created_at FROM danh_gia WHERE san_pham_id = ? ORDER BY created_at DESC");
        $cstmt->execute([$id]);
        $comments = $cstmt->fetchAll();

        // Tính điểm trung bình
        $avg = 0; $count = count($comments);
        if ($count > 0) {
            $sum = 0;
            foreach ($comments as $c) $sum += (int)$c['rating'];
            $avg = round($sum / $count, 1);
        }
    } catch (Throwable $e) {
        $comments = [];
        $avg = 0;
        $count = 0;
    }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($san_pham['ten_san_pham']); ?>Shop - Thời trang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: var(--bg, #f7f7f8); margin: 0; color: var(--text, #1f2937); }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .breadcrumbs { font-size: 14px; margin: 10px 0 20px; }
        .breadcrumbs a { color: #1f2937; text-decoration: none; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 24px; }
        .image { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 12px; }
        .name { font-size: 24px; font-weight: 700; margin: 0 0 8px; }
        .category { color: #64748b; margin-bottom: 8px; }
        .price { font-size: 22px; font-weight: 700; color: var(--text,#222); margin: 12px 0; }
        .stock { color: #0f766e; margin-bottom: 12px; }
        .desc { line-height: 1.7; white-space: pre-line; margin-top: 12px; }
        /* Comments and rating */
        .comments { margin: 20px 0; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .comments h3 { font-size: 1.5rem; margin-bottom: 1rem; color: #1f2937; }
        .comment-list { margin-top: 24px; }
        .comment { border-top: 1px solid #e5e7eb; padding: 16px 0; }
        .comment:first-child { border-top: none; padding-top: 0; }
        .comment .meta { color: #6b7280; font-size: 0.875rem; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .comment .meta strong { color: #1f2937; font-size: 1rem; }
        .comment .comment-text { color: #374151; line-height: 1.6; margin-top: 8px; }
        .stars { color: #f59e0b; display: inline-flex; gap: 2px; }
        .stars i { font-size: 0.875rem; }
        .rating-summary { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 16px; 
            background: #f9fafb; 
            border-radius: 8px; 
            margin-bottom: 20px;
        }
        .rating-summary .stars { font-size: 1.25rem; }
        .rating-summary .rating-text { font-size: 1.125rem; font-weight: 600; color: #1f2937; }
        .rating-input { display: inline-flex; gap: 4px; cursor: pointer; }
        .star-btn { cursor: pointer; color: #d1d5db; font-size: 1.5rem; transition: color 0.2s; }
        .star-btn:hover { color: #fbbf24; }
        .star-btn.active { color: #f59e0b; }
        .qty { display: flex; align-items: center; gap: 10px; margin: 16px 0; }
        .qty input { width: 90px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .actions { display: flex; gap: 12px; margin-top: 12px; }
        .btn { border: none; padding: 12px 18px; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn-cart { background: var(--text,#222); color: #fff; }
        .btn-buy { background: var(--accent,#ff6b57); color: #fff; }
        .btn:hover{opacity:.95}
        .back { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px; color: var(--text,#222); text-decoration: none; }
        @media (max-width: 900px){ .grid{ grid-template-columns: 1fr; } }
    </style>
    <script>
    // Tự động thêm vào giỏ nếu có ?reorder=1 trên URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('reorder') === '1') {
            var form = document.getElementById('cartForm');
            if (form) {
                var qty = urlParams.get('qty') || 1;
                var qtyInput = form.querySelector('input[name="so_luong"]');
                if (qtyInput) qtyInput.value = qty;
                var actionInput = document.getElementById('actionInput');
                if (actionInput) actionInput.value = 'add_to_cart';
                // Thêm input để biết cần redirect sang giỏ hàng
                var redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_to_cart';
                redirectInput.value = '1';
                form.appendChild(redirectInput);
                form.submit();
            }
        }
    });
        function submitAction(action) {
            <?php if (!isset($_SESSION['user_id'])): ?>
            if (confirm('Bạn cần đăng nhập để mua hàng. Đăng nhập ngay?')) {
                window.location.href = 'dangnhap.php';
            }
            return;
            <?php endif; ?>
            
            const form = document.getElementById('cartForm');
            const input = document.getElementById('actionInput');
            
            if (action === 'buy_now') {
                // Chuyển sang action thêm vào giỏ rồi redirect đến giohang.php
                input.value = 'add_to_cart';
                // Thêm input ẩn để biết cần redirect
                let redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_to_cart';
                redirectInput.value = '1';
                form.appendChild(redirectInput);
            } else {
                input.value = action;
            }
            
            form.submit();
        }
    </script>
    </head>
<body>
    <div class="container">
        <a href="san-pham.php" class="back"><i class="fas fa-arrow-left"></i> Quay lại Sản phẩm</a>

        <div class="card">
            <div class="grid">
                <div>
                    <?php if (!empty($san_pham['hinh_anh'])): ?>
                        <img class="image" src="uploads/<?php echo htmlspecialchars($san_pham['hinh_anh']); ?>" alt="<?php echo htmlspecialchars($san_pham['ten_san_pham']); ?>">
                    <?php else: ?>
                        <img class="image" src="images/no-image.jpg" alt="No image">
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="name"><?php echo htmlspecialchars($san_pham['ten_san_pham']); ?></h1>
                    <div class="category">Danh mục: <?php echo htmlspecialchars($san_pham['ten_danh_muc']); ?></div>
                    <div class="price"><?php echo number_format((int)$san_pham['gia'], 0, ',', '.'); ?>đ</div>
                    <?php if($isLocked): ?>
                        <div class="stock" style="color: #e74c3c; font-weight: 600;"><i class="fas fa-times-circle"></i> Hết hàng</div>
                    <?php else: ?>
                        <div class="stock">Còn lại: <?php echo (int)$san_pham['so_luong']; ?> sản phẩm</div>
                    <?php endif; ?>
                    <form id="cartForm" action="giohang.php" method="POST">
                        <input type="hidden" name="san_pham_id" value="<?php echo (int)$san_pham['id']; ?>">
                        <input type="hidden" id="actionInput" name="action" value="add_to_cart">
                        <input type="hidden" name="so_luong" id="soLuongInput" value="1">
                        <?php if(!$isLocked): ?>
                        <div class="qty">
                          <!-- Đã xóa input số lượng theo yêu cầu -->
                        </div>
                        <div class="actions">
                                                    <!-- Đã xóa nút Thêm vào giỏ hàng và Mua ngay theo yêu cầu -->
                        </div>
                        <?php else: ?>
                        <div style="padding: 16px; background: #fee; border-radius: 8px; color: #c33; margin: 16px 0;">
                            <i class="fas fa-exclamation-triangle"></i> Sản phẩm hiện đang hết hàng. Vui lòng quay lại sau.
                        </div>
                        <?php endif; ?>
                    </form>
                    <!-- Đã xóa script xử lý Mua ngay -->
                    <div class="desc"><?php echo nl2br(htmlspecialchars((string)$san_pham['mo_ta'])); ?></div>
                </div>
            </div>
        </div>

        <div id="comments" class="comments">
            <h3><i class="fas fa-comments"></i> Đánh giá & Nhận xét</h3>
            
            <!-- Rating Summary -->
            <div class="rating-summary">
                <div>
                    <div class="rating-text"><?php echo $avg; ?>/5</div>
                    <div class="stars">
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <?php if ($i <= floor($avg)): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i - 0.5 <= $avg): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <div style="color: #6b7280; font-size: 0.875rem;">
                    Dựa trên <strong><?php echo $count; ?></strong> đánh giá
                </div>
            </div>

            <?php if (!empty($_SESSION['comment_success'])): ?>
                <div style="padding:12px;background:#ecfdf5;color:#065f46;border-radius:8px;margin-bottom:16px;border-left:4px solid #10b981;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['comment_success']); unset($_SESSION['comment_success']); ?>
                </div>
            <?php elseif (!empty($_SESSION['comment_error'])): ?>
                <div style="padding:12px;background:#fff1f2;color:#881337;border-radius:8px;margin-bottom:16px;border-left:4px solid #ef4444;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['comment_error']); unset($_SESSION['comment_error']); ?>
                </div>
            <?php endif; ?>

            <!-- Comment Form - Only show if user has purchased -->
            <?php if ($hasPurchased): ?>
            <form id="commentForm" method="POST" style="margin-top:24px;padding:20px;background:#f9fafb;border-radius:8px;">
                <h4 style="margin-bottom:16px;color:#1f2937;font-size:1.125rem;">
                    <i class="fas fa-pen"></i> Viết đánh giá của bạn
                </h4>
                <div id="reviewMessage" style="display:none;padding:12px;border-radius:8px;margin-bottom:16px;"></div>
                <input type="hidden" name="san_pham_id" value="<?php echo (int)$san_pham['id']; ?>">
                <input type="hidden" id="ratingInput" name="rating" value="5">
                
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;display:block;margin-bottom:8px;color:#374151;">
                        <i class="fas fa-star" style="color:#f59e0b;"></i> Chấm điểm:
                    </label>
                    <span class="rating-input" id="ratingStars">
                        <?php for ($s=1;$s<=5;$s++): ?>
                            <i data-value="<?php echo $s; ?>" class="star-btn fas fa-star <?php echo $s<=5 ? 'active':''; ?>"></i>
                        <?php endfor; ?>
                    </span>
                    <span id="ratingText" style="margin-left:12px;color:#6b7280;font-size:0.875rem;">Xuất sắc</span>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;display:block;margin-bottom:8px;color:#374151;">
                        <i class="fas fa-user"></i> Tên của bạn:
                    </label>
                    <input type="text" name="user_name" value="<?php echo isset($_SESSION['username'])?htmlspecialchars($_SESSION['username']):''; ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;" placeholder="Nhập tên của bạn" required>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;display:block;margin-bottom:8px;color:#374151;">
                        <i class="fas fa-comment"></i> Nhận xét:
                    </label>
                    <textarea name="comment" rows="4" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:0.875rem;resize:vertical;" placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..." required></textarea>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-cart" style="padding:10px 24px;">
                        <i class="fas fa-paper-plane"></i> Gửi đánh giá
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div style="margin-top:24px;padding:20px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b;">
                <p style="margin:0;color:#92400e;">
                    <i class="fas fa-info-circle"></i> <strong>Bạn cần mua và nhận sản phẩm này để có thể đánh giá.</strong>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Comments List -->
            <div style="margin-top:32px;">
                <h4 style="margin-bottom:16px;color:#1f2937;font-size:1.125rem;">
                    <i class="fas fa-list"></i> Các đánh giá
                </h4>
                <?php if (empty($comments)): ?>
                    <p style="font-size:0.875rem;margin:4px 0 0;color:#6b7280;">Hãy là người đầu tiên đánh giá sản phẩm này!</p>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                        <div class="comment">
                            <div class="meta">
                                <i class="fas fa-user-circle" style="font-size:1.25rem;color:#6b7280;"></i>
                                <strong><?php echo htmlspecialchars($c['user_name']); ?></strong>
                                <span class="stars">
                                    <?php for($x=1;$x<=5;$x++){ 
                                        echo $x <= (int)$c['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; 
                                    } ?>
                                </span>
                                <span style="color:#9ca3af;">·</span>
                                <span style="color:#9ca3af;">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?>
                                </span>
                            </div>
                            <div class="comment-text">
                                <?php echo nl2br(htmlspecialchars($c['comment'])); ?>
                            </div>
                            
                            <?php if (!empty($c['admin_reply'])): ?>
                            <div style="margin-top:12px;padding:12px 16px;background:#e8f5e9;border-left:4px solid #4caf50;border-radius:6px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                    <i class="fas fa-user-shield" style="color:#2e7d32;font-size:1rem;"></i>
                                    <strong style="color:#2e7d32;font-size:0.875rem;">Phản hồi từ Shop:</strong>
                                </div>
                                <div style="color:#1b5e20;font-size:0.875rem;line-height:1.5;">
                                    <?php echo nl2br(htmlspecialchars($c['admin_reply'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
<script>
    // Rating star click behavior with text feedback
    (function(){
        const stars = document.querySelectorAll('#ratingStars .star-btn');
        const input = document.getElementById('ratingInput');
        const ratingText = document.getElementById('ratingText');
        const ratingLabels = {
            1: 'Rất tệ',
            2: 'Tệ',
            3: 'Bình thường',
            4: 'Tốt',
            5: 'Xuất sắc'
        };
        
        stars.forEach(s => s.addEventListener('click', () => {
            const v = parseInt(s.getAttribute('data-value')) || 5;
            input.value = v;
            stars.forEach(st => st.classList.toggle('active', parseInt(st.getAttribute('data-value')) <= v));
            if (ratingText) {
                ratingText.textContent = ratingLabels[v] || 'Xuất sắc';
            }
        }));
        
        // Hover effect
        stars.forEach(s => {
            s.addEventListener('mouseenter', () => {
                const v = parseInt(s.getAttribute('data-value'));
                stars.forEach(st => {
                    const val = parseInt(st.getAttribute('data-value'));
                    st.style.color = val <= v ? '#f59e0b' : '#d1d5db';
                });
            });
        });
        
        const ratingContainer = document.getElementById('ratingStars');
        if (ratingContainer) {
            ratingContainer.addEventListener('mouseleave', () => {
                const currentValue = parseInt(input.value);
                stars.forEach(st => {
                    const val = parseInt(st.getAttribute('data-value'));
                    st.style.color = val <= currentValue ? '#f59e0b' : '#d1d5db';
                });
            });
        }
    })();
    
    // Handle review form submission with AJAX
    document.getElementById('commentForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const messageDiv = document.getElementById('reviewMessage');
        const submitBtn = this.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
        
        fetch('xu_ly_danh_gia.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            messageDiv.style.display = 'block';
            
            if (data.success) {
                messageDiv.style.background = '#ecfdf5';
                messageDiv.style.color = '#065f46';
                messageDiv.style.borderLeft = '4px solid #10b981';
                messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                
                // Reset form and reload page after 2 seconds
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                messageDiv.style.background = '#fff1f2';
                messageDiv.style.color = '#881337';
                messageDiv.style.borderLeft = '4px solid #ef4444';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi đánh giá';
            }
        })
        .catch(error => {
            messageDiv.style.display = 'block';
            messageDiv.style.background = '#fff1f2';
            messageDiv.style.color = '#881337';
            messageDiv.style.borderLeft = '4px solid #ef4444';
            messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Có lỗi xảy ra. Vui lòng thử lại.';
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi đánh giá';
        });
    });
</script>
<link rel="stylesheet" href="assets/chatbot.css">
<link rel="stylesheet" href="assets/notifications.css">
<?php include 'assets/chatbot_session.php'; ?>
<script src="assets/notification_bell.js" defer></script>
<script src="assets/chatbot.js" defer></script>
</html>


