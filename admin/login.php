<?php

// Include session configuration (đã có session_start bên trong)
require_once __DIR__ . '/config/session.php';

// Nếu đã đăng nhập admin, chuyển đến dashboard
if (isset($_SESSION['user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: index.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/../db/db_config.php';
        
        // Kiểm tra kết nối database
        if (!$pdo) {
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra cấu hình.");
        }
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error_message = "Vui lòng nhập đầy đủ thông tin đăng nhập.";
        } else {
            // Kiểm tra xem email có tồn tại không - SỬA: fullname (không có gạch dưới)
            $stmt = $pdo->prepare("SELECT id, fullname, email, password, role, status FROM users WHERE email = ? AND role = 'admin'");
            
            if (!$stmt) {
                throw new Exception("Lỗi chuẩn bị câu lệnh SQL: " . implode(" ", $pdo->errorInfo()));
            }
            
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // DEBUG: Log thông tin mật khẩu
                error_log("=== PASSWORD DEBUG ===");
                error_log("Input password: " . $password);
                error_log("Stored hash: " . $user['password']);
                error_log("Verify result: " . (password_verify($password, $user['password']) ? 'TRUE' : 'FALSE'));
                error_log("Hash algorithm: " . password_get_info($user['password'])['algoName']);
                error_log("======================");
                
                // Kiểm tra trạng thái tài khoản
                if ($user['status'] !== 'active') {
                    $error_message = "Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên cấp cao.";
                    
                    // Ghi log đăng nhập thất bại
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'admin_login_failed', ?, ?, datetime('now'))");
                        if ($logStmt) {
                            $logStmt->execute([$user['id'], "Account disabled or suspended: $email", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to log activity: " . $e->getMessage());
                    }
                } elseif (password_verify($password, $user['password'])) {
                    // Đăng nhập thành công - SỬA: fullname (không có gạch dưới)
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user'] = $user['fullname'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Cập nhật updated_at (không cập nhật last_login)
                    try {
                        $updateStmt = $pdo->prepare("UPDATE users SET updated_at = datetime('now') WHERE id = ?");
                        if (!$updateStmt->execute([$user['id']])) {
                            error_log("Failed to update timestamp: " . implode(" ", $updateStmt->errorInfo()));
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to update timestamp: " . $e->getMessage());
                    }
                    
                    // Ghi log đăng nhập thành công
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'admin_login', 'Admin login successful', ?, datetime('now'))");
                        if ($logStmt) {
                            $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to log activity: " . $e->getMessage());
                    }
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $error_message = "Mật khẩu không chính xác.";
                    
                    // Thêm thông tin debug vào error message (CHỈ KHI DEVELOPMENT)
                    $error_message .= " [Debug: Hash=" . substr($user['password'], 0, 20) . "...]";
                    
                    // Ghi log đăng nhập thất bại
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'admin_login_failed', 'Incorrect password', ?, datetime('now'))");
                        if ($logStmt) {
                            $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to log activity: " . $e->getMessage());
                    }
                }
            } else {
                $error_message = "Email không tồn tại hoặc không có quyền truy cập Admin.";
                
                // Ghi log đăng nhập thất bại
                try {
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (NULL, 'admin_login_failed', ?, ?, datetime('now'))");
                    if ($logStmt) {
                        $logStmt->execute(["Admin not found: $email", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    }
                } catch (PDOException $e) {
                    error_log("Failed to log activity: " . $e->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
        error_log("PDO Error in admin login: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Error in admin login: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-500: #4CAF50;
            --primary-600: #43A047;
            --primary-700: #388E3C;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --danger: #F44336;
            --success: #4CAF50;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Lexend', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
        }
        
        .login-card {
            background: var(--bg-primary);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-700) 100%);
            padding: 40px 32px;
            text-align: center;
            color: white;
        }
        
        .login-header .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .login-header .logo i {
            font-size: 40px;
        }
        
        .login-header .logo span {
            font-size: 28px;
            font-weight: 700;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 16px;
        }
        
        .login-body {
            padding: 40px 32px;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
        }
        
        .alert-success {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 4px;
        }
        
        .password-toggle:hover {
            color: var(--text-primary);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .remember-me input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-500);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-500) 0%, var(--primary-700) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            padding: 24px 32px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }
        
        .login-footer a {
            color: var(--primary-600);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .security-notice {
            margin-top: 24px;
            padding: 16px;
            background: #fff3e0;
            border: 1px solid #ffe0b2;
            border-radius: 10px;
            font-size: 13px;
            color: #e65100;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .security-notice i {
            font-size: 18px;
            margin-top: 2px;
        }
        
        @media (max-width: 480px) {
            .login-header {
                padding: 32px 24px;
            }
            
            .login-body {
                padding: 32px 24px;
            }
            
            .login-footer {
                padding: 20px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>EDUSERVICE</span>
                </div>
                <h1>Trang quản trị</h1>
                <p>Đăng nhập để quản lý hệ thống</p>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Khu vực Admin</span>
                </div>
            </div>
            
            <div class="login-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Email quản trị viên</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-input" placeholder="admin@vnu.edu.vn" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mật khẩu</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="form-input" placeholder="Nhập mật khẩu" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Ghi nhớ đăng nhập</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Đăng nhập Admin</span>
                    </button>
                </form>
                
                <div class="security-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Lưu ý bảo mật:</strong> Trang này chỉ dành cho quản trị viên hệ thống. 
                        Mọi hoạt động đăng nhập đều được ghi lại.
                    </div>
                </div>
            </div>
            
            <div class="login-footer">
                <a href="../dangnhap/dangnhap.php">
                    <i class="fas fa-arrow-left"></i>
                    <span>Về trang đăng nhập thường</span>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('password-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>