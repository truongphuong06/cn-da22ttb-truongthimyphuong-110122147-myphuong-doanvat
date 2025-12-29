<?php
session_start();

// DB connect
$conn = mysqli_connect("localhost", "root", "");
if (!$conn) die("Kết nối MySQL thất bại: " . mysqli_connect_error());

// Google Client ID (thay bằng Client ID thật)
define('GOOGLE_CLIENT_ID', '123456789012-abcdefg12345.apps.googleusercontent.com');
$google_enabled = (GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID');

// If Google OAuth client config exists, create the auth URL so the register page
// can link directly to Google (skips intermediate login.php page).
$login_url = null;
if (file_exists(__DIR__ . '/google-login/config.php')) {
  require_once __DIR__ . '/google-login/config.php';
  if (isset($client)) {
    // Force account chooser so user can pick which Google account to use
    if (method_exists($client, 'setPrompt')) {
      $client->setPrompt('select_account');
    }
    $login_url = $client->createAuthUrl();
  }
}

// Ensure database and table
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS ban_hang");
mysqli_select_db($conn, "ban_hang");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS nguoi_dung (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoten VARCHAR(255) NULL,
    tendangnhap VARCHAR(100) NULL UNIQUE,
    email VARCHAR(255) NULL UNIQUE,
    mat_khau VARCHAR(255) NULL,
    quyen VARCHAR(20) NOT NULL DEFAULT 'user',
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// helper to get existing columns
$cols = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM `nguoi_dung`");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) $cols[] = $r['Field'];
    mysqli_free_result($res);
}
function find_col($cols, $candidates) {
    foreach ($candidates as $c) if (in_array($c, $cols)) return $c;
    return null;
}

$tendangnhap_col = find_col($cols, ['tendangnhap','username','user_name','ten_dang_nhap']) ?? 'tendangnhap';
$email_col = find_col($cols, ['email','e_mail','mail']) ?? 'email';
$mat_khau_col = find_col($cols, ['mat_khau','password','passwd','matkhau']) ?? 'mat_khau';
$hoten_col = find_col($cols, ['hoten','ho_ten','fullname','name']) ?? 'hoten';

// Registration handling
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ho_ten = mysqli_real_escape_string($conn, $_POST['hoten'] ?? '');
    $tendangnhap = mysqli_real_escape_string($conn, $_POST['tendangnhap'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';

    // basic validation
    if ($mat_khau === '' || $tendangnhap === '' || $email === '') {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } else {
        // check existing
        $check_sql = "SELECT id FROM nguoi_dung WHERE `{$tendangnhap_col}` = ? OR `{$email_col}` = ? LIMIT 1";
        $stmt = $conn->prepare($check_sql);
        if ($stmt) {
            $stmt->bind_param('ss', $tendangnhap, $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "Tên đăng nhập hoặc email đã tồn tại!";
            } else {
                $hashed = password_hash($mat_khau, PASSWORD_DEFAULT);
                $insert_cols = ["`{$hoten_col}`","`{$tendangnhap_col}`","`{$email_col}`","`{$mat_khau_col}`"];
                $placeholders = implode(',', array_fill(0, count($insert_cols), '?'));
                $colList = implode(',', $insert_cols);
                $insert_sql = "INSERT INTO nguoi_dung ({$colList}) VALUES ({$placeholders})";
                $stmt2 = $conn->prepare($insert_sql);
                if ($stmt2) {
                    $stmt2->bind_param('ssss', $ho_ten, $tendangnhap, $email, $hashed);
                    if ($stmt2->execute()) {
                        header('Location: dangnhap.php?registered=1');
                        exit();
                    } else {
                        $error = "Lỗi lưu dữ liệu: " . $stmt2->error;
                    }
                    $stmt2->close();
                } else {
                    $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            $error = "Lỗi kiểm tra người dùng: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng Ký - My Shop</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.container {
  background: white;
  padding: 45px 40px;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 100%;
  max-width: 500px;
  animation: slideUp 0.5s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.logo-section {
  text-align: center;
  margin-bottom: 35px;
}

.logo-icon {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.logo-icon i {
  font-size: 32px;
  color: white;
}

.title {
  color: #1a202c;
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 8px;
}

.subtitle {
  color: #718096;
  font-size: 15px;
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 25px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.alert-danger {
  background: #fee;
  color: #c53030;
  border-left: 4px solid #fc8181;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  color: #4a5568;
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 8px;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #a0aec0;
  font-size: 16px;
}

.form-control {
  width: 100%;
  padding: 14px 16px 14px 45px;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 15px;
  transition: all 0.3s ease;
  outline: none;
  background: #f7fafc;
}

.form-control:focus {
  border-color: #667eea;
  background: white;
  box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.btn {
  width: 100%;
  padding: 16px;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
}

.divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 25px 0;
  color: #a0aec0;
  font-size: 14px;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #e2e8f0;
}

.divider span {
  padding: 0 15px;
}

.google-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  padding: 14px;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  font-size: 15px;
  font-weight: 600;
  color: #4a5568;
  transition: all 0.3s ease;
  text-decoration: none;
}

.google-btn:hover {
  border-color: #667eea;
  background: #f7fafc;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.google-icon {
  width: 20px;
  height: 20px;
}

.text-center {
  text-align: center;
}

.mt-2 {
  margin-top: 20px;
}

.text-center a {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s ease;
  font-size: 14px;
}

.text-center a:hover {
  color: #764ba2;
}

@media (max-width: 480px) {
  .container {
    padding: 35px 25px;
  }
  .title { font-size: 24px; }
}
</style>
</head>
<body>
<div class="container">
  <div class="logo-section">
    <div class="logo-icon">
      <i class="fas fa-user-plus"></i>
    </div>
    <div class="title">Tạo tài khoản mới</div>
    <p class="subtitle">Đăng ký để bắt đầu mua sắm</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle"></i>
      <span><?php echo htmlspecialchars($error ?? '', ENT_QUOTES); ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" onsubmit="return validateForm()">
    <div class="form-group">
      <label>Họ và tên</label>
      <div class="input-wrapper">
        <i class="fas fa-user input-icon"></i>
        <input name="hoten" class="form-control" placeholder="Nguyễn Văn A" required>
      </div>
    </div>
    <div class="form-group">
      <label>Tên đăng nhập</label>
      <div class="input-wrapper">
        <i class="fas fa-at input-icon"></i>
        <input name="tendangnhap" class="form-control" placeholder="username" minlength="3" required>
      </div>
    </div>
    <div class="form-group">
      <label>Email</label>
      <div class="input-wrapper">
        <i class="fas fa-envelope input-icon"></i>
        <input name="email" type="email" class="form-control" placeholder="email@example.com" required>
      </div>
    </div>
    <div class="form-group">
      <label>Mật khẩu</label>
      <div class="input-wrapper">
        <i class="fas fa-lock input-icon"></i>
        <input name="mat_khau" id="mat_khau" type="password" class="form-control" placeholder="Tối thiểu 6 ký tự" minlength="6" required>
        <i class="fas fa-eye toggle-password" onclick="togglePassword('mat_khau')" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0aec0;"></i>
      </div>
    </div>
    <div class="form-group">
      <label>Xác nhận mật khẩu</label>
      <div class="input-wrapper">
        <i class="fas fa-lock input-icon"></i>
        <input id="xac_nhan_mat_khau" type="password" class="form-control" placeholder="Nhập lại mật khẩu" required>
        <i class="fas fa-eye toggle-password" onclick="togglePassword('xac_nhan_mat_khau')" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0aec0;"></i>
      </div>
    </div>
    <button class="btn btn-primary" type="submit">
      <i class="fas fa-user-plus"></i> Đăng ký
    </button>
  </form>

  <?php if ($login_url): ?>
    <div class="divider"><span>hoặc</span></div>
    <a href="<?php echo htmlspecialchars($login_url ?? 'google-login/login.php', ENT_QUOTES); ?>" class="google-btn">
      <svg class="google-icon" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
      </svg>
      Tiếp tục với Google
    </a>
  <?php endif; ?>

  <p class="text-center mt-2">Đã có tài khoản? <a href="dangnhap.php">Đăng nhập</a></p>
</div>

<script>
function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  const icon = input.nextElementSibling;
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
</script>

<?php if ($google_enabled): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
const GOOGLE_CLIENT_ID = '<?php echo addslashes(GOOGLE_CLIENT_ID); ?>';
function handleCredentialResponse(response){
  fetch('google_auth.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({id_token: response.credential})
  }).then(r=>r.json()).then(data=>{
    if (data.success) window.location.href = data.redirect || 'trangchu.php';
    else alert(data.error || 'Google auth thất bại');
  }).catch(()=>alert('Lỗi mạng'));
}
window.onload = function(){
  google.accounts.id.initialize({client_id: GOOGLE_CLIENT_ID, callback: handleCredentialResponse});
  google.accounts.id.renderButton(document.getElementById('g_signin_btn'), { theme: 'outline', size: 'large' });
};
function validateForm(){
  var p = document.getElementById('mat_khau').value;
  var c = document.getElementById('xac_nhan_mat_khau').value;
  if (p !== c) { alert('Mật khẩu xác nhận không khớp'); return false; }
  return true;
}
</script>
<?php else: ?>
<script>
function validateForm(){
  var p = document.getElementById('mat_khau').value;
  var c = document.getElementById('xac_nhan_mat_khau').value;
  if (p !== c) { alert('Mật khẩu xác nhận không khớp'); return false; }
  return true;
}
</script>
<?php endif; ?>

</body>
</html>
<!-- Removed duplicate floating Google button (we now show a red full-width button under the form) -->
