<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Cấu hình hệ thống";
$current_page = "settings";

$success_message = '';
$error_message = '';

// Khởi tạo bảng settings nếu chưa có - SỬA: Tắt foreign key trước, sau đó bật lại
try {
    // Tắt foreign key constraints tạm thời
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT UNIQUE NOT NULL,
            setting_value TEXT,
            setting_group TEXT DEFAULT 'general',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            template_key TEXT UNIQUE NOT NULL,
            template_name TEXT NOT NULL,
            subject TEXT,
            body TEXT,
            variables TEXT,
            is_active INTEGER DEFAULT 1,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Bật lại foreign key constraints
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Insert default settings if not exist
    $defaultSettings = [
        ['system_name', 'EDUSERVICE', 'brand'],
        ['system_tagline', 'Nền tảng học tập trực tuyến', 'brand'],
        ['logo_url', '/images/logo.png', 'brand'],
        ['favicon_url', '/images/favicon.ico', 'brand'],
        ['primary_color', '#4CAF50', 'brand'],
        ['contact_email', 'support@vnu.edu.vn', 'contact'],
        ['contact_phone', '024 3558 5858', 'contact'],
        ['contact_address', 'Đại học Quốc gia Hà Nội', 'contact'],
        ['facebook_url', '', 'social'],
        ['youtube_url', '', 'social'],
        ['maintenance_mode', '0', 'system'],
        ['allow_registration', '1', 'system'],
    ];
    
    foreach ($defaultSettings as $setting) {
        try {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
            $stmt->execute($setting);
        } catch (PDOException $e) {
            error_log("Error inserting setting {$setting[0]}: " . $e->getMessage());
        }
    }
    
    // Insert default email templates if not exist
    $defaultTemplates = [
        ['welcome', 'Email chào mừng', 'Chào mừng bạn đến với {system_name}', 
         '<h2>Xin chào {fullname}!</h2><p>Chào mừng bạn đến với <strong>{system_name}</strong>.</p><p>Tài khoản của bạn đã được tạo thành công với thông tin:</p><ul><li>Email: {email}</li><li>Vai trò: {role}</li></ul><p>Trân trọng,<br>{system_name} Team</p>',
         'fullname,email,role,system_name'],
        ['reset_password', 'Đặt lại mật khẩu', 'Yêu cầu đặt lại mật khẩu - {system_name}',
         '<h2>Xin chào {fullname}!</h2><p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p><p>Mật khẩu mới của bạn là: <strong style="font-size: 18px; color: #4CAF50;">{new_password}</strong></p><p>Vui lòng đăng nhập và đổi mật khẩu ngay sau khi nhận được email này.</p><p>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p><p>Trân trọng,<br>{system_name} Team</p>',
         'fullname,email,new_password,system_name'],
        ['score_notification', 'Thông báo điểm', 'Thông báo điểm bài tập - {class_name}',
         '<h2>Xin chào {fullname}!</h2><p>Bài tập <strong>{assignment_name}</strong> của bạn trong lớp <strong>{class_name}</strong> đã được chấm điểm.</p><p>Điểm số: <strong style="font-size: 24px; color: #4CAF50;">{score}</strong></p><p>Nhận xét của giảng viên:</p><blockquote style="background: #f5f5f5; padding: 15px; border-left: 4px solid #4CAF50;">{feedback}</blockquote><p>Trân trọng,<br>{system_name} Team</p>',
         'fullname,assignment_name,class_name,score,feedback,system_name'],
        ['reminder', 'Nhắc nhở', 'Nhắc nhở: {reminder_title}',
         '<h2>Xin chào {fullname}!</h2><p>Đây là nhắc nhở về: <strong>{reminder_title}</strong></p><p>{reminder_content}</p><p>Thời hạn: <strong style="color: #f44336;">{deadline}</strong></p><p>Trân trọng,<br>{system_name} Team</p>',
         'fullname,reminder_title,reminder_content,deadline,system_name'],
        ['account_created', 'Tài khoản được tạo', 'Tài khoản {system_name} của bạn đã được tạo',
         '<h2>Xin chào {fullname}!</h2><p>Tài khoản của bạn trên <strong>{system_name}</strong> đã được tạo bởi quản trị viên.</p><p><strong>Thông tin đăng nhập:</strong></p><table style="background: #f5f5f5; padding: 15px; border-radius: 8px; width: 100%;"><tr><td style="padding: 8px;"><strong>Email:</strong></td><td style="padding: 8px;">{email}</td></tr><tr><td style="padding: 8px;"><strong>Mật khẩu:</strong></td><td style="padding: 8px; color: #d32f2f; font-weight: bold;">{password}</td></tr><tr><td style="padding: 8px;"><strong>Vai trò:</strong></td><td style="padding: 8px;">{role}</td></tr></table><p style="color: #f44336;"><strong>⚠️ Lưu ý:</strong> Vui lòng đổi mật khẩu ngay sau khi đăng nhập lần đầu!</p><p>Trân trọng,<br>{system_name} Team</p>',
         'fullname,email,password,role,system_name'],
    ];
    
    foreach ($defaultTemplates as $template) {
        try {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO email_templates (template_key, template_name, subject, body, variables) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($template);
        } catch (PDOException $e) {
            error_log("Error inserting template {$template[0]}: " . $e->getMessage());
        }
    }
    
} catch (PDOException $e) {
    // Bật lại foreign key nếu lỗi
    try { $pdo->exec('PRAGMA foreign_keys = ON'); } catch (Exception $ex) {}
    error_log("Settings init error: " . $e->getMessage());
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_brand') {
            $settings = [
                'system_name' => trim($_POST['system_name'] ?? ''),
                'system_tagline' => trim($_POST['system_tagline'] ?? ''),
                'logo_url' => trim($_POST['logo_url'] ?? ''),
                'favicon_url' => trim($_POST['favicon_url'] ?? ''),
                'primary_color' => trim($_POST['primary_color'] ?? '#4CAF50'),
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = datetime('now') WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            
            $success_message = "Đã cập nhật thông tin thương hiệu";
            
        } elseif ($action === 'update_contact') {
            $settings = [
                'contact_email' => trim($_POST['contact_email'] ?? ''),
                'contact_phone' => trim($_POST['contact_phone'] ?? ''),
                'contact_address' => trim($_POST['contact_address'] ?? ''),
                'facebook_url' => trim($_POST['facebook_url'] ?? ''),
                'youtube_url' => trim($_POST['youtube_url'] ?? ''),
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = datetime('now') WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            
            $success_message = "Đã cập nhật thông tin liên hệ";
            
        } elseif ($action === 'update_system') {
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $allow_registration = isset($_POST['allow_registration']) ? '1' : '0';
            
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = datetime('now') WHERE setting_key = ?");
            $stmt->execute([$maintenance_mode, 'maintenance_mode']);
            $stmt->execute([$allow_registration, 'allow_registration']);
            
            $success_message = "Đã cập nhật cài đặt hệ thống";
            
        } elseif ($action === 'update_template') {
            $template_id = (int)($_POST['template_id'] ?? 0);
            $subject = trim($_POST['subject'] ?? '');
            $body = $_POST['body'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($template_id > 0) {
                $stmt = $pdo->prepare("UPDATE email_templates SET subject = ?, body = ?, is_active = ?, updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$subject, $body, $is_active, $template_id]);
                $success_message = "Đã cập nhật mẫu email";
            }
            
        } elseif ($action === 'update_profile') {
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (!empty($fullname) && !empty($email)) {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$fullname, $email, $_SESSION['user_id']]);
                $_SESSION['user'] = $fullname;
                $_SESSION['email'] = $email;
                $success_message = "Đã cập nhật thông tin cá nhân";
            }
            
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                $error_message = "Mật khẩu mới không khớp";
            } elseif (strlen($new_password) < 6) {
                $error_message = "Mật khẩu mới phải có ít nhất 6 ký tự";
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['password'])) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = datetime('now') WHERE id = ?");
                    $stmt->execute([$new_hash, $_SESSION['user_id']]);
                    $success_message = "Đã đổi mật khẩu thành công";
                } else {
                    $error_message = "Mật khẩu hiện tại không đúng";
                }
            }
        }
        
        // Log activity - SỬA: Thêm kiểm tra và try-catch
        if ($success_message) {
            try {
                $user_id = $_SESSION['user_id'] ?? null;
                
                // Kiểm tra user có tồn tại không trước khi log
                if ($user_id) {
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $checkStmt->execute([$user_id]);
                    
                    if ($checkStmt->fetch()) {
                        // User tồn tại, log bình thường
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
                        $logStmt->execute([$user_id, 'update_settings', $success_message, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    } else {
                        // User không tồn tại, log với NULL
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (NULL, ?, ?, ?, datetime('now'))");
                        $logStmt->execute(['update_settings', $success_message, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    }
                }
            } catch (PDOException $logErr) {
                // Không làm gián đoạn thao tác chính nếu log thất bại
                error_log("Settings activity log failed: " . $logErr->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Lấy settings
function getSettings($pdo, $group = null) {
    try {
        $sql = "SELECT setting_key, setting_value FROM system_settings";
        if ($group) {
            $sql .= " WHERE setting_group = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$group]);
        } else {
            $stmt = $pdo->query($sql);
        }
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (PDOException $e) {
        error_log("Error getting settings: " . $e->getMessage());
        return [];
    }
}

$brandSettings = getSettings($pdo, 'brand');
$contactSettings = getSettings($pdo, 'contact');
$socialSettings = getSettings($pdo, 'social');
$systemSettings = getSettings($pdo, 'system');

// Lấy email templates
try {
    $emailTemplates = $pdo->query("SELECT * FROM email_templates ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting email templates: " . $e->getMessage());
    $emailTemplates = [];
}

// Lấy thông tin admin hiện tại
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting admin info: " . $e->getMessage());
    $admin = [];
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
        .settings-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }
        
        /* Settings Navigation */
        .settings-nav {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 16px;
            height: fit-content;
            position: sticky;
            top: 24px;
        }
        
        .settings-nav-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #94a3b8;
            padding: 12px 16px 8px;
            letter-spacing: 0.5px;
        }
        
        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        
        .settings-nav-item:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        .settings-nav-item.active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .settings-nav-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Settings Content */
        .settings-content {
            min-height: 600px;
        }
        
        .settings-section {
            display: none;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .settings-section-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .settings-section-header h2 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .settings-section-header h2 i {
            color: #4CAF50;
        }
        
        .settings-section-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .settings-section-body {
            padding: 24px;
        }
        
        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group:last-child {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
        }
        
        .color-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .color-input-wrapper input[type="color"] {
            width: 50px;
            height: 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            padding: 2px;
        }
        
        .color-input-wrapper input[type="text"] {
            flex: 1;
        }
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-size: 14px;
            color: #1e293b;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #4CAF50;
        }
        
        /* Preview */
        .logo-preview {
            margin-top: 12px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            text-align: center;
        }
        
        .logo-preview img {
            max-height: 60px;
            max-width: 200px;
        }
        
        /* Email Templates */
        .template-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .template-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .template-item:hover {
            border-color: #4CAF50;
        }
        
        .template-item.active {
            background: #e8f5e9;
            border-color: #4CAF50;
        }
        
        .template-item-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .template-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
        }
        
        .template-item-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .template-item-key {
            font-size: 12px;
            color: #94a3b8;
        }
        
        .template-item-status {
            font-size: 12px;
        }
        
        .template-editor {
            display: none;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .template-editor.active {
            display: block;
        }
        
        .template-editor h4 {
            margin: 0 0 16px;
            font-size: 16px;
            color: #1e293b;
        }
        
        .variables-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .variable-tag {
            padding: 4px 10px;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 4px;
            font-size: 12px;
            font-family: monospace;
            cursor: pointer;
        }
        
        .variable-tag:hover {
            background: #bbdefb;
        }
        
        .email-preview {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .email-preview-header {
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }
        
        .email-preview-subject {
            font-weight: 600;
            color: #1e293b;
        }
        
        /* Buttons */
        .btn-save {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Alert */
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }
            
            .settings-nav {
                position: static;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 12px;
            }
            
            .settings-nav-title {
                width: 100%;
            }
            
            .settings-nav-item {
                flex: 1;
                min-width: 150px;
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h1><i class="fas fa-cog"></i> Cấu hình hệ thống</h1>
                    <p>Quản lý cài đặt và tùy chỉnh hệ thống</p>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="settings-layout">
                <!-- Settings Navigation -->
                <nav class="settings-nav">
                    <div class="settings-nav-title">Thiết lập chung</div>
                    <button class="settings-nav-item active" onclick="showSection('brand')">
                        <i class="fas fa-palette"></i>
                        <span>Thương hiệu</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSection('contact')">
                        <i class="fas fa-address-card"></i>
                        <span>Thông tin liên hệ</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSection('system')">
                        <i class="fas fa-sliders-h"></i>
                        <span>Cài đặt hệ thống</span>
                    </button>
                    
                    <div class="settings-nav-title">Mẫu Email</div>
                    <button class="settings-nav-item" onclick="showSection('email')">
                        <i class="fas fa-envelope"></i>
                        <span>Quản lý mẫu</span>
                    </button>
                    
                    <div class="settings-nav-title">Tài khoản</div>
                    <button class="settings-nav-item" onclick="showSection('profile')">
                        <i class="fas fa-user"></i>
                        <span>Thông tin cá nhân</span>
                    </button>
                    <button class="settings-nav-item" onclick="showSection('password')">
                        <i class="fas fa-lock"></i>
                        <span>Đổi mật khẩu</span>
                    </button>
                </nav>
                
                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Brand Settings -->
                    <div class="settings-section active" id="section-brand">
                        <div class="settings-section-header">
                            <h2><i class="fas fa-palette"></i> Thông tin thương hiệu</h2>
                            <p>Tùy chỉnh logo, tên hệ thống và màu sắc chủ đạo</p>
                        </div>
                        <div class="settings-section-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_brand">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Tên hệ thống</label>
                                        <input type="text" name="system_name" class="form-input" 
                                               value="<?php echo htmlspecialchars($brandSettings['system_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Slogan / Tagline</label>
                                        <input type="text" name="system_tagline" class="form-input" 
                                               value="<?php echo htmlspecialchars($brandSettings['system_tagline'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">URL Logo</label>
                                    <input type="url" name="logo_url" id="logo-url" class="form-input" 
                                           value="<?php echo htmlspecialchars($brandSettings['logo_url'] ?? ''); ?>"
                                           placeholder="https://example.com/logo.png"
                                           onchange="previewLogo()">
                                    <div class="logo-preview" id="logo-preview">
                                        <?php if (!empty($brandSettings['logo_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($brandSettings['logo_url']); ?>" alt="Logo" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">Chưa có logo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">URL Favicon</label>
                                        <input type="url" name="favicon_url" class="form-input" 
                                               value="<?php echo htmlspecialchars($brandSettings['favicon_url'] ?? ''); ?>"
                                               placeholder="https://example.com/favicon.ico">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Màu chủ đạo</label>
                                        <div class="color-input-wrapper">
                                            <input type="color" id="color-picker" 
                                                   value="<?php echo htmlspecialchars($brandSettings['primary_color'] ?? '#4CAF50'); ?>"
                                                   onchange="document.getElementById('primary-color').value = this.value">
                                            <input type="text" name="primary_color" id="primary-color" class="form-input" 
                                                   value="<?php echo htmlspecialchars($brandSettings['primary_color'] ?? '#4CAF50'); ?>"
                                                   onchange="document.getElementById('color-picker').value = this.value">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> Lưu thay đổi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Contact Settings -->
                    <div class="settings-section" id="section-contact">
                        <div class="settings-section-header">
                            <h2><i class="fas fa-address-card"></i> Thông tin liên hệ</h2>
                            <p>Cấu hình thông tin liên hệ và mạng xã hội</p>
                        </div>
                        <div class="settings-section-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_contact">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Email hỗ trợ</label>
                                        <input type="email" name="contact_email" class="form-input" 
                                               value="<?php echo htmlspecialchars($contactSettings['contact_email'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Số điện thoại</label>
                                        <input type="text" name="contact_phone" class="form-input" 
                                               value="<?php echo htmlspecialchars($contactSettings['contact_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Địa chỉ</label>
                                    <textarea name="contact_address" class="form-textarea" rows="3"><?php echo htmlspecialchars($contactSettings['contact_address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook URL</label>
                                        <input type="url" name="facebook_url" class="form-input" 
                                               value="<?php echo htmlspecialchars($socialSettings['facebook_url'] ?? ''); ?>"
                                               placeholder="https://facebook.com/...">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fab fa-youtube" style="color: #ff0000;"></i> YouTube URL</label>
                                        <input type="url" name="youtube_url" class="form-input" 
                                               value="<?php echo htmlspecialchars($socialSettings['youtube_url'] ?? ''); ?>"
                                               placeholder="https://youtube.com/...">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> Lưu thay đổi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- System Settings -->
                    <div class="settings-section" id="section-system">
                        <div class="settings-section-header">
                            <h2><i class="fas fa-sliders-h"></i> Cài đặt hệ thống</h2>
                            <p>Cấu hình các tính năng và trạng thái hệ thống</p>
                        </div>
                        <div class="settings-section-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_system">
                                
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="maintenance_mode" <?php echo ($systemSettings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                        <span>
                                            <strong>Chế độ bảo trì</strong><br>
                                            <small style="color: #94a3b8;">Khi bật, người dùng không thể truy cập hệ thống (trừ Admin)</small>
                                        </span>
                                    </label>
                                    
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="allow_registration" <?php echo ($systemSettings['allow_registration'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <span>
                                            <strong>Cho phép đăng ký</strong><br>
                                            <small style="color: #94a3b8;">Cho phép người dùng tự đăng ký tài khoản mới</small>
                                        </span>
                                    </label>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> Lưu thay đổi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Email Templates -->
                    <div class="settings-section" id="section-email">
                        <div class="settings-section-header">
                            <h2><i class="fas fa-envelope"></i> Mẫu Email</h2>
                            <p>Soạn thảo và quản lý các mẫu email tự động</p>
                        </div>
                        <div class="settings-section-body">
                            <div class="template-list">
                                <?php foreach ($emailTemplates as $index => $template): ?>
                                    <div class="template-item <?php echo $index === 0 ? 'active' : ''; ?>" onclick="selectTemplate(<?php echo $template['id']; ?>)" data-template-id="<?php echo $template['id']; ?>">
                                        <div class="template-item-info">
                                            <div class="template-item-icon">
                                                <?php
                                                $icons = [
                                                    'welcome' => 'fa-hand-wave',
                                                    'reset_password' => 'fa-key',
                                                    'score_notification' => 'fa-star',
                                                    'reminder' => 'fa-bell',
                                                    'account_created' => 'fa-user-plus',
                                                ];
                                                $icon = $icons[$template['template_key']] ?? 'fa-envelope';
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="template-item-name"><?php echo htmlspecialchars($template['template_name']); ?></div>
                                                <div class="template-item-key"><?php echo htmlspecialchars($template['template_key']); ?></div>
                                            </div>
                                        </div>
                                        <div class="template-item-status">
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Hoạt động</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><i class="fas fa-pause"></i> Tắt</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php foreach ($emailTemplates as $index => $template): ?>
                                <div class="template-editor <?php echo $index === 0 ? 'active' : ''; ?>" id="template-editor-<?php echo $template['id']; ?>">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_template">
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        
                                        <h4><i class="fas fa-edit"></i> Chỉnh sửa: <?php echo htmlspecialchars($template['template_name']); ?></h4>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Biến có sẵn (click để copy)</label>
                                            <div class="variables-list">
                                                <?php 
                                                $vars = explode(',', $template['variables'] ?? '');
                                                foreach ($vars as $var): 
                                                    $var = trim($var);
                                                    if ($var):
                                                ?>
                                                    <span class="variable-tag" onclick="copyVariable('{<?php echo $var; ?>}')">{<?php echo $var; ?>}</span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Tiêu đề email</label>
                                            <input type="text" name="subject" class="form-input" 
                                                   value="<?php echo htmlspecialchars($template['subject'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Nội dung email (HTML)</label>
                                            <textarea name="body" class="form-textarea" rows="12"><?php echo htmlspecialchars($template['body'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="is_active" <?php echo $template['is_active'] ? 'checked' : ''; ?>>
                                                <span>Kích hoạt mẫu email này</span>
                                            </label>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" class="btn-secondary" onclick="previewEmail(<?php echo $template['id']; ?>)">
                                                <i class="fas fa-eye"></i> Xem trước
                                            </button>
                                            <button type="submit" class="btn-save">
                                                <i class="fas fa-save"></i> Lưu mẫu
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Profile Settings -->
                    <div class="settings-section" id="section-profile">
                        <div class="settings-section-header">
                            <h2><i class="fas fa-user"></i> Thông tin cá nhân</h2>
                            <p>Cập nhật thông tin tài khoản admin của bạn</p>
                        </div>
                        <div class="settings-section-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Họ và tên</label>
                                        <input type="text" name="fullname" class="form-input" 
                                               value="<?php echo htmlspecialchars($admin['fullname'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-input" 
                                               value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Vai trò</label>
                                        <input type="text" class="form-input" value="Administrator" disabled>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Ngày tạo</label>
                                        <input type="text" class="form-input" value="<?php echo $admin['created_at'] ? date('d/m/Y H:i', strtotime($admin['created_at'])) : 'N/A'; ?>" disabled>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> Lưu thay đổi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Password Settings -->
                    <div class="settings-section" id="section-password">
                        <div class="settings-section-header">
                            <h2><i class="fas fa-lock"></i> Đổi mật khẩu</h2>
                            <p>Thay đổi mật khẩu đăng nhập của bạn</p>
                        </div>
                        <div class="settings-section-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group">
                                    <label class="form-label">Mật khẩu hiện tại</label>
                                    <input type="password" name="current_password" class="form-input" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Mật khẩu mới</label>
                                        <input type="password" name="new_password" class="form-input" required minlength="6">
                                        <small class="form-hint">Tối thiểu 6 ký tự</small>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Xác nhận mật khẩu mới</label>
                                        <input type="password" name="confirm_password" class="form-input" required>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-key"></i> Đổi mật khẩu
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Show/hide settings sections
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
            
            // Remove active from all nav items
            document.querySelectorAll('.settings-nav-item').forEach(n => n.classList.remove('active'));
            
            // Show selected section
            document.getElementById('section-' + sectionId).classList.add('active');
            
            // Activate clicked nav item
            event.currentTarget.classList.add('active');
        }
        
        // Preview logo
        function previewLogo() {
            const url = document.getElementById('logo-url').value;
            const preview = document.getElementById('logo-preview');
            
            if (url) {
                preview.innerHTML = '<img src="' + url + '" alt="Logo Preview" onerror="this.onerror=null;this.parentElement.innerHTML=\'<span style=color:#c62828>Không thể tải logo</span>\'">';
            } else {
                preview.innerHTML = '<span style="color: #94a3b8;">Chưa có logo</span>';
            }
        }
        
        // Select email template
        function selectTemplate(templateId) {
            // Update template items
            document.querySelectorAll('.template-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector('.template-item[data-template-id="' + templateId + '"]').classList.add('active');
            
            // Update template editors
            document.querySelectorAll('.template-editor').forEach(editor => {
                editor.classList.remove('active');
            });
            document.getElementById('template-editor-' + templateId).classList.add('active');
        }
        
        // Copy variable to clipboard
        function copyVariable(variable) {
            navigator.clipboard.writeText(variable).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Đã copy: ' + variable,
                    showConfirmButton: false,
                    timer: 1500
                });
            });
        }
        
        // Preview email
        function previewEmail(templateId) {
            const editor = document.getElementById('template-editor-' + templateId);
            const subject = editor.querySelector('input[name="subject"]').value;
            const body = editor.querySelector('textarea[name="body"]').value;
            
            // Replace sample variables
            const sampleData = {
                '{fullname}': 'Nguyễn Văn A',
                '{email}': 'nguyenvana@vnu.edu.vn',
                '{role}': 'Sinh viên',
                '{system_name}': 'EDUSERVICE',
                '{new_password}': 'Abc123@xyz',
                '{password}': 'RandomPass123',
                '{class_name}': 'Lập trình Web',
                '{assignment_name}': 'Bài tập 1',
                '{score}': '9.5',
                '{feedback}': 'Bài làm tốt, cần cải thiện phần giao diện.',
                '{reminder_title}': 'Nộp bài tập',
                '{reminder_content}': 'Hãy hoàn thành bài tập trước thời hạn.',
                '{deadline}': '31/12/2024 23:59'
            };
            
            let previewSubject = subject;
            let previewBody = body;
            
            for (const [key, value] of Object.entries(sampleData)) {
                previewSubject = previewSubject.split(key).join(value);
                previewBody = previewBody.split(key).join(value);
            }
            
            Swal.fire({
                title: '<i class="fas fa-envelope"></i> Xem trước Email',
                html: `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <div style="padding: 12px; background: #f5f5f5; border-radius: 8px; margin-bottom: 16px;">
                            <strong>Tiêu đề:</strong> ${previewSubject}
                        </div>
                        <div style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px;">
                            ${previewBody}
                        </div>
                    </div>
                `,
                width: 700,
                showCloseButton: true,
                showConfirmButton: false
            });
        }
    </script>
</body>
</html>
