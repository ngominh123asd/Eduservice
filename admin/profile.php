<?php
// Include session configuration
require_once __DIR__ . '/config/session.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Hồ sơ cá nhân";
$current_page = "profile";

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $fullname = trim($_POST['fullname'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                if (empty($fullname) || empty($email)) {
                    $error_message = "Họ tên và email không được để trống!";
                } else {
                    try {
                        // Kiểm tra email đã tồn tại chưa
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user_id]);
                        if ($stmt->fetch()) {
                            $error_message = "Email này đã được sử dụng!";
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$fullname, $email, $phone, $user_id]);
                            $_SESSION['user'] = $fullname;
                            $success_message = "Cập nhật thông tin thành công!";
                        }
                    } catch (PDOException $e) {
                        $error_message = "Lỗi: " . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = "Vui lòng điền đầy đủ thông tin!";
                } elseif ($new_password !== $confirm_password) {
                    $error_message = "Mật khẩu xác nhận không khớp!";
                } elseif (strlen($new_password) < 6) {
                    $error_message = "Mật khẩu mới phải có ít nhất 6 ký tự!";
                } else {
                    try {
                        // Kiểm tra mật khẩu hiện tại
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if (!password_verify($current_password, $user['password'])) {
                            $error_message = "Mật khẩu hiện tại không đúng!";
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$hashed_password, $user_id]);
                            $success_message = "Đổi mật khẩu thành công!";
                        }
                    } catch (PDOException $e) {
                        $error_message = "Lỗi: " . $e->getMessage();
                    }
                }
                break;
                
            case 'update_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    $file_type = $_FILES['avatar']['type'];
                    $file_size = $_FILES['avatar']['size'];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $error_message = "Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WEBP)!";
                    } elseif ($file_size > $max_size) {
                        $error_message = "File ảnh không được vượt quá 5MB!";
                    } else {
                        $upload_dir = __DIR__ . '/../uploads/avatar/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                            try {
                                // Xóa avatar cũ nếu có
                                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                                $stmt->execute([$user_id]);
                                $old_avatar = $stmt->fetchColumn();
                                if ($old_avatar && file_exists($upload_dir . $old_avatar)) {
                                    unlink($upload_dir . $old_avatar);
                                }
                                
                                // Cập nhật avatar mới
                                $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$new_filename, $user_id]);
                                $success_message = "Cập nhật ảnh đại diện thành công!";
                            } catch (PDOException $e) {
                                $error_message = "Lỗi: " . $e->getMessage();
                            }
                        } else {
                            $error_message = "Không thể tải lên ảnh!";
                        }
                    }
                } else {
                    $error_message = "Vui lòng chọn file ảnh!";
                }
                break;
        }
    }
}

// Lấy thông tin user hiện tại
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Lỗi: " . $e->getMessage();
    $user_info = [];
}

// Lấy thống kê hoạt động
try {
    // Số lần đăng nhập (nếu có bảng logs)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND action = 'login'");
    $stmt->execute([$user_id]);
    $login_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $login_count = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Admin - EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin-common.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            margin-top: 24px;
        }
        
        @media (max-width: 1024px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        
        .profile-sidebar {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: 32px;
            text-align: center;
            box-shadow: var(--shadow-md);
            height: fit-content;
        }
        
        .avatar-wrapper {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 24px;
        }
        
        .avatar-wrapper img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-500);
        }
        
        .avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 56px;
            font-weight: 600;
            border: 4px solid var(--primary-500);
        }
        
        .avatar-edit-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 40px;
            height: 40px;
            background: var(--primary-500);
            border: 3px solid var(--bg-primary);
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-fast);
        }
        
        .avatar-edit-btn:hover {
            background: var(--primary-600);
            transform: scale(1.1);
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .profile-role {
            display: inline-block;
            padding: 6px 16px;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
            color: white;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item h4 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-500);
        }
        
        .stat-item p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .profile-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-header i {
            font-size: 20px;
            color: var(--primary-500);
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group:last-child {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 15px;
            font-family: inherit;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: var(--transition-fast);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .form-group input:disabled {
            background: var(--bg-tertiary);
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-700));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-outline:hover {
            border-color: var(--primary-500);
            color: var(--primary-500);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid var(--primary-500);
            color: var(--primary-700);
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .info-row {
            display: flex;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-row i {
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-500);
            margin-right: 16px;
        }
        
        .info-row .info-content {
            flex: 1;
        }
        
        .info-row .info-label {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .info-row .info-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 45px;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
        }
        
        .password-toggle-btn:hover {
            color: var(--primary-500);
        }
        
        /* Modal for avatar upload */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow: auto;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        
        .upload-zone:hover {
            border-color: var(--primary-500);
            background: rgba(76, 175, 80, 0.05);
        }
        
        .upload-zone i {
            font-size: 48px;
            color: var(--primary-500);
            margin-bottom: 16px;
        }
        
        .upload-zone p {
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .upload-zone span {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .upload-zone input[type="file"] {
            display: none;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius-md);
            margin: 16px auto;
            display: none;
        }
        
        .modal-actions {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-user-circle"></i> Hồ sơ cá nhân</h1>
                <p>Quản lý thông tin tài khoản của bạn</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="avatar-wrapper">
                        <?php if (!empty($user_info['avatar']) && file_exists(__DIR__ . '/../uploads/avatar/' . $user_info['avatar'])): ?>
                            <img src="../uploads/avatar/<?php echo htmlspecialchars($user_info['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($user_info['fullname'] ?? 'A', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <button class="avatar-edit-btn" onclick="openAvatarModal()">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    
                    <h2 class="profile-name"><?php echo htmlspecialchars($user_info['fullname'] ?? ''); ?></h2>
                    <span class="profile-role">
                        <i class="fas fa-shield-alt"></i> Quản trị viên
                    </span>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <h4><?php echo $login_count; ?></h4>
                            <p>Lần đăng nhập</p>
                        </div>
                        <div class="stat-item">
                            <h4><?php echo date('d/m', strtotime($user_info['created_at'] ?? 'now')); ?></h4>
                            <p>Ngày tham gia</p>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Main Content -->
                <div class="profile-main">
                    <!-- Account Info Card -->
                    <div class="profile-card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i>
                            <h3>Thông tin tài khoản</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <i class="fas fa-id-badge"></i>
                                <div class="info-content">
                                    <div class="info-label">ID tài khoản</div>
                                    <div class="info-value">#<?php echo $user_info['id'] ?? ''; ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-user-tag"></i>
                                <div class="info-content">
                                    <div class="info-label">Vai trò</div>
                                    <div class="info-value">Quản trị viên (Admin)</div>
                                </div>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-calendar-plus"></i>
                                <div class="info-content">
                                    <div class="info-label">Ngày tạo tài khoản</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($user_info['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-clock"></i>
                                <div class="info-content">
                                    <div class="info-label">Cập nhật lần cuối</div>
                                    <div class="info-value"><?php echo $user_info['updated_at'] ? date('d/m/Y H:i', strtotime($user_info['updated_at'])) : 'Chưa cập nhật'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Profile Card -->
                    <div class="profile-card">
                        <div class="card-header">
                            <i class="fas fa-edit"></i>
                            <h3>Chỉnh sửa thông tin</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="fullname">Họ và tên</label>
                                        <input type="text" id="fullname" name="fullname" 
                                               value="<?php echo htmlspecialchars($user_info['fullname'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Số điện thoại</label>
                                        <input type="tel" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>"
                                               placeholder="Nhập số điện thoại">
                                    </div>
                                    <div class="form-group">
                                        <label for="username">Tên đăng nhập</label>
                                        <input type="text" id="username" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" disabled>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu thay đổi
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password Card -->
                    <div class="profile-card">
                        <div class="card-header">
                            <i class="fas fa-lock"></i>
                            <h3>Đổi mật khẩu</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group">
                                    <label for="current_password">Mật khẩu hiện tại</label>
                                    <div class="password-toggle">
                                        <input type="password" id="current_password" name="current_password" required>
                                        <button type="button" class="password-toggle-btn" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_password">Mật khẩu mới</label>
                                        <div class="password-toggle">
                                            <input type="password" id="new_password" name="new_password" required minlength="6">
                                            <button type="button" class="password-toggle-btn" onclick="togglePassword('new_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Xác nhận mật khẩu mới</label>
                                        <div class="password-toggle">
                                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                            <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Đổi mật khẩu
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Avatar Upload Modal -->
    <div class="modal" id="avatar-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Cập nhật ảnh đại diện</h3>
                <button class="modal-close" onclick="closeAvatarModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_avatar">
                <div class="modal-body">
                    <div class="upload-zone" onclick="document.getElementById('avatar-input').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Nhấp để chọn ảnh hoặc kéo thả vào đây</p>
                        <span>Định dạng: JPEG, PNG, GIF, WEBP (Tối đa 5MB)</span>
                        <input type="file" id="avatar-input" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                    </div>
                    <img id="avatar-preview" class="preview-image" src="" alt="Preview">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAvatarModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Tải lên
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.password-toggle-btn i');
            
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
        
        // Avatar modal functions
        function openAvatarModal() {
            document.getElementById('avatar-modal').classList.add('active');
        }
        
        function closeAvatarModal() {
            document.getElementById('avatar-modal').classList.remove('active');
            document.getElementById('avatar-input').value = '';
            document.getElementById('avatar-preview').style.display = 'none';
        }
        
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatar-preview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('avatar-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAvatarModal();
            }
        });
        
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('admin-sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                document.querySelector('.main-wrapper').classList.toggle('expanded');
            });
        }
        
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-moon');
                icon.classList.toggle('fa-sun');
                
                // Save preference
                localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
            });
            
            // Load saved preference
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
                themeToggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
            }
        }
    </script>
</body>
</html>
