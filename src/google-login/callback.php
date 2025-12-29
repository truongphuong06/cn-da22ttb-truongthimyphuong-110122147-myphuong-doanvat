<?php
session_start();
require 'config.php';

// Kết nối DB
$conn = mysqli_connect("localhost", "root", "", "ban_hang");
if (!$conn) die("Lỗi kết nối DB: " . mysqli_connect_error());

// helper to find actual column names in `nguoi_dung`
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

$name_col = find_col($cols, ['hoten','ho_ten','fullname','name']);
$username_col = find_col($cols, ['ten_dang_nhap','tendangnhap','tendang_nhap','username','user_name']);
$email_col = find_col($cols, ['email','e_mail','mail']);
$pass_col = find_col($cols, ['mat_khau','password','passwd','matkhau']);
$id_col = find_col($cols, ['id','user_id']);
$role_col = find_col($cols, ['quyen','role','vai_tro']);

if (isset($_GET['code'])) {
    // If Google returned an error (e.g., access_denied), show/log it for debugging
    if (isset($_GET['error'])) {
        $err = $_GET['error'];
        $desc = isset($_GET['error_description']) ? $_GET['error_description'] : '';
        error_log('Google OAuth error: ' . $err . ' desc: ' . $desc);
        echo "Google OAuth error: " . htmlspecialchars($err) . ". " . htmlspecialchars($desc);
        exit;
    }

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (!isset($token["error"])) {
        $client->setAccessToken($token['access_token']);

        $google_service = new Google_Service_Oauth2($client);
        $data = $google_service->userinfo->get();

        // Lấy dữ liệu Google
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';

        // Kiểm tra email đã tồn tại trong DB chưa (sử dụng cột thực tế nếu có)
        if ($email_col) {
            $sql = "SELECT * FROM nguoi_dung WHERE `{$email_col}` = ? LIMIT 1";
        } else {
            $sql = "SELECT * FROM nguoi_dung WHERE email = ? LIMIT 1";
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $user = null;
        }

        if ($user) {
            // Email đã tồn tại → đăng nhập luôn (map các cột nếu có)
            if ($username_col && isset($user[$username_col])) $_SESSION['username'] = $user[$username_col];
            elseif (isset($user['tendangnhap'])) $_SESSION['username'] = $user['tendangnhap'];
            else $_SESSION['username'] = '';

            if ($id_col && isset($user[$id_col])) $_SESSION['user_id'] = $user[$id_col];
            elseif (isset($user['id'])) $_SESSION['user_id'] = $user['id'];

            if ($role_col && isset($user[$role_col])) $_SESSION['role'] = $user[$role_col];
            elseif (isset($user['quyen'])) $_SESSION['role'] = $user['quyen'];

        } else {
            // Nếu email chưa tồn tại → tạo tài khoản mới (chọn cột phù hợp)
            // Tạo username an toàn, ưu tiên phần local-part của email, hoặc tên, hoặc 'user'
            $base = '';
            if (!empty($email)) {
                $base = explode('@', $email)[0];
            } elseif (!empty($name)) {
                $base = preg_replace('/\s+/', '', mb_strtolower($name));
            } else {
                $base = 'user';
            }
            // keep only safe characters
            $base = preg_replace('/[^a-z0-9._-]/i', '', $base);
            if ($base === '') $base = 'user';

            // ensure uniqueness: only check DB if a username column actually exists
            $randomUsername = '';
            if ($username_col) {
                $candidate = $base;
                $suffix = 0;
                $uname_col = $username_col;
                $checkSql = "SELECT COUNT(*) AS c FROM nguoi_dung WHERE `{$uname_col}` = ?";
                $checkStmt = $conn->prepare($checkSql);
                while (true) {
                    if ($suffix > 0) $candidate = $base . $suffix;
                    if ($checkStmt) {
                        $checkStmt->bind_param('s', $candidate);
                        $checkStmt->execute();
                        $res = $checkStmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $count = $row ? (int)$row['c'] : 0;
                        if ($count === 0) {
                            $randomUsername = $candidate;
                            break; // unique
                        }
                    } else {
                        // If we cannot prepare, fallback to random username
                        $randomUsername = $base . rand(100,999);
                        break;
                    }
                    $suffix++;
                    if ($suffix > 999) { $randomUsername = $base . uniqid(); break; }
                }
            } else {
                // No username column in DB table; generate a local username but don't attempt DB checks
                $randomUsername = $base . rand(100,999);
            }
            $randomPass = password_hash("google_login", PASSWORD_DEFAULT);

            $insertCols = [];
            $insertVals = [];
            if ($name_col) { $insertCols[] = "`$name_col`"; $insertVals[] = $name; }
            if ($username_col) { $insertCols[] = "`$username_col`"; $insertVals[] = $randomUsername; }
            if ($email_col) { $insertCols[] = "`$email_col`"; $insertVals[] = $email; }
            if ($pass_col) { $insertCols[] = "`$pass_col`"; $insertVals[] = $randomPass; }

            // Fallback: if no columns detected, try legacy names
            if (empty($insertCols)) {
                $insertCols = ['`hoten`','`tendangnhap`','`email`','`mat_khau`'];
                $insertVals = [$name, $randomUsername, $email, $randomPass];
            }

            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $colList = implode(',', $insertCols);
            $sql = "INSERT INTO nguoi_dung ({$colList}) VALUES ({$placeholders})";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types = str_repeat('s', count($insertVals));
                // bind_param requires references — build properly
                $bind_names = [];
                $bind_names[] = $types;
                for ($i=0; $i<count($insertVals); $i++) {
                    $bind_name = 'bind' . $i;
                    $$bind_name = $insertVals[$i];
                    $bind_names[] = &$$bind_name;
                }
                call_user_func_array(array($stmt, 'bind_param'), $bind_names);
                if (!$stmt->execute()) {
                    // Log detailed DB error for debugging
                    error_log('Insert user failed: ' . $stmt->error . ' SQL: ' . $sql);
                } else {
                    $newID = $stmt->insert_id;

                    $_SESSION['username'] = $randomUsername;
                    $_SESSION['user_id'] = $newID;
                    $_SESSION['role'] = "user";
                }
            } else {
                // If insert fails to prepare, log and continue
                error_log('Prepare insert user failed: ' . $conn->error . ' SQL: ' . $sql);
            }
        }

        // Chuyển hướng về trang chủ
        header("Location: ../trangchu.php");
        exit;
    }
}

echo "Đăng nhập Google thất bại!";
?>
