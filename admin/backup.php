<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Sao lưu dữ liệu";
$current_page = "backup";

$db_path = __DIR__ . '/../db/edservices.db';
$backup_dir = __DIR__ . '/../db/backups';

// Tạo thư mục backup nếu chưa có
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$success_message = '';
$error_message = '';

// Xử lý tạo backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_backup') {
        $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.db';
        $backup_path = $backup_dir . '/' . $backup_name;
        
        if (copy($db_path, $backup_path)) {
            $success_message = "Đã tạo bản sao lưu: $backup_name";
            
            // Log activity
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'create_backup', ?, ?, datetime('now'))");
                if ($stmt) {
                    $stmt->execute([$_SESSION['user_id'], "Created backup: $backup_name", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                }
            } catch (PDOException $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
        } else {
            $error_message = "Không thể tạo bản sao lưu";
        }
    } elseif ($_POST['action'] === 'delete_backup' && isset($_POST['backup_file'])) {
        $file_to_delete = basename($_POST['backup_file']);
        $file_path = $backup_dir . '/' . $file_to_delete;
        
        if (file_exists($file_path) && unlink($file_path)) {
            $success_message = "Đã xóa bản sao lưu: $file_to_delete";
        } else {
            $error_message = "Không thể xóa bản sao lưu";
        }
    } elseif ($_POST['action'] === 'restore_backup' && isset($_POST['backup_file'])) {
        $file_to_restore = basename($_POST['backup_file']);
        $file_path = $backup_dir . '/' . $file_to_restore;
        
        if (file_exists($file_path)) {
            // Tạo backup trước khi restore
            $pre_restore_backup = $backup_dir . '/pre_restore_' . date('Y-m-d_H-i-s') . '.db';
            copy($db_path, $pre_restore_backup);
            
            if (copy($file_path, $db_path)) {
                $success_message = "Đã khôi phục từ bản sao lưu: $file_to_restore";
                
                // Reconnect to the restored database
                try {
                    // Close current connection
                    $pdo = null;
                    
                    // Reconnect
                    $pdo = new PDO(
                        'sqlite:' . $db_path,
                        null,
                        null,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false
                        ]
                    );
                    $pdo->exec('PRAGMA foreign_keys = ON');
                } catch (PDOException $e) {
                    error_log("Database reconnect error: " . $e->getMessage());
                }
            } else {
                $error_message = "Không thể khôi phục bản sao lưu";
            }
        } else {
            $error_message = "File sao lưu không tồn tại";
        }
    } elseif ($_POST['action'] === 'download_backup' && isset($_POST['backup_file'])) {
        $file_to_download = basename($_POST['backup_file']);
        $file_path = $backup_dir . '/' . $file_to_download;
        
        if (file_exists($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_to_download . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit();
        } else {
            $error_message = "File không tồn tại";
        }
    }
}

// Lấy danh sách backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'db') {
            $file_path = $backup_dir . '/' . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => filemtime($file_path)
            ];
        }
    }
}

// Thông tin database hiện tại
$db_size = file_exists($db_path) ? filesize($db_path) : 0;
$db_modified = file_exists($db_path) ? filemtime($db_path) : 0;

// Đếm số bản ghi - SỬA: Thêm try-catch để tránh lỗi foreign key
try {
    $stats = [
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0,
        'classes' => $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn() ?? 0,
        'enrollments' => $pdo->query("SELECT COUNT(*) FROM class_enrollments")->fetchColumn() ?? 0,
        'logs' => $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn() ?? 0
    ];
} catch (PDOException $e) {
    error_log("Error counting records: " . $e->getMessage());
    $stats = ['users' => 0, 'classes' => 0, 'enrollments' => 0, 'logs' => 0];
}

// Helper function để format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
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
        .backup-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
        }
        
        .db-info-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: fit-content;
        }
        
        .db-info-card h3 {
            margin: 0 0 20px;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .db-info-card h3 i {
            color: #4CAF50;
        }
        
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px;
        }
        
        .info-list li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .info-list li:last-child {
            border-bottom: none;
        }
        
        .info-list .label {
            color: #64748b;
            font-size: 14px;
        }
        
        .info-list .value {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .btn-backup {
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
            font-family: inherit;
        }
        
        .btn-backup:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .backups-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .backups-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .backups-card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .backups-card-header h3 i {
            color: #4CAF50;
        }
        
        .backups-card-body {
            padding: 0;
        }
        
        .backup-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        
        .backup-item:hover {
            background: #f8fafc;
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .backup-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #64748b;
        }
        
        .backup-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .backup-actions {
            display: flex;
            gap: 8px;
        }
        
        .backup-actions button, .backup-actions form {
            display: inline;
        }
        
        .backup-actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-restore {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-restore:hover {
            background: #bbdefb;
        }
        
        .btn-download {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .btn-download:hover {
            background: #c8e6c9;
        }
        
        .btn-delete-backup {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn-delete-backup:hover {
            background: #ffcdd2;
        }
        
        .empty-backups {
            padding: 60px 24px;
            text-align: center;
            color: #94a3b8;
        }
        
        .empty-backups i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
        
        .empty-backups h4 {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 16px;
        }
        
        .empty-backups p {
            margin: 0;
            font-size: 14px;
        }
        
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
        
        .warning-box {
            margin-top: 20px;
            padding: 16px;
            background: #fff3e0;
            border: 1px solid #ffe0b2;
            border-radius: 8px;
            font-size: 13px;
            color: #e65100;
        }
        
        .warning-box i {
            margin-right: 8px;
        }
        
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .stat-mini-item {
            text-align: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .stat-mini-item .number {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-mini-item .label {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        
        @media (max-width: 1024px) {
            .backup-grid {
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
                    <h1><i class="fas fa-database"></i> Sao lưu dữ liệu</h1>
                    <p>Quản lý và khôi phục dữ liệu hệ thống</p>
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
            
            <div class="backup-grid">
                <!-- Database Info -->
                <div class="db-info-card">
                    <h3><i class="fas fa-info-circle"></i> Thông tin Database</h3>
                    
                    <ul class="info-list">
                        <li>
                            <span class="label">Loại database</span>
                            <span class="value">SQLite</span>
                        </li>
                        <li>
                            <span class="label">Kích thước</span>
                            <span class="value"><?php echo formatFileSize($db_size); ?></span>
                        </li>
                        <li>
                            <span class="label">Cập nhật lần cuối</span>
                            <span class="value"><?php echo $db_modified ? date('d/m/Y H:i', $db_modified) : 'N/A'; ?></span>
                        </li>
                        <li>
                            <span class="label">Số bản sao lưu</span>
                            <span class="value"><?php echo count($backups); ?></span>
                        </li>
                    </ul>
                    
                    <div class="stats-mini">
                        <div class="stat-mini-item">
                            <div class="number"><?php echo $stats['users']; ?></div>
                            <div class="label">Người dùng</div>
                        </div>
                        <div class="stat-mini-item">
                            <div class="number"><?php echo $stats['classes']; ?></div>
                            <div class="label">Lớp học</div>
                        </div>
                        <div class="stat-mini-item">
                            <div class="number"><?php echo $stats['enrollments']; ?></div>
                            <div class="label">Ghi danh</div>
                        </div>
                        <div class="stat-mini-item">
                            <div class="number"><?php echo $stats['logs']; ?></div>
                            <div class="label">Nhật ký</div>
                        </div>
                    </div>
                    
                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" class="btn-backup" onclick="return confirm('Tạo bản sao lưu mới?')">
                            <i class="fas fa-plus-circle"></i>
                            <span>Tạo bản sao lưu mới</span>
                        </button>
                    </form>
                    
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Lưu ý:</strong> Nên sao lưu dữ liệu thường xuyên để tránh mất mát dữ liệu. 
                        Khuyến nghị sao lưu ít nhất 1 lần/tuần.
                    </div>
                </div>
                
                <!-- Backups List -->
                <div class="backups-card">
                    <div class="backups-card-header">
                        <h3><i class="fas fa-history"></i> Danh sách bản sao lưu</h3>
                        <span class="badge badge-primary"><?php echo count($backups); ?> bản</span>
                    </div>
                    <div class="backups-card-body">
                        <?php if (empty($backups)): ?>
                            <div class="empty-backups">
                                <i class="fas fa-archive"></i>
                                <h4>Chưa có bản sao lưu nào</h4>
                                <p>Hãy tạo bản sao lưu đầu tiên để bảo vệ dữ liệu của bạn</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($backups as $backup): ?>
                                <div class="backup-item">
                                    <div class="backup-icon">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <div class="backup-info">
                                        <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                                        <div class="backup-meta">
                                            <span><i class="fas fa-hdd"></i> <?php echo formatFileSize($backup['size']); ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i:s', $backup['date']); ?></span>
                                        </div>
                                    </div>
                                    <div class="backup-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="restore_backup">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                            <button type="submit" class="btn-restore" onclick="return confirm('Khôi phục từ bản sao lưu này? Dữ liệu hiện tại sẽ được sao lưu tự động trước khi khôi phục.')">
                                                <i class="fas fa-undo"></i> Khôi phục
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="download_backup">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                            <button type="submit" class="btn-download">
                                                <i class="fas fa-download"></i> Tải về
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                            <button type="submit" class="btn-delete-backup" onclick="return confirm('Xóa bản sao lưu này?')">
                                                <i class="fas fa-trash"></i> Xóa
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
