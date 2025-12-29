<?php
require_once 'connect.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $stmt = $conn->prepare("SELECT id, ma_don_hang, nguoi_dung_id, email, trang_thai FROM don_hang ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $orders = $stmt->fetchAll();
    echo "ID | Mã đơn | UserID | Email | Trạng thái\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($orders as $o) {
        echo $o['id'] . " | " . $o['ma_don_hang'] . " | " . $o['nguoi_dung_id'] . " | " . $o['email'] . " | " . $o['trang_thai'] . "\n";
    }
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}
