<?php
/**
 * Login Page
 * Trang đăng nhập
 */

// Load database connection
require_once __DIR__ . '/connect.php';

// Kết nối database mysqli (để tương thích với code cũ)
$mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli_conn->connect_error) {
    die("Kết nối MySQL thất bại: " . $mysqli_conn->connect_error);
}
$mysqli_conn->set_charset("utf8mb4");
$conn = $mysqli_conn; // Override for this file

// If Google OAuth client config exists, create the auth URL so the login page
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

// Tạo bảng nguoi_dung nếu chưa tồn tại (migration)
$sql = "CREATE TABLE IF NOT EXISTS nguoi_dung (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ho_ten VARCHAR(100) NOT NULL,
    tendangnhap VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    mat_khau VARCHAR(255) NOT NULL,
    quyen VARCHAR(20) NOT NULL DEFAULT 'user',
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (!mysqli_query($conn, $sql)) {
    die("Lỗi tạo bảng nguoi_dung: " . mysqli_error($conn));
}

// Xử lý đăng nhập
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    // Lấy danh sách cột bảng nguoi_dung để phát hiện tên cột thực tế
    $cols = [];
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `nguoi_dung`");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $cols[] = $r['Field'];
        mysqli_free_result($res);
    }

    // Helper tìm cột
    $find_col = function(array $candidates) use ($cols) {
        foreach ($candidates as $c) {
            if (in_array($c, $cols)) return $c;
        }
        return null;
    };

    // Các ứng viên tên cột cho "username" và "password"
    $user_col = $find_col(['tendangnhap','username','user_name','ten_dang_nhap','email']);
    $pass_col = $find_col(['mat_khau','password','passwd','matkhau']);
    $khoa_col = $find_col(['khoa','is_locked','locked']);

    if (!$user_col) {
        $error = "Không tìm thấy cột tên đăng nhập trong cơ sở dữ liệu. Liên hệ quản trị.";
    } else {
        $sql = "SELECT * FROM `nguoi_dung` WHERE `{$user_col}` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = "Lỗi truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("s", $usernameInput);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
              // Nếu có cột khóa tài khoản, kiểm tra trước khi xác thực mật khẩu
              if (
                ($khoa_col && !empty($user_data[$khoa_col])) ||
                (isset($user_data['trang_thai']) && $user_data['trang_thai'] == 0)
              ) {
                $error = "Tài khoản này đã bị khóa. Vui lòng liên hệ quản trị viên.";
              } else {
                // Nếu tìm được cột mật khẩu, so sánh hợp lý
                if ($pass_col && isset($user_data[$pass_col])) {
                    $stored = $user_data[$pass_col];
                    // nếu được hash bằng password_hash, dùng password_verify; ngược lại so sánh trực tiếp
                    if (password_verify($passwordInput, $stored) || $passwordInput === $stored) {
                        // XÓA HẾT SESSION CŨ TRƯỚC
                        session_unset();
                        session_destroy();
                        session_start();
                        
                        // Thiết lập session mới
                        $_SESSION['username'] = $usernameInput;
                        $_SESSION['user_id'] = $user_data['id'] ?? null;
                        
                        // map cột quyền (ưu tiên quyen trước vai_tro)
                        $role_col = $find_col(['quyen','vai_tro','role']);
                        $user_role = $role_col ? ($user_data[$role_col] ?? null) : null;
                        $_SESSION['role'] = $user_role;
                        
                        // Phân quyền tự động: admin -> trang quản trị, user -> trang chủ
                        if ($user_role === 'admin') {
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_username'] = $usernameInput;
                            $_SESSION['admin_id'] = $user_data['id'] ?? null;
                            header("Location: qtvtrangchu.php");
                        } else {
                            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'trangchu.php';
                            unset($_SESSION['redirect_after_login']);
                            header("Location: " . $redirect);
                        }
                        exit();
                    } else {
                        $error = "Tên đăng nhập hoặc mật khẩu không đúng";
                    }
              } else {
                // Không có cột mật khẩu: từ chối đăng nhập an toàn
                $error = "Cấu hình mật khẩu của hệ thống chưa chính xác. Liên hệ quản trị.";
              }
              }
            } else {
                $error = "Tên đăng nhập hoặc mật khẩu không đúng";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập - My Shop</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
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

  .login-container {
    background: white;
    padding: 50px 45px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 440px;
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

  h1 {
    color: #1a202c;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
  }

  .subtitle {
    color: #718096;
    font-size: 15px;
  }

  .error-message {
    background: #fee;
    color: #c53030;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 14px;
    border-left: 4px solid #fc8181;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  form {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .form-group {
    position: relative;
  }

  label {
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

  input[type="text"],
  input[type="password"] {
    width: 100%;
    padding: 14px 16px 14px 45px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    outline: none;
    background: #f7fafc;
  }

  input[type="text"]:focus,
  input[type="password"]:focus {
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
  }

  .submit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    margin-top: 10px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
  }

  .submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
  }

  .submit-btn:active {
    transform: translateY(0);
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

  .signup-link {
    text-align: center;
    margin-top: 25px;
    color: #718096;
    font-size: 14px;
  }

  .signup-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
  }

  .signup-link a:hover {
    color: #764ba2;
  }

  @media (max-width: 480px) {
    .login-container {
      padding: 40px 30px;
    }
    h1 { font-size: 24px; }
  }
</style>
<body>
  <div class="login-container">
    <div class="logo-section">
      <div class="logo-icon">
        <i class="fas fa-shopping-bag"></i>
      </div>
      <h1>Chào mừng trở lại!</h1>
      <p class="subtitle">Đăng nhập để tiếp tục mua sắm</p>
    </div>

    <?php if(isset($error)) { ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
      </div>
    <?php } ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <div class="form-group">
        <label for="username">Tên đăng nhập</label>
        <div class="input-wrapper">
          <i class="fas fa-user input-icon"></i>
          <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập" required>
        </div>
      </div>

      <div class="form-group">
        <label for="password">Mật khẩu</label>
        <div class="input-wrapper">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
          <i class="fas fa-eye toggle-password" onclick="togglePassword('password')" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0aec0;"></i>
        </div>
      </div>

      <button type="submit" class="submit-btn">
        <i class="fas fa-sign-in-alt"></i> Đăng nhập
      </button>
    </form>

    <?php if ($login_url): ?>
      <div class="divider"><span>hoặc</span></div>
      <a href="<?php echo htmlspecialchars($login_url, ENT_QUOTES); ?>" class="google-btn">
        <svg class="google-icon" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Tiếp tục với Google
      </a>
    <?php endif; ?>

    <div class="signup-link">
      Chưa có tài khoản? <a href="dangky.php">Đăng ký ngay</a>
    </div>
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
</body>
</html>
