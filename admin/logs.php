<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Nhật ký hoạt động";
$current_page = "logs";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    // Build query
    $where_conditions = [];
    $params = [];
    
    if ($action_filter) {
        $where_conditions[] = "al.action LIKE ?";
        $params[] = "%$action_filter%";
    }
    
    if ($user_filter) {
        $where_conditions[] = "al.user_id = ?";
        $params[] = $user_filter;
    }
    
    if ($date_from) {
        $where_conditions[] = "date(al.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "date(al.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Count total
    $count_sql = "SELECT COUNT(*) FROM activity_logs al $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $limit);
    
    // Get logs
    $sql = "SELECT al.*, u.fullname, u.email
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $where_clause
            ORDER BY al.created_at DESC
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique actions for filter
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    
    // Get users for filter
    $users = $pdo->query("SELECT id, fullname FROM users ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi: " . $e->getMessage();
    $logs = [];
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
        .log-item {
            display: flex;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .log-icon.login { background: #e3f2fd; color: #1976d2; }
        .log-icon.create { background: #e8f5e9; color: #388e3c; }
        .log-icon.update { background: #fff3e0; color: #f57c00; }
        .log-icon.delete { background: #ffebee; color: #d32f2f; }
        .log-icon.default { background: #f5f5f5; color: #757575; }
        
        .log-content {
            flex: 1;
        }
        
        .log-action {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .log-description {
            color: #64748b;
            font-size: 13px;
            margin-top: 4px;
        }
        
        .log-meta {
            display: flex;
            gap: 16px;
            margin-top: 8px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .log-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }
        
        .filter-select, .filter-input {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
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
                    <h1><i class="fas fa-history"></i> Nhật ký hoạt động</h1>
                    <p>Theo dõi tất cả hoạt động trong hệ thống</p>
                </div>
                <div class="page-header-right">
                    <span class="badge badge-primary"><?php echo number_format($total_logs); ?> bản ghi</span>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Hành động</label>
                        <select name="action" class="filter-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action_filter === $act ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($act); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Người dùng</label>
                        <select name="user" class="filter-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['fullname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Từ ngày</label>
                        <input type="date" name="date_from" class="filter-input" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Đến ngày</label>
                        <input type="date" name="date_to" class="filter-input" value="<?php echo $date_to; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                    <?php if ($action_filter || $user_filter || $date_from || $date_to): ?>
                        <a href="logs.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-times"></i> Xóa lọc
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Logs List -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>Không có nhật ký</h3>
                            <p>Chưa có hoạt động nào được ghi lại.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $iconClass = 'default';
                            $icon = 'fa-circle';
                            if (strpos($log['action'], 'login') !== false) { $iconClass = 'login'; $icon = 'fa-sign-in-alt'; }
                            elseif (strpos($log['action'], 'create') !== false) { $iconClass = 'create'; $icon = 'fa-plus'; }
                            elseif (strpos($log['action'], 'update') !== false) { $iconClass = 'update'; $icon = 'fa-edit'; }
                            elseif (strpos($log['action'], 'delete') !== false) { $iconClass = 'delete'; $icon = 'fa-trash'; }
                        ?>
                        <div class="log-item">
                            <div class="log-icon <?php echo $iconClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="log-content">
                                <div class="log-action"><?php echo htmlspecialchars($log['action']); ?></div>
                                <div class="log-description"><?php echo htmlspecialchars($log['description'] ?? ''); ?></div>
                                <div class="log-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($log['fullname'] ?? 'Hệ thống'); ?></span>
                                    <span><i class="fas fa-globe"></i> <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo $log['created_at'] ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <div class="pagination">
                        <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>