<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Email Marketing";
$current_page = "email";

$success_message = '';
$error_message = '';

// Khởi tạo bảng email campaigns nếu chưa có
try {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            content TEXT NOT NULL,
            template_id INTEGER,
            target_type TEXT DEFAULT 'all' CHECK(target_type IN ('all', 'students', 'teachers', 'specific')),
            target_users TEXT,
            status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'scheduled', 'sending', 'sent', 'failed')),
            scheduled_at DATETIME,
            sent_at DATETIME,
            total_recipients INTEGER DEFAULT 0,
            total_sent INTEGER DEFAULT 0,
            total_opened INTEGER DEFAULT 0,
            total_clicked INTEGER DEFAULT 0,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            recipient_email TEXT NOT NULL,
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'sent', 'failed', 'bounced')),
            opened_at DATETIME,
            clicked_at DATETIME,
            error_message TEXT,
            sent_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec('PRAGMA foreign_keys = ON');
    
} catch (PDOException $e) {
    error_log("Email init error: " . $e->getMessage());
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_campaign') {
            $name = trim($_POST['name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $content = $_POST['content'] ?? '';
            $target_type = $_POST['target_type'] ?? 'all';
            $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
            $send_now = isset($_POST['send_now']);
            
            if (empty($name) || empty($subject) || empty($content)) {
                $error_message = "Vui lòng nhập đầy đủ thông tin chiến dịch";
            } else {
                // Đếm số người nhận
                $countQuery = "SELECT COUNT(*) FROM users WHERE status = 'active'";
                if ($target_type === 'students') {
                    $countQuery .= " AND role = 'student'";
                } elseif ($target_type === 'teachers') {
                    $countQuery .= " AND role = 'teacher'";
                }
                $total_recipients = $pdo->query($countQuery)->fetchColumn();
                
                $status = $send_now ? 'sending' : ($scheduled_at ? 'scheduled' : 'draft');
                $sent_at = $send_now ? date('Y-m-d H:i:s') : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO email_campaigns (name, subject, content, target_type, status, scheduled_at, sent_at, total_recipients, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([$name, $subject, $content, $target_type, $status, $scheduled_at, $sent_at, $total_recipients, $_SESSION['user_id']]);
                
                $campaign_id = $pdo->lastInsertId();
                
                // Nếu gửi ngay, tạo email logs (giả lập)
                if ($send_now) {
                    $recipientQuery = "SELECT email FROM users WHERE status = 'active'";
                    if ($target_type === 'students') {
                        $recipientQuery .= " AND role = 'student'";
                    } elseif ($target_type === 'teachers') {
                        $recipientQuery .= " AND role = 'teacher'";
                    }
                    
                    $recipients = $pdo->query($recipientQuery)->fetchAll(PDO::FETCH_COLUMN);
                    
                    $logStmt = $pdo->prepare("INSERT INTO email_logs (campaign_id, recipient_email, status, sent_at) VALUES (?, ?, 'sent', datetime('now'))");
                    foreach ($recipients as $email) {
                        $logStmt->execute([$campaign_id, $email]);
                    }
                    
                    // Cập nhật total_sent
                    $pdo->prepare("UPDATE email_campaigns SET total_sent = ?, status = 'sent' WHERE id = ?")->execute([count($recipients), $campaign_id]);
                    
                    $success_message = "Đã gửi chiến dịch email tới " . count($recipients) . " người nhận";
                } else {
                    $success_message = $scheduled_at ? "Đã lên lịch chiến dịch email" : "Đã lưu chiến dịch email";
                }
                
                // Log activity
                try {
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
                    $logStmt->execute([$_SESSION['user_id'], 'create_email_campaign', $success_message . ": $name", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } catch (PDOException $e) {
                    error_log("Log error: " . $e->getMessage());
                }
            }
            
        } elseif ($action === 'delete_campaign') {
            $id = (int)($_POST['campaign_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM email_logs WHERE campaign_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM email_campaigns WHERE id = ?")->execute([$id]);
                $success_message = "Đã xóa chiến dịch";
            }
            
        } elseif ($action === 'duplicate_campaign') {
            $id = (int)($_POST['campaign_id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ?");
                $stmt->execute([$id]);
                $original = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($original) {
                    $stmt = $pdo->prepare("
                        INSERT INTO email_campaigns (name, subject, content, target_type, status, total_recipients, created_by, created_at)
                        VALUES (?, ?, ?, ?, 'draft', ?, ?, datetime('now'))
                    ");
                    $stmt->execute([
                        $original['name'] . ' (Bản sao)',
                        $original['subject'],
                        $original['content'],
                        $original['target_type'],
                        $original['total_recipients'],
                        $_SESSION['user_id']
                    ]);
                    $success_message = "Đã nhân bản chiến dịch";
                }
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Lấy danh sách campaigns
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $total = $pdo->query("SELECT COUNT(*) FROM email_campaigns")->fetchColumn();
    $total_pages = ceil($total / $limit);
    
    $campaigns = $pdo->query("
        SELECT ec.*, u.fullname as creator_name
        FROM email_campaigns ec
        LEFT JOIN users u ON ec.created_by = u.id
        ORDER BY ec.created_at DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_campaigns,
            SUM(total_sent) as total_emails_sent,
            SUM(total_opened) as total_opened,
            SUM(total_clicked) as total_clicked,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as campaigns_sent,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as campaigns_scheduled
        FROM email_campaigns
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Email templates
    $templates = $pdo->query("SELECT id, template_name, template_key FROM email_templates WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    // User counts
    $user_counts = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch (PDOException $e) {
    $campaigns = [];
    $stats = ['total_campaigns' => 0, 'total_emails_sent' => 0, 'total_opened' => 0, 'total_clicked' => 0];
    $templates = [];
    $user_counts = [];
}

// Tính tỷ lệ
$open_rate = ($stats['total_emails_sent'] ?? 0) > 0 ? round(($stats['total_opened'] ?? 0) / $stats['total_emails_sent'] * 100, 1) : 0;
$click_rate = ($stats['total_emails_sent'] ?? 0) > 0 ? round(($stats['total_clicked'] ?? 0) / $stats['total_emails_sent'] * 100, 1) : 0;
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
        .email-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .stat-card .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .stat-icon.campaigns { background: #e3f2fd; color: #1976d2; }
        .stat-icon.sent { background: #e8f5e9; color: #388e3c; }
        .stat-icon.opened { background: #fff3e0; color: #f57c00; }
        .stat-icon.clicked { background: #f3e5f5; color: #7b1fa2; }
        .stat-icon.rate { background: #e0f2f1; color: #00897b; }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        
        /* Tabs */
        .email-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .email-tab {
            padding: 12px 24px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .email-tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        .email-tab.active {
            background: #4CAF50;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Campaign List */
        .campaigns-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .campaigns-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .campaigns-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .campaigns-header h3 i {
            color: #4CAF50;
        }
        
        .campaign-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        
        .campaign-item:hover {
            background: #f8fafc;
        }
        
        .campaign-item:last-child {
            border-bottom: none;
        }
        
        .campaign-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .campaign-info {
            flex: 1;
            min-width: 0;
        }
        
        .campaign-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 4px;
        }
        
        .campaign-subject {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .campaign-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .campaign-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .campaign-stats {
            display: flex;
            gap: 24px;
            text-align: center;
        }
        
        .campaign-stat {
            min-width: 60px;
        }
        
        .campaign-stat-number {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .campaign-stat-label {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .campaign-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-sent { background: #e8f5e9; color: #388e3c; }
        .status-scheduled { background: #fff3e0; color: #f57c00; }
        .status-draft { background: #f5f5f5; color: #757575; }
        .status-sending { background: #e3f2fd; color: #1976d2; }
        .status-failed { background: #ffebee; color: #d32f2f; }
        
        .campaign-actions {
            display: flex;
            gap: 8px;
        }
        
        .campaign-actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-duplicate { background: #e3f2fd; color: #1976d2; }
        .btn-duplicate:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #d32f2f; }
        .btn-delete:hover { background: #ffcdd2; }
        
        /* Create Campaign Form */
        .create-campaign-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .create-campaign-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .create-campaign-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .create-campaign-header h3 i {
            color: #4CAF50;
        }
        
        .create-campaign-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
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
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .form-textarea {
            min-height: 200px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4CAF50;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-primary {
            padding: 14px 28px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            padding: 14px 28px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .template-select-wrapper {
            position: relative;
        }
        
        .template-preview-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            padding: 6px 12px;
            background: #e8f5e9;
            color: #388e3c;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .target-info {
            margin-top: 8px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 13px;
            color: #64748b;
        }
        
        .target-info i {
            margin-right: 8px;
            color: #4CAF50;
        }
        
        /* Templates Tab */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .template-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .template-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .template-card-header {
            padding: 20px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
        }
        
        .template-card-header h4 {
            margin: 0 0 8px;
            font-size: 16px;
        }
        
        .template-card-header span {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .template-card-body {
            padding: 20px;
        }
        
        .template-card-body p {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 16px;
            line-height: 1.6;
        }
        
        .template-card-footer {
            padding: 16px 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .template-vars {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .template-var {
            padding: 4px 8px;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 4px;
            font-size: 10px;
            font-family: monospace;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            margin: 0 0 8px;
            color: #64748b;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 14px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 20px;
        }
        
        .pagination-btn {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .pagination-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        .pagination-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        @media (max-width: 1200px) {
            .email-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .email-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
            .campaign-item {
                flex-wrap: wrap;
            }
            .campaign-stats {
                width: 100%;
                justify-content: space-around;
                margin-top: 12px;
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
                    <h1><i class="fas fa-envelope"></i> Email Marketing</h1>
                    <p>Quản lý chiến dịch email và theo dõi hiệu quả</p>
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
            
            <!-- Stats -->
            <div class="email-stats">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon campaigns"><i class="fas fa-bullhorn"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_campaigns'] ?? 0; ?></div>
                    <div class="stat-label">Chiến dịch</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon sent"><i class="fas fa-paper-plane"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_emails_sent'] ?? 0); ?></div>
                    <div class="stat-label">Email đã gửi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon opened"><i class="fas fa-envelope-open"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_opened'] ?? 0); ?></div>
                    <div class="stat-label">Đã mở</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon clicked"><i class="fas fa-mouse-pointer"></i></div>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_clicked'] ?? 0); ?></div>
                    <div class="stat-label">Đã click</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon rate"><i class="fas fa-chart-line"></i></div>
                    </div>
                    <div class="stat-number"><?php echo $open_rate; ?>%</div>
                    <div class="stat-label">Tỷ lệ mở</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="email-tabs">
                <button class="email-tab active" onclick="switchTab('campaigns')">
                    <i class="fas fa-list"></i> Chiến dịch
                </button>
                <button class="email-tab" onclick="switchTab('create')">
                    <i class="fas fa-plus"></i> Tạo mới
                </button>
                <button class="email-tab" onclick="switchTab('templates')">
                    <i class="fas fa-file-alt"></i> Mẫu email
                </button>
            </div>
            
            <!-- Tab: Campaigns List -->
            <div class="tab-content active" id="tab-campaigns">
                <div class="campaigns-list">
                    <div class="campaigns-header">
                        <h3><i class="fas fa-bullhorn"></i> Danh sách chiến dịch</h3>
                        <span class="badge badge-primary"><?php echo $total; ?> chiến dịch</span>
                    </div>
                    
                    <?php if (empty($campaigns)): ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope-open"></i>
                            <h3>Chưa có chiến dịch nào</h3>
                            <p>Tạo chiến dịch email đầu tiên để bắt đầu tiếp thị</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($campaigns as $campaign): ?>
                            <div class="campaign-item">
                                <div class="campaign-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="campaign-info">
                                    <div class="campaign-name"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                    <div class="campaign-subject"><?php echo htmlspecialchars($campaign['subject']); ?></div>
                                    <div class="campaign-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($campaign['creator_name'] ?? 'Hệ thống'); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo $campaign['created_at'] ? date('d/m/Y H:i', strtotime($campaign['created_at'])) : 'N/A'; ?></span>
                                        <span><i class="fas fa-users"></i> 
                                            <?php
                                            $targets = ['all' => 'Tất cả', 'students' => 'Sinh viên', 'teachers' => 'Giảng viên'];
                                            echo $targets[$campaign['target_type']] ?? $campaign['target_type'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="campaign-stats">
                                    <div class="campaign-stat">
                                        <div class="campaign-stat-number"><?php echo $campaign['total_sent'] ?? 0; ?></div>
                                        <div class="campaign-stat-label">Đã gửi</div>
                                    </div>
                                    <div class="campaign-stat">
                                        <div class="campaign-stat-number"><?php echo $campaign['total_opened'] ?? 0; ?></div>
                                        <div class="campaign-stat-label">Đã mở</div>
                                    </div>
                                    <div class="campaign-stat">
                                        <div class="campaign-stat-number"><?php echo $campaign['total_clicked'] ?? 0; ?></div>
                                        <div class="campaign-stat-label">Đã click</div>
                                    </div>
                                </div>
                                <span class="campaign-status status-<?php echo $campaign['status']; ?>">
                                    <?php
                                    $statuses = ['draft' => 'Nháp', 'scheduled' => 'Đã lên lịch', 'sending' => 'Đang gửi', 'sent' => 'Đã gửi', 'failed' => 'Thất bại'];
                                    echo $statuses[$campaign['status']] ?? $campaign['status'];
                                    ?>
                                </span>
                                <div class="campaign-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="duplicate_campaign">
                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                        <button type="submit" class="btn-duplicate" title="Nhân bản">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa chiến dịch này?')">
                                        <input type="hidden" name="action" value="delete_campaign">
                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                        <button type="submit" class="btn-delete" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab: Create Campaign -->
            <div class="tab-content" id="tab-create">
                <div class="create-campaign-card">
                    <div class="create-campaign-header">
                        <h3><i class="fas fa-plus-circle"></i> Tạo chiến dịch email mới</h3>
                    </div>
                    <div class="create-campaign-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_campaign">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Tên chiến dịch <span style="color: #f44336;">*</span></label>
                                    <input type="text" name="name" class="form-input" required placeholder="VD: Thông báo khai giảng kỳ 2">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tiêu đề email <span style="color: #f44336;">*</span></label>
                                    <input type="text" name="subject" class="form-input" required placeholder="VD: [EDUSERVICE] Thông báo khai giảng">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Đối tượng nhận</label>
                                    <select name="target_type" class="form-select" id="target-type" onchange="updateTargetInfo()">
                                        <option value="all">Tất cả người dùng</option>
                                        <option value="students">Chỉ sinh viên</option>
                                        <option value="teachers">Chỉ giảng viên</option>
                                    </select>
                                    <div class="target-info" id="target-info">
                                        <i class="fas fa-users"></i>
                                        <span id="target-count">Sẽ gửi tới <?php echo array_sum($user_counts); ?> người dùng</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Mẫu email (tùy chọn)</label>
                                    <select name="template_id" class="form-select" id="template-select" onchange="loadTemplate()">
                                        <option value="">-- Không sử dụng mẫu --</option>
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?php echo $template['id']; ?>" data-key="<?php echo $template['template_key']; ?>">
                                                <?php echo htmlspecialchars($template['template_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nội dung email <span style="color: #f44336;">*</span></label>
                                <textarea name="content" id="email-content" class="form-textarea" required placeholder="Nhập nội dung email (hỗ trợ HTML)..."></textarea>
                                <small style="color: #94a3b8; font-size: 12px;">
                                    <i class="fas fa-lightbulb"></i> Mẹo: Sử dụng các biến như {fullname}, {email} để cá nhân hóa email
                                </small>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Lên lịch gửi</label>
                                    <input type="datetime-local" name="scheduled_at" class="form-input">
                                    <small style="color: #94a3b8; font-size: 12px;">Để trống để lưu nháp hoặc gửi ngay</small>
                                </div>
                                <div class="form-group" style="display: flex; align-items: flex-end;">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="send_now" id="send-now">
                                        <span>Gửi ngay lập tức</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" onclick="previewEmail()">
                                    <i class="fas fa-eye"></i> Xem trước
                                </button>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-paper-plane"></i> Tạo chiến dịch
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Templates -->
            <div class="tab-content" id="tab-templates">
                <div class="templates-grid">
                    <?php if (empty($templates)): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fas fa-file-alt"></i>
                            <h3>Chưa có mẫu email nào</h3>
                            <p>Tạo mẫu email trong phần <a href="settings.php">Cấu hình hệ thống</a></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <div class="template-card">
                                <div class="template-card-header">
                                    <h4><?php echo htmlspecialchars($template['template_name']); ?></h4>
                                    <span><?php echo htmlspecialchars($template['template_key']); ?></span>
                                </div>
                                <div class="template-card-body">
                                    <p>Mẫu email dùng để gửi thông báo tự động cho người dùng.</p>
                                </div>
                                <div class="template-card-footer">
                                    <div class="template-vars">
                                        <span class="template-var">{fullname}</span>
                                        <span class="template-var">{email}</span>
                                    </div>
                                    <button class="btn-secondary" style="padding: 8px 16px; font-size: 12px;" onclick="useTemplate('<?php echo $template['template_key']; ?>')">
                                        <i class="fas fa-plus"></i> Sử dụng
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const userCounts = <?php echo json_encode($user_counts); ?>;
        const totalUsers = <?php echo array_sum($user_counts); ?>;
        
        // Switch tabs
        function switchTab(tabName) {
            document.querySelectorAll('.email-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        // Update target info
        function updateTargetInfo() {
            const targetType = document.getElementById('target-type').value;
            const targetCount = document.getElementById('target-count');
            
            let count = totalUsers;
            if (targetType === 'students') {
                count = userCounts['student'] || 0;
            } else if (targetType === 'teachers') {
                count = userCounts['teacher'] || 0;
            }
            
            targetCount.textContent = 'Sẽ gửi tới ' + count + ' người dùng';
        }
        
        // Load template
        function loadTemplate() {
            const select = document.getElementById('template-select');
            const templateKey = select.options[select.selectedIndex].dataset.key;
            
            if (templateKey) {
                // Giả lập load template content
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Đã tải mẫu: ' + templateKey,
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        }
        
        // Use template
        function useTemplate(templateKey) {
            document.querySelectorAll('.email-tab')[1].click(); // Switch to create tab
            
            const select = document.getElementById('template-select');
            for (let option of select.options) {
                if (option.dataset.key === templateKey) {
                    option.selected = true;
                    loadTemplate();
                    break;
                }
            }
        }
        
        // Preview email
        function previewEmail() {
            const subject = document.querySelector('input[name="subject"]').value || 'Chưa có tiêu đề';
            const content = document.getElementById('email-content').value || 'Chưa có nội dung';
            
            // Replace sample variables
            const sampleData = {
                '{fullname}': 'Nguyễn Văn A',
                '{email}': 'nguyenvana@vnu.edu.vn',
                '{system_name}': 'EDUSERVICE'
            };
            
            let previewContent = content;
            for (const [key, value] of Object.entries(sampleData)) {
                previewContent = previewContent.split(key).join(value);
            }
            
            Swal.fire({
                title: '<i class="fas fa-envelope"></i> Xem trước Email',
                html: `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <div style="padding: 12px; background: #f5f5f5; border-radius: 8px; margin-bottom: 16px;">
                            <strong>Tiêu đề:</strong> ${subject}
                        </div>
                        <div style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px;">
                            ${previewContent.replace(/\n/g, '<br>')}
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
