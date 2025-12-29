<?php
/**
 * Cron job để tạo thông báo tự động định kỳ
 * Chạy file này mỗi ngày hoặc thiết lập Windows Task Scheduler
 * 
 * Cách chạy: php cron_notifications.php
 */

require_once 'connect.php';
require_once 'notification_helpers.php';

echo "=== CRON JOB: Auto Notifications ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Kiểm tra sản phẩm sắp hết hàng
echo "Checking low stock products...\n";
$low_stock = $conn->query("SELECT id, ten_san_pham, so_luong FROM san_pham WHERE so_luong <= 5 AND so_luong > 0");
$count = 0;
while ($product = $low_stock->fetch(PDO::FETCH_ASSOC)) {
    if (auto_notify_low_stock($product['id'], $product['ten_san_pham'], $product['so_luong'])) {
        $count++;
        echo "  - Notified: {$product['ten_san_pham']} (Stock: {$product['so_luong']})\n";
    }
}
echo "Low stock notifications: {$count}\n\n";

// 2. Tạo Flash Sale (chạy vào 9h sáng mỗi ngày)
$current_hour = (int)date('H');
if ($current_hour == 9) {
    echo "Creating flash sale notification...\n";
    if (schedule_flash_sale()) {
        echo "  - Flash sale created!\n";
    }
}
echo "\n";

// 3. Kiểm tra milestone đơn hàng
echo "Checking order milestones...\n";
auto_notify_order_milestone();
echo "Milestone check completed\n\n";

// 4. Xóa thông báo đã hết hạn
echo "Cleaning expired notifications...\n";
$deleted = $conn->exec("DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at < NOW()");
echo "Deleted {$deleted} expired notifications\n\n";

// 5. Thống kê
$total = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC);
echo "=== SUMMARY ===\n";
echo "Total active notifications: {$total['cnt']}\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
?>
