<?php
/**
 * Tạo tài khoản Admin mới
 * Chạy file này 1 lần rồi XÓA đi
 */

require_once __DIR__ . '/connect.php';

$admin_username = 'admin123';
$admin_password = '123456';
$admin_email = 'admin123@shop.com';
$admin_name = 'Quản Trị Viên';

// Hash password
$hashed_password = password_hash($admin_password, PASSWORD_BCRYPT);

// Dùng mysqli để tương thích với cấu trúc database
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli->set_charset("utf8mb4");

// Kiểm tra cấu trúc bảng
echo "<h3>📋 Cấu trúc bảng nguoi_dung:</h3>";
$cols_result = $mysqli->query("SHOW COLUMNS FROM nguoi_dung");
$columns = [];
echo "<ul>";
while ($col = $cols_result->fetch_assoc()) {
    $columns[] = $col['Field'];
    echo "<li>{$col['Field']} - {$col['Type']}</li>";
}
echo "</ul>";

// Tìm tên cột đúng
$username_col = in_array('ten_dang_nhap', $columns) ? 'ten_dang_nhap' : (in_array('tendangnhap', $columns) ? 'tendangnhap' : null);
$fullname_col = in_array('ho_ten', $columns) ? 'ho_ten' : (in_array('hoten', $columns) ? 'hoten' : null);

echo "<p>Cột username: <strong>$username_col</strong></p>";
echo "<p>Cột họ tên: <strong>$fullname_col</strong></p>";

if (!$username_col) {
    die("<h2 style='color:red'>❌ Không tìm thấy cột username trong bảng!</h2>");
}

// Kiểm tra đã tồn tại chưa
$check = $mysqli->prepare("SELECT id FROM nguoi_dung WHERE `$username_col` = ? OR email = ?");
$check->bind_param("ss", $admin_username, $admin_email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "<h2 style='color:orange'>⚠️ Tài khoản đã tồn tại!</h2>";
} else {
    // Tạo admin mới
    $sql = "INSERT INTO nguoi_dung (`$fullname_col`, `$username_col`, email, mat_khau, quyen) VALUES (?, ?, ?, ?, 'admin')";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssss", $admin_name, $admin_username, $admin_email, $hashed_password);
    
    if ($stmt->execute()) {
        echo "<h2 style='color:green'>✅ Tạo tài khoản Admin thành công!</h2>";
    } else {
        echo "<h2 style='color:red'>❌ Lỗi: " . $stmt->error . "</h2>";
    }
}

echo "<hr>";
echo "<h3>🔑 Thông tin đăng nhập:</h3>";
echo "<p>Username: <strong>$admin_username</strong></p>";
echo "<p>Password: <strong>$admin_password</strong></p>";
echo "<p>Email: <strong>$admin_email</strong></p>";

echo "<br><a href='dangnhap.php' style='padding:10px 20px; background:#667eea; color:white; text-decoration:none; border-radius:5px;'>👉 Đăng nhập ngay</a>";
echo "<br><br><p style='color:red'>⚠️ <strong>Nhớ xóa file create_admin.php sau khi dùng xong!</strong></p>";

$mysqli->close();
?>
