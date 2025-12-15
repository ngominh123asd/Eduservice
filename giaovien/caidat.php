<?php
session_start();

// Check authentication and role
if (
    !isset($_SESSION['user']) ||
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'teacher'
) {
    header("Location: ../dangnhap/dangnhap.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Verify teacher role from database
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: ../dangnhap/dangnhap.php");
        exit();
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $fullname = trim($_POST['fullname'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $gender = $_POST['gender'] ?? '';
                $birthday = $_POST['birthday'] ?? null;
                $bio = trim($_POST['bio'] ?? '');
                
                if (empty($fullname) || empty($email)) {
                    $error_message = "Họ tên và email không được để trống!";
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user_id]);
                        if ($stmt->fetch()) {
                            $error_message = "Email này đã được sử dụng!";
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, gender = ?, birthday = ?, bio = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$fullname, $email, $phone, $gender, $birthday ?: null, $bio, $user_id]);
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
                    $max_size = 5 * 1024 * 1024;
                    
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
                        $new_filename = 'teacher_' . $user_id . '_' . time() . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                            try {
                                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                                $stmt->execute([$user_id]);
                                $old_avatar = $stmt->fetchColumn();
                                if ($old_avatar && file_exists($upload_dir . $old_avatar)) {
                                    unlink($upload_dir . $old_avatar);
                                }
                                
                                $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$new_filename, $user_id]);
                                $_SESSION['avatar'] = $new_filename;
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
                
            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $submission_alerts = isset($_POST['submission_alerts']) ? 1 : 0;
                $deadline_reminders = isset($_POST['deadline_reminders']) ? 1 : 0;
                $class_updates = isset($_POST['class_updates']) ? 1 : 0;
                $student_progress = isset($_POST['student_progress']) ? 1 : 0;
                
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        email_notifications TINYINT(1) DEFAULT 1,
                        submission_alerts TINYINT(1) DEFAULT 1,
                        deadline_reminders TINYINT(1) DEFAULT 1,
                        class_updates TINYINT(1) DEFAULT 1,
                        student_progress TINYINT(1) DEFAULT 1,
                        theme VARCHAR(20) DEFAULT 'light',
                        language VARCHAR(10) DEFAULT 'vi',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_user (user_id)
                    )");
                    
                    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, email_notifications, submission_alerts, deadline_reminders, class_updates, student_progress) 
                                          VALUES (?, ?, ?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE 
                                          email_notifications = VALUES(email_notifications),
                                          submission_alerts = VALUES(submission_alerts),
                                          deadline_reminders = VALUES(deadline_reminders),
                                          class_updates = VALUES(class_updates),
                                          student_progress = VALUES(student_progress)");
                    $stmt->execute([$user_id, $email_notifications, $submission_alerts, $deadline_reminders, $class_updates, $student_progress]);
                    $success_message = "Cập nhật cài đặt thông báo thành công!";
                } catch (PDOException $e) {
                    $error_message = "Lỗi: " . $e->getMessage();
                }
                break;
        }
    }
}

// Lấy thông tin user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Lấy cài đặt thông báo
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_settings) {
        $user_settings = [
            'email_notifications' => 1,
            'submission_alerts' => 1,
            'deadline_reminders' => 1,
            'class_updates' => 1,
            'student_progress' => 1,
            'theme' => 'light',
            'language' => 'vi'
        ];
    }
    
    // Thống kê
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
    $stmt->execute([$user_id]);
    $total_classes = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ce.student_id) FROM class_enrollments ce 
                          JOIN classes c ON ce.class_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$user_id]);
    $total_students = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $user_info = [];
    $user_settings = [];
    $total_classes = 0;
    $total_students = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt | Giáo viên - EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/teacher-platform.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .settings-header {
            margin-bottom: 32px;
        }
        
        .settings-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .settings-header h1 i {
            color: var(--primary-color);
        }
        
        .settings-header p {
            color: var(--text-secondary);
            margin-top: 8px;
        }
        
        .settings-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }
        
        @media (max-width: 900px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Settings Navigation */
        .settings-nav {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .settings-nav-item:hover {
            background: rgba(46, 125, 50, 0.1);
            color: var(--primary-color);
        }
        
        .settings-nav-item.active {
            background: var(--primary-color);
            color: white;
        }
        
        .settings-nav-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Profile Preview Card */
        .profile-preview {
            text-align: center;
            padding: 24px 16px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .avatar-preview-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
        }
        
        .avatar-preview-wrapper img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }
        
        .avatar-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: 600;
            border: 3px solid var(--primary-color);
        }
        
        .profile-preview h4 {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
        }
        
        .profile-preview p {
            font-size: 13px;
            color: #fff;
        }
        
        .profile-preview .role-badge {
            display: inline-block;
            padding: 4px 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .profile-stat {
            text-align: center;
        }
        
        .profile-stat h5 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .profile-stat p {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* Settings Content */
        .settings-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .settings-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
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
            color: var(--primary-color);
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Form Styles */
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            background: var(--card-bg);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }
        
        .form-group input:disabled {
            background: var(--background);
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(46, 125, 50, 0.1);
            border: 1px solid var(--primary-color);
            color: var(--primary-dark);
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid #f44336;
            color: #f44336;
        }
        
        /* Password Toggle */
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
            color: var(--primary-color);
        }
        
        /* Avatar Upload */
        .avatar-upload-section {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 20px;
            background: var(--background);
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .current-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
        }
        
        .current-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 600;
            border: 4px solid var(--primary-color);
        }
        
        .avatar-upload-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .avatar-upload-info p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        
        /* Toggle Switch */
        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .toggle-group:last-child {
            border-bottom: none;
        }
        
        .toggle-info h4 {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .toggle-info p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: var(--transition);
            border-radius: 28px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background-color: var(--primary-color);
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 24px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .back-btn:hover {
            color: var(--primary-color);
        }
        
        /* Modal */
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
            background: var(--card-bg);
            border-radius: 16px;
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
        }
        
        .modal-close:hover {
            color: #f44336;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-actions {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .upload-zone:hover {
            border-color: var(--primary-color);
            background: rgba(46, 125, 50, 0.05);
        }
        
        .upload-zone i {
            font-size: 48px;
            color: var(--primary-color);
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
            border-radius: 12px;
            margin: 16px auto;
            display: none;
        }
        
        /* Danger Zone */
        .danger-zone {
            border: 2px solid #f44336;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
            text-align: left;
        }
        
        .danger-zone h4 {
            color: #f44336;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .danger-zone p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }
        
        .btn-danger {
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(244,67,54,0.08);
            cursor: pointer;
            width: fit-content;
        }
        .btn-danger i {
            font-size: 18px;
        }
        .btn-danger:hover, .btn-danger:focus {
            background: #d32f2f;
            color: #fff;
            box-shadow: 0 4px 16px rgba(244,67,54,0.15);
        }
    </style>
</head>
<body>
    <div id="header-placeholder"></div>

    <div class="sidebar-trigger"></div>
    <div class="sidebar" id="sidebar">
        <div class="menu-container">
            <div class="menu-section">
                <div class="menu-header">Menu chính</div>
                <div class="menu-items">
                    <div class="menu-item" onclick="window.location.href='trangchu_giaovien.php'">
                        <i class="fas fa-home"></i>
                        <span>Trang chủ</span>
                    </div>
                    <div class="menu-item" onclick="window.location.href='trangchu_giaovien.php#classes'">
                        <i class="fas fa-chalkboard"></i>
                        <span>Lớp học của tôi</span>
                    </div>
                    <div class="menu-item" onclick="window.location.href='trangchu_giaovien.php#assignments'">
                        <i class="fas fa-tasks"></i>
                        <span>Nhiệm vụ</span>
                    </div>
                </div>
            </div>
            <div class="menu-section">
                <div class="menu-header">Tài khoản</div>
                <div class="menu-items">
                    <div class="menu-item active">
                        <i class="fas fa-cog"></i>
                        <span>Cài đặt</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="main-container">
        <div class="settings-container">
            <a href="trangchu_giaovien.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Quay lại trang chủ
            </a>
            
            <div class="settings-header">
                <h1><i class="fas fa-cog"></i> Cài đặt tài khoản</h1>
                <p>Quản lý thông tin cá nhân và tùy chỉnh trải nghiệm giảng dạy của bạn</p>
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
            
            <div class="settings-layout">
                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <div class="profile-preview">
                        <div class="avatar-preview-wrapper">
                            <?php if (!empty($user_info['avatar']) && file_exists(__DIR__ . '/../uploads/avatar/' . $user_info['avatar'])): ?>
                                <img src="../uploads/avatar/<?php echo htmlspecialchars($user_info['avatar']); ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($user_info['fullname'] ?? 'G', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h4><?php echo htmlspecialchars($user_info['fullname'] ?? ''); ?></h4>
                        <p><?php echo htmlspecialchars($user_info['email'] ?? ''); ?></p>
                        <span class="role-badge"><i class="fas fa-chalkboard-teacher"></i> Giáo viên</span>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <h5><?php echo $total_classes; ?></h5>
                            <p>Lớp học</p>
                        </div>
                        <div class="profile-stat">
                            <h5><?php echo $total_students; ?></h5>
                            <p>Học sinh</p>
                        </div>
                    </div>
                    
                    <div class="settings-nav-item active" data-section="profile">
                        <i class="fas fa-user"></i>
                        <span>Thông tin cá nhân</span>
                    </div>
                    <div class="settings-nav-item" data-section="security">
                        <i class="fas fa-shield-alt"></i>
                        <span>Bảo mật</span>
                    </div>
                    <div class="settings-nav-item" data-section="notifications">
                        <i class="fas fa-bell"></i>
                        <span>Thông báo</span>
                    </div>
                    <div class="settings-nav-item" data-section="appearance">
                        <i class="fas fa-palette"></i>
                        <span>Giao diện</span>
                    </div>
                </div>
                
                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Profile Section -->
                    <div class="settings-section active" id="profile-section">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-camera"></i>
                                <h3>Ảnh đại diện</h3>
                            </div>
                            <div class="card-body">
                                <div class="avatar-upload-section">
                                    <?php if (!empty($user_info['avatar']) && file_exists(__DIR__ . '/../uploads/avatar/' . $user_info['avatar'])): ?>
                                        <img src="../uploads/avatar/<?php echo htmlspecialchars($user_info['avatar']); ?>" alt="Avatar" class="current-avatar">
                                    <?php else: ?>
                                        <div class="current-avatar-placeholder">
                                            <?php echo strtoupper(substr($user_info['fullname'] ?? 'G', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="avatar-upload-info">
                                        <h4>Thay đổi ảnh đại diện</h4>
                                        <p>Định dạng: JPEG, PNG, GIF, WEBP. Tối đa 5MB.</p>
                                        <button type="button" class="btn btn-outline" onclick="openAvatarModal()">
                                            <i class="fas fa-upload"></i> Tải ảnh lên
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-edit"></i>
                                <h3>Thông tin cá nhân</h3>
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
                                            <label for="gender">Giới tính</label>
                                            <select id="gender" name="gender">
                                                <option value="">-- Chọn giới tính --</option>
                                                <option value="male" <?php echo ($user_info['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Nam</option>
                                                <option value="female" <?php echo ($user_info['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Nữ</option>
                                                <option value="other" <?php echo ($user_info['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Khác</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="birthday">Ngày sinh</label>
                                            <input type="date" id="birthday" name="birthday" 
                                                   value="<?php echo htmlspecialchars($user_info['birthday'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="username">Tên đăng nhập</label>
                                            <input type="text" id="username" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" disabled>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bio">Giới thiệu bản thân</label>
                                        <textarea id="bio" name="bio" placeholder="Viết vài dòng giới thiệu về bạn..."><?php echo htmlspecialchars($user_info['bio'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Lưu thay đổi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Section -->
                    <div class="settings-section" id="security-section">
                        <div class="settings-card">
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
                                
                                <div class="danger-zone">
                                    <h4><i class="fas fa-exclamation-triangle"></i> Vùng nguy hiểm</h4>
                                    <p>Xóa tài khoản sẽ xóa vĩnh viễn tất cả dữ liệu của bạn bao gồm lớp học, bài tập và điểm số. Hành động này không thể hoàn tác.</p>
                                    <button type="button" class="btn btn-danger" onclick="confirmDeleteAccount()">
                                        <i class="fas fa-trash"></i> Xóa tài khoản
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notifications Section -->
                    <div class="settings-section" id="notifications-section">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-bell"></i>
                                <h3>Cài đặt thông báo</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_notifications">
                                    
                                    <div class="toggle-group">
                                        <div class="toggle-info">
                                            <h4>Thông báo qua Email</h4>
                                            <p>Nhận thông báo quan trọng qua email</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="email_notifications" <?php echo ($user_settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-group">
                                        <div class="toggle-info">
                                            <h4>Thông báo bài nộp</h4>
                                            <p>Nhận thông báo khi học sinh nộp bài</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="submission_alerts" <?php echo ($user_settings['submission_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-group">
                                        <div class="toggle-info">
                                            <h4>Nhắc nhở hạn chót</h4>
                                            <p>Nhận thông báo khi bài tập sắp đến hạn</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="deadline_reminders" <?php echo ($user_settings['deadline_reminders'] ?? 1) ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-group">
                                        <div class="toggle-info">
                                            <h4>Cập nhật lớp học</h4>
                                            <p>Nhận thông báo khi có học sinh mới tham gia lớp</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="class_updates" <?php echo ($user_settings['class_updates'] ?? 1) ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="toggle-group">
                                        <div class="toggle-info">
                                            <h4>Tiến độ học sinh</h4>
                                            <p>Nhận báo cáo hàng tuần về tiến độ học tập của học sinh</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="student_progress" <?php echo ($user_settings['student_progress'] ?? 1) ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                                        <i class="fas fa-save"></i> Lưu cài đặt
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appearance Section -->
                    <div class="settings-section" id="appearance-section">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-palette"></i>
                                <h3>Giao diện</h3>
                            </div>
                            <div class="card-body">
                                <div class="toggle-group">
                                    <div class="toggle-info">
                                        <h4>Chế độ tối</h4>
                                        <p>Bật chế độ tối để bảo vệ mắt khi sử dụng ban đêm</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="dark-mode-toggle" onchange="toggleDarkMode()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="toggle-group">
                                    <div class="toggle-info">
                                        <h4>Hiệu ứng chuyển động</h4>
                                        <p>Bật/tắt hiệu ứng animation trên trang</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="toggle-group">
                                    <div class="toggle-info">
                                        <h4>Thu gọn sidebar mặc định</h4>
                                        <p>Sidebar sẽ tự động thu gọn khi vào trang</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

    <div id="footer-placeholder"></div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Load header and footer
        fetch('/components/header.php')
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const links = doc.head.getElementsByTagName('link');
                for (let link of links) {
                    if (!document.querySelector(`link[href="${link.getAttribute('href')}"]`)) {
                        document.head.appendChild(link.cloneNode(true));
                    }
                }
                document.getElementById('header-placeholder').innerHTML = doc.body.innerHTML;
            });

        fetch('/components/footer.html')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            });

        // Settings Navigation
        document.querySelectorAll('.settings-nav-item').forEach(item => {
            item.addEventListener('click', function() {
                const section = this.dataset.section;
                
                document.querySelectorAll('.settings-nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelectorAll('.settings-section').forEach(sec => sec.classList.remove('active'));
                document.getElementById(section + '-section').classList.add('active');
            });
        });

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

        // Dark mode toggle
        function toggleDarkMode() {
            document.documentElement.setAttribute('data-theme', 
                document.getElementById('dark-mode-toggle').checked ? 'dark' : 'light'
            );
            localStorage.setItem('theme', document.getElementById('dark-mode-toggle').checked ? 'dark' : 'light');
        }

        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.getElementById('dark-mode-toggle').checked = true;
        }

        // Confirm delete account
        function confirmDeleteAccount() {
            Swal.fire({
                title: 'Xác nhận xóa tài khoản?',
                text: 'Tất cả dữ liệu của bạn sẽ bị xóa vĩnh viễn bao gồm lớp học, bài tập và điểm số. Hành động này không thể hoàn tác!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f44336',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Xóa tài khoản',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Đã hủy', 'Tính năng đang được phát triển', 'info');
                }
            });
        }

        // Sidebar functionality
        const sidebarTrigger = document.querySelector('.sidebar-trigger');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarTrigger && sidebar) {
            sidebarTrigger.addEventListener('mouseenter', () => {
                sidebar.classList.add('active');
            });
            
            sidebar.addEventListener('mouseleave', () => {
                sidebar.classList.remove('active');
            });
        }

        // Logout confirmation
        function xacNhanDangXuat() {
            Swal.fire({
                title: 'Xác nhận đăng xuất',
                text: 'Bạn có chắc chắn muốn đăng xuất?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Đăng xuất',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../dangnhap/dangxuat.php';
                }
            });
        }
    </script>
</body>
</html>