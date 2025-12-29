<?php
/**
 * Notification Helpers
 * Các hàm hỗ trợ tự động tạo thông báo
 */

/**
 * Tự động tạo thông báo khi có sản phẩm mới
 */
function auto_notify_new_product($product_id, $product_name, $category_name) {
    global $conn;
    
    error_log("[auto_notify_new_product] Called with: product_id=$product_id, name=$product_name, category=$category_name");
    
    // Kiểm tra connection tồn tại
    if (!isset($conn)) {
        error_log("[auto_notify_new_product] ERROR: No database connection");
        return false;
    }
    
    error_log("[auto_notify_new_product] Connection type: " . get_class($conn));
    
    try {
        $title = "Sản phẩm mới: {$product_name}";
        $message = "Chúng tôi vừa cập nhật sản phẩm mới thuộc danh mục {$category_name}. Xem ngay!";
        $link = "chitiet_san_pham.php?id={$product_id}";
        $type = 'new_product';
        
        error_log("[auto_notify_new_product] Preparing SQL...");
        
        // Hỗ trợ cả PDO và mysqli
        if ($conn instanceof PDO) {
            error_log("[auto_notify_new_product] Using PDO");
            $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 1)");
            $stmt->execute([$type, $title, $message, $link]);
            error_log("[auto_notify_new_product] PDO insert successful");
        } elseif ($conn instanceof mysqli) {
            error_log("[auto_notify_new_product] Using MySQLi");
            $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 1)");
            if ($stmt === false) {
                error_log("[auto_notify_new_product] ERROR: Prepare failed - " . $conn->error);
                return false;
            }
            $stmt->bind_param("ssss", $type, $title, $message, $link);
            $result = $stmt->execute();
            if ($result === false) {
                error_log("[auto_notify_new_product] ERROR: Execute failed - " . $stmt->error);
                $stmt->close();
                return false;
            }
            $insert_id = $conn->insert_id;
            $stmt->close();
            error_log("[auto_notify_new_product] MySQLi insert successful, ID: $insert_id");
            return $result;
        }
        
        error_log("[auto_notify_new_product] Success!");
        return true;
    } catch (Exception $e) {
        error_log("[auto_notify_new_product] EXCEPTION: " . $e->getMessage());
        error_log("[auto_notify_new_product] Stack: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Tự động tạo thông báo khi admin trả lời đánh giá
 */
function auto_notify_reply_review($product_id, $product_name, $user_id) {
    global $conn;
    
    if (!isset($conn)) {
        error_log("Auto notify reply review: No database connection");
        return false;
    }
    
    try {
        $title = "Phản hồi đánh giá sản phẩm";
        $message = "Đánh giá của bạn về sản phẩm '{$product_name}' đã được phản hồi. Xem ngay!";
        $link = "chitiet_san_pham.php?id={$product_id}";
        $type = 'review_reply';
        
        // Hỗ trợ cả PDO và mysqli
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 1)");
            $stmt->execute([$type, $title, $message, $link]);
        } elseif ($conn instanceof mysqli) {
            $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 1)");
            if ($stmt === false) {
                error_log("Notification prepare failed: " . $conn->error);
                return false;
            }
            $stmt->bind_param("ssss", $type, $title, $message, $link);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Auto notify reply review error: " . $e->getMessage());
        return false;
    }
}

/**
 * Tự động tạo thông báo sale khi giảm giá > 20%
 */
function auto_notify_sale($product_id, $product_name, $old_price, $new_price) {
    global $conn;
    
    if (!isset($conn)) {
        error_log("Auto notify sale: No database connection");
        return false;
    }
    
    $discount_percent = round((($old_price - $new_price) / $old_price) * 100);
    
    if ($discount_percent >= 20) {
        try {
            $title = "Giảm giá {$discount_percent}%: {$product_name}";
            $message = "Giá từ " . number_format($old_price) . "đ xuống còn " . number_format($new_price) . "đ. Nhanh tay!";
            $link = "chitiet_san_pham.php?id={$product_id}";
            $type = 'sale';
            
            // Hỗ trợ cả PDO và mysqli
            if ($conn instanceof PDO) {
                $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY), 1)");
                $stmt->execute([$type, $title, $message, $link]);
            } elseif ($conn instanceof mysqli) {
                $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY), 1)");
                if ($stmt === false) {
                    error_log("Notification prepare failed: " . $conn->error);
                    return false;
                }
                $stmt->bind_param("ssss", $type, $title, $message, $link);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Auto notify sale error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Tự động thông báo sản phẩm sắp hết hàng
 */
function auto_notify_low_stock($product_id, $product_name, $stock) {
    global $conn;
    
    if ($stock <= 5 && $stock > 0) {
        try {
            $title = "Sắp hết: {$product_name}";
            $message = "Chỉ còn {$stock} sản phẩm. Đặt hàng ngay để không bỏ lỡ!";
            $link = "chitiet_san_pham.php?id={$product_id}";
            $type = 'announcement';
            
            // Kiểm tra đã thông báo trong 24h chưa
            $has_notification = false;
            if ($conn instanceof PDO) {
                $check = $conn->prepare("SELECT id FROM notifications WHERE title = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $check->execute([$title]);
                $has_notification = $check->rowCount() > 0;
            } elseif ($conn instanceof mysqli) {
                $check = $conn->prepare("SELECT id FROM notifications WHERE title = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $check->bind_param("s", $title);
                $check->execute();
                $result = $check->get_result();
                $has_notification = $result->num_rows > 0;
                $check->close();
            }
            
            if (!$has_notification) {
                if ($conn instanceof PDO) {
                    $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 DAY), 1)");
                    $stmt->execute([$type, $title, $message, $link]);
                } elseif ($conn instanceof mysqli) {
                    $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at, is_active) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 DAY), 1)");
                    $stmt->bind_param("ssss", $type, $title, $message, $link);
                    $stmt->execute();
                    $stmt->close();
                }
                return true;
            }
        } catch (Exception $e) {
            error_log("Auto notify low stock error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Tự động thông báo đơn hàng thành công
 */
function auto_notify_order_milestone() {
    global $conn;
    
    try {
        // Đếm tổng số đơn hàng thành công
        $result = $conn->query("SELECT COUNT(*) as total FROM don_hang WHERE trang_thai = 'Đã giao'");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $total = $row['total'];
        
        // Nếu đạt milestone (100, 500, 1000...)
        $milestones = [100, 500, 1000, 5000, 10000];
        
        foreach ($milestones as $milestone) {
            if ($total == $milestone) {
                $title = "🎉 Cảm ơn khách hàng!";
                $message = "Chúng tôi đã hoàn thành {$milestone} đơn hàng thành công! Cảm ơn sự ủng hộ của quý khách.";
                $type = 'announcement';
                
                $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, expires_at, is_active) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 1)");
                $stmt->execute([$type, $title, $message]);
                break;
            }
        }
    } catch (Exception $e) {
        error_log("Auto notify milestone error: " . $e->getMessage());
    }
}

/**
 * Tự động thông báo flash sale định kỳ
 */
function schedule_flash_sale() {
    global $conn;
    
    try {
        // Lấy ngẫu nhiên 5 sản phẩm để sale
        $products = $conn->query("SELECT id, ten_san_pham, gia FROM san_pham WHERE so_luong > 0 ORDER BY RAND() LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($products) > 0) {
            $product_names = implode(', ', array_column($products, 'ten_san_pham'));
            
            $title = "⚡ Flash Sale - Giảm 30%";
            $message = "Flash sale trong 24h cho: {$product_names}. Mua ngay!";
            $link = "sale.php";
            $type = 'sale';
            
            $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))");
            $stmt->execute([$type, $title, $message, $link]);
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Schedule flash sale error: " . $e->getMessage());
        return false;
    }
}
?>
