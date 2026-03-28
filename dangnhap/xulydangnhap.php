<?php
// Start session with proper configuration
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

session_start();
// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Not POST request, redirecting");
    header('Location: dangnhap.php');
    exit();
}

// Kết nối đến SQLite database
try {
    require_once __DIR__ . '/../db/db_config.php';
    $db = $pdo;
    error_log("Database connected successfully via db_config");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Kết nối đến cơ sở dữ liệu thất bại: " . $e->getMessage());
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

error_log("Email: " . $email);
error_log("Password length: " . strlen($password));

// Kiểm tra input không rỗng
if (empty($email) || empty($password)) {
    error_log("Empty email or password");
    header('Location: dangnhap.php?error=empty');
    exit();
}

// Kiểm tra định dạng email @vnu.edu.vn
if (!preg_match('/^[\w\.-]+@vnu\.edu\.vn$/', $email)) {
    error_log("Invalid email format: " . $email);
    header('Location: dangnhap.php?error=invalid_email');
    exit();
}

try {
    // Tìm user theo email
    $stmt = $db->prepare("SELECT id, fullname, email, password, role, status, avatar FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        error_log("User not found: $email");
        header("Location: dangnhap.php?error=user_not_found");
        exit();
    }
    
    // Debug password info
    error_log("User found: ID=" . $user['id'] . ", Role=" . $user['role']);
    error_log("Stored hash: " . substr($user['password'], 0, 30) . "...");
    error_log("Hash algorithm: " . password_get_info($user['password'])['algoName']);
    
    // Kiểm tra trạng thái tài khoản
    if ($user['status'] !== 'active') {
        error_log("Account not active: " . $user['status']);
        header("Location: dangnhap.php?error=account_inactive");
        exit();
    }
    
    // Verify password với password_verify
    $password_valid = password_verify($password, $user['password']);
    error_log("Password verify result: " . ($password_valid ? 'TRUE' : 'FALSE'));
    
    if (!$password_valid) {
        error_log("Wrong password for: $email");
        
        // Log failed login attempt
        try {
            $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'login_failed', 'Wrong password', ?, datetime('now'))");
            $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        header("Location: dangnhap.php?error=wrong_password");
        exit();
    }
    
    // Đăng nhập thành công - Tạo session với keys nhất quán
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user['fullname'];  // Key chính
    $_SESSION['fullname'] = $user['fullname'];  // Alias
    $_SESSION['name'] = $user['fullname'];  // Alias
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['avatar'] = $user['avatar'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    error_log("Login successful! User ID: " . $user['id'] . ", Role: " . $user['role']);
    error_log("Session user: " . $_SESSION['user']);
    error_log("Session fullname: " . $_SESSION['fullname']);
    
    // Cập nhật updated_at
    try {
        $updateStmt = $db->prepare("UPDATE users SET updated_at = datetime('now') WHERE id = ?");
        $updateStmt->execute([$user['id']]);
    } catch (PDOException $e) {
        error_log("Failed to update timestamp: " . $e->getMessage());
    }
    
    // Log successful login
    try {
        $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'login_success', 'User logged in successfully', ?, datetime('now'))");
        $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // Redirect based on role
    switch ($user['role']) {
        case 'admin':
            error_log("Redirecting to admin dashboard");
            header("Location: ../admin/index.php");
            break;
            
        case 'teacher':
            error_log("Redirecting to teacher dashboard");
            header("Location: ../giaovien/trangchu_giaovien.php");
            break;
            
        case 'student':
        default:
            error_log("Redirecting to student dashboard");
            header("Location: ../saudn/trangchusaudn.php");
            break;
    }
    exit();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: dangnhap.php?error=system");
    exit();
} catch (Exception $e) {
    error_log("System error: " . $e->getMessage());
    header("Location: dangnhap.php?error=system");
    exit();
}
?>
