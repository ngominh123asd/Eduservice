<?php
// File: admin/notifications.php - System Notifications Management
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Thông báo hệ thống";
$current_page = "notifications";

$success_message = '';
$error_message = '';

// Khởi tạo bảng notifications nếu chưa có
try {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            type TEXT DEFAULT 'info' CHECK(type IN ('info', 'warning', 'success', 'danger')),
            target_type TEXT DEFAULT 'all' CHECK(target_type IN ('all', 'students', 'teachers', 'specific')),
            target_users TEXT,
            is_read INTEGER DEFAULT 0,
            priority TEXT DEFAULT 'normal' CHECK(priority IN ('low', 'normal', 'high', 'urgent')),
            scheduled_at DATETIME,
            sent_at DATETIME,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_reads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notification_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (notification_id) REFERENCES notifications(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(notification_id, user_id)
        )
    ");
    
    $pdo->exec('PRAGMA foreign_keys = ON');
    
} catch (PDOException $e) {
    error_log("Notifications init error: " . $e->getMessage());
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_notification') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $type = $_POST['type'] ?? 'info';
            $target_type = $_POST['target_type'] ?? 'all';
            $priority = $_POST['priority'] ?? 'normal';
            $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
            $send_now = isset($_POST['send_now']);
            
            if (empty($title) || empty($content)) {
                $error_message = "Vui lòng nhập đầy đủ tiêu đề và nội dung";
            } else {
                $sent_at = $send_now ? date('Y-m-d H:i:s') : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (title, content, type, target_type, priority, scheduled_at, sent_at, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([$title, $content, $type, $target_type, $priority, $scheduled_at, $sent_at, $_SESSION['user_id']]);
                
                $success_message = $send_now ? "Đã gửi thông báo thành công" : "Đã lên lịch thông báo";
                
                // Log activity
                try {
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
                    $logStmt->execute([$_SESSION['user_id'], 'create_notification', $success_message . ": $title", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                } catch (PDOException $e) {
                    error_log("Log error: " . $e->getMessage());
                }
            }
            
        } elseif ($action === 'delete_notification') {
            $id = (int)($_POST['notification_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM notification_reads WHERE notification_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
                $success_message = "Đã xóa thông báo";
            }
            
        } elseif ($action === 'resend_notification') {
            $id = (int)($_POST['notification_id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE notifications SET sent_at = datetime('now') WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "Đã gửi lại thông báo";
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Lấy danh sách thông báo
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $total = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    $total_pages = ceil($total / $limit);
    
    $notifications = $pdo->query("
        SELECT n.*, u.fullname as creator_name,
               (SELECT COUNT(*) FROM notification_reads WHERE notification_id = n.id) as read_count
        FROM notifications n
        LEFT JOIN users u ON n.created_by = u.id
        ORDER BY n.created_at DESC
        LIMIT $limit OFFSET $offset
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN sent_at IS NULL AND scheduled_at IS NOT NULL THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
        FROM notifications
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Đếm số người dùng theo vai trò
    $user_counts = $pdo->query("
        SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch (PDOException $e) {
    $notifications = [];
    $stats = ['total' => 0, 'sent' => 0, 'scheduled' => 0, 'urgent' => 0];
    $user_counts = [];
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
        .notification-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .stat-card .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.total { background: #e3f2fd; color: #1976d2; }
        .stat-icon.sent { background: #e8f5e9; color: #388e3c; }
        .stat-icon.scheduled { background: #fff3e0; color: #f57c00; }
        .stat-icon.urgent { background: #ffebee; color: #d32f2f; }
        
        .stat-card .stat-content .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-card .stat-content .stat-label {
            font-size: 14px;
            color: #64748b;
        }
        
        .notifications-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
        }
        
        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #4CAF50;
        }
        
        .notification-item.type-warning { border-left-color: #ff9800; }
        .notification-item.type-danger { border-left-color: #f44336; }
        .notification-item.type-info { border-left-color: #2196f3; }
        .notification-item.type-success { border-left-color: #4CAF50; }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .notification-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .notification-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-priority-urgent { background: #ffebee; color: #d32f2f; }
        .badge-priority-high { background: #fff3e0; color: #f57c00; }
        .badge-priority-normal { background: #e3f2fd; color: #1976d2; }
        .badge-priority-low { background: #f5f5f5; color: #757575; }
        
        .badge-sent { background: #e8f5e9; color: #388e3c; }
        .badge-scheduled { background: #fff3e0; color: #f57c00; }
        .badge-draft { background: #f5f5f5; color: #757575; }
        
        .notification-content {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        
        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }
        
        .notification-info {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .notification-info span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
        }
        
        .notification-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-resend { background: #e3f2fd; color: #1976d2; }
        .btn-resend:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #d32f2f; }
        .btn-delete:hover { background: #ffcdd2; }
        
        /* Create Form */
        .create-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 24px;
        }
        
        .create-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .create-card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .create-card-header h3 i {
            color: #4CAF50;
        }
        
        .create-card-body {
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
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
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
        
        .btn-create {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .target-info {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
            color: #64748b;
        }
        
        .target-info i {
            margin-right: 8px;
            color: #4CAF50;
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
        
        @media (max-width: 1024px) {
            .notification-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .notifications-grid {
                grid-template-columns: 1fr;
            }
            .create-card {
                position: static;
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
                    <h1><i class="fas fa-bell"></i> Thông báo hệ thống</h1>
                    <p>Broadcast thông báo tới người dùng</p>
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
            <div class="notification-stats">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-bell"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="stat-label">Tổng thông báo</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon sent"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['sent'] ?? 0; ?></div>
                        <div class="stat-label">Đã gửi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon scheduled"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['scheduled'] ?? 0; ?></div>
                        <div class="stat-label">Đã lên lịch</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon urgent"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['urgent'] ?? 0; ?></div>
                        <div class="stat-label">Khẩn cấp</div>
                    </div>
                </div>
            </div>
            
            <div class="notifications-grid">
                <!-- Notifications List -->
                <div class="notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>Chưa có thông báo nào</h3>
                            <p>Tạo thông báo đầu tiên để gửi tới người dùng</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item type-<?php echo $notif['type']; ?>">
                                <div class="notification-header">
                                    <h4 class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                    <div class="notification-meta">
                                        <span class="notification-badge badge-priority-<?php echo $notif['priority']; ?>">
                                            <?php 
                                            $priorities = ['low' => 'Thấp', 'normal' => 'Bình thường', 'high' => 'Cao', 'urgent' => 'Khẩn cấp'];
                                            echo $priorities[$notif['priority']] ?? $notif['priority'];
                                            ?>
                                        </span>
                                        <?php if ($notif['sent_at']): ?>
                                            <span class="notification-badge badge-sent"><i class="fas fa-check"></i> Đã gửi</span>
                                        <?php elseif ($notif['scheduled_at']): ?>
                                            <span class="notification-badge badge-scheduled"><i class="fas fa-clock"></i> Đã lên lịch</span>
                                        <?php else: ?>
                                            <span class="notification-badge badge-draft"><i class="fas fa-file"></i> Nháp</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="notification-content">
                                    <?php echo nl2br(htmlspecialchars(substr($notif['content'], 0, 200))); ?>
                                    <?php if (strlen($notif['content']) > 200): ?>...<?php endif; ?>
                                </div>
                                <div class="notification-footer">
                                    <div class="notification-info">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($notif['creator_name'] ?? 'Hệ thống'); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo $notif['created_at'] ? date('d/m/Y H:i', strtotime($notif['created_at'])) : 'N/A'; ?></span>
                                        <span><i class="fas fa-eye"></i> <?php echo $notif['read_count'] ?? 0; ?> đã đọc</span>
                                        <span><i class="fas fa-users"></i> 
                                            <?php
                                            $targets = ['all' => 'Tất cả', 'students' => 'Sinh viên', 'teachers' => 'Giảng viên', 'specific' => 'Chọn lọc'];
                                            echo $targets[$notif['target_type']] ?? $notif['target_type'];
                                            ?>
                                        </span>
                                    </div>
                                    <div class="notification-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="resend_notification">
                                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="btn-resend" onclick="return confirm('Gửi lại thông báo này?')">
                                                <i class="fas fa-redo"></i> Gửi lại
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_notification">
                                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Xóa thông báo này?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination" style="margin-top: 20px; display: flex; gap: 8px; justify-content: center;">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Create Form -->
                <div class="create-card">
                    <div class="create-card-header">
                        <h3><i class="fas fa-plus-circle"></i> Tạo thông báo mới</h3>
                    </div>
                    <div class="create-card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_notification">
                            
                            <div class="form-group">
                                <label class="form-label">Tiêu đề <span style="color: #f44336;">*</span></label>
                                <input type="text" name="title" class="form-input" required placeholder="Nhập tiêu đề thông báo">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nội dung <span style="color: #f44336;">*</span></label>
                                <textarea name="content" class="form-textarea" required placeholder="Nhập nội dung thông báo..."></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Loại</label>
                                    <select name="type" class="form-select">
                                        <option value="info">Thông tin</option>
                                        <option value="success">Thành công</option>
                                        <option value="warning">Cảnh báo</option>
                                        <option value="danger">Quan trọng</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Độ ưu tiên</label>
                                    <select name="priority" class="form-select">
                                        <option value="low">Thấp</option>
                                        <option value="normal" selected>Bình thường</option>
                                        <option value="high">Cao</option>
                                        <option value="urgent">Khẩn cấp</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Đối tượng nhận</label>
                                <select name="target_type" class="form-select" id="target-type" onchange="updateTargetInfo()">
                                    <option value="all">Tất cả người dùng</option>
                                    <option value="students">Chỉ sinh viên</option>
                                    <option value="teachers">Chỉ giảng viên</option>
                                </select>
                                <div class="target-info" id="target-info">
                                    <i class="fas fa-users"></i>
                                    <span id="target-count">
                                        Sẽ gửi tới <?php echo array_sum($user_counts); ?> người dùng
                                    </span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Lên lịch gửi</label>
                                <input type="datetime-local" name="scheduled_at" class="form-input">
                                <small style="color: #94a3b8; font-size: 12px;">Để trống để gửi ngay lập tức</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="send_now" checked>
                                    <span>Gửi ngay lập tức</span>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-create">
                                <i class="fas fa-paper-plane"></i>
                                Gửi thông báo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const userCounts = <?php echo json_encode($user_counts); ?>;
        const totalUsers = <?php echo array_sum($user_counts); ?>;
        
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
    </script>
</body>
</html>
