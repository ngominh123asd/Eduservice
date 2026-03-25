<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Help Desk";
$current_page = "support";

$success_message = '';
$error_message = '';

// Khởi tạo bảng tickets nếu chưa có
try {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_number TEXT UNIQUE NOT NULL,
            subject TEXT NOT NULL,
            description TEXT NOT NULL,
            category TEXT DEFAULT 'general' CHECK(category IN ('general', 'technical', 'account', 'billing', 'feature', 'other')),
            priority TEXT DEFAULT 'medium' CHECK(priority IN ('low', 'medium', 'high', 'urgent')),
            status TEXT DEFAULT 'open' CHECK(status IN ('open', 'in_progress', 'waiting', 'resolved', 'closed')),
            user_id INTEGER NOT NULL,
            assigned_to INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (assigned_to) REFERENCES users(id)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            is_internal INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES support_tickets(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    $pdo->exec('PRAGMA foreign_keys = ON');
    
} catch (PDOException $e) {
    error_log("Support init error: " . $e->getMessage());
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_ticket') {
            $subject = trim($_POST['subject'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'general';
            $priority = $_POST['priority'] ?? 'medium';
            $user_id = (int)($_POST['user_id'] ?? $_SESSION['user_id']);
            
            if (empty($subject) || empty($description)) {
                $error_message = "Vui lòng nhập đầy đủ tiêu đề và mô tả";
            } else {
                // Tạo ticket number
                $ticket_number = 'TK' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO support_tickets (ticket_number, subject, description, category, priority, user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([$ticket_number, $subject, $description, $category, $priority, $user_id]);
                
                $success_message = "Đã tạo ticket #$ticket_number thành công";
            }
            
        } elseif ($action === 'update_ticket') {
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $priority = $_POST['priority'] ?? '';
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            
            if ($ticket_id > 0) {
                $resolved_at = ($status === 'resolved' || $status === 'closed') ? "datetime('now')" : 'NULL';
                
                $stmt = $pdo->prepare("
                    UPDATE support_tickets 
                    SET status = ?, priority = ?, assigned_to = ?, updated_at = datetime('now'),
                        resolved_at = CASE WHEN ? IN ('resolved', 'closed') THEN datetime('now') ELSE resolved_at END
                    WHERE id = ?
                ");
                $stmt->execute([$status, $priority, $assigned_to, $status, $ticket_id]);
                
                $success_message = "Đã cập nhật ticket";
            }
            
        } elseif ($action === 'reply_ticket') {
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $is_internal = isset($_POST['is_internal']) ? 1 : 0;
            
            if ($ticket_id > 0 && !empty($message)) {
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal, created_at)
                    VALUES (?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([$ticket_id, $_SESSION['user_id'], $message, $is_internal]);
                
                // Cập nhật trạng thái ticket
                $pdo->prepare("UPDATE support_tickets SET status = 'in_progress', updated_at = datetime('now') WHERE id = ? AND status = 'open'")->execute([$ticket_id]);
                
                $success_message = "Đã gửi phản hồi";
            }
            
        } elseif ($action === 'delete_ticket') {
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            if ($ticket_id > 0) {
                $pdo->prepare("DELETE FROM ticket_replies WHERE ticket_id = ?")->execute([$ticket_id]);
                $pdo->prepare("DELETE FROM support_tickets WHERE id = ?")->execute([$ticket_id]);
                $success_message = "Đã xóa ticket";
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Lấy danh sách tickets
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

try {
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "t.status = ?";
        $params[] = $status_filter;
    }
    if ($priority_filter) {
        $where_conditions[] = "t.priority = ?";
        $params[] = $priority_filter;
    }
    if ($category_filter) {
        $where_conditions[] = "t.category = ?";
        $params[] = $category_filter;
    }
    if ($search) {
        $where_conditions[] = "(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.fullname LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $total = $pdo->prepare("SELECT COUNT(*) FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id $where_clause");
    $total->execute($params);
    $total_tickets = $total->fetchColumn();
    $total_pages = ceil($total_tickets / $limit);
    
    $sql = "
        SELECT t.*, 
               u.fullname as user_name, u.email as user_email,
               a.fullname as assigned_name,
               (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = t.id) as reply_count
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN priority = 'urgent' AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as urgent
        FROM support_tickets
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Lấy danh sách admin/staff để gán ticket
    $staff = $pdo->query("SELECT id, fullname FROM users WHERE role IN ('admin', 'teacher') AND status = 'active' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
    
    // Lấy danh sách users để tạo ticket
    $users = $pdo->query("SELECT id, fullname, email FROM users WHERE status = 'active' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $tickets = [];
    $stats = [];
    $staff = [];
    $users = [];
}

// Labels
$status_labels = ['open' => 'Mở', 'in_progress' => 'Đang xử lý', 'waiting' => 'Chờ phản hồi', 'resolved' => 'Đã giải quyết', 'closed' => 'Đã đóng'];
$priority_labels = ['low' => 'Thấp', 'medium' => 'Trung bình', 'high' => 'Cao', 'urgent' => 'Khẩn cấp'];
$category_labels = ['general' => 'Chung', 'technical' => 'Kỹ thuật', 'account' => 'Tài khoản', 'billing' => 'Thanh toán', 'feature' => 'Tính năng', 'other' => 'Khác'];
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
        .support-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
        }
        
        .stat-icon.total { background: #e3f2fd; color: #1976d2; }
        .stat-icon.open { background: #fff3e0; color: #f57c00; }
        .stat-icon.progress { background: #e3f2fd; color: #1976d2; }
        .stat-icon.waiting { background: #fce4ec; color: #c2185b; }
        .stat-icon.resolved { background: #e8f5e9; color: #388e3c; }
        .stat-icon.urgent { background: #ffebee; color: #d32f2f; }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-card .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        /* Tickets Table */
        .tickets-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .tickets-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tickets-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ticket-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        
        .ticket-row:hover {
            background: #f8fafc;
        }
        
        .ticket-priority {
            width: 4px;
            height: 48px;
            border-radius: 2px;
            flex-shrink: 0;
        }
        
        .priority-urgent { background: #d32f2f; }
        .priority-high { background: #f57c00; }
        .priority-medium { background: #1976d2; }
        .priority-low { background: #757575; }
        
        .ticket-main {
            flex: 1;
            min-width: 0;
        }
        
        .ticket-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ticket-number {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        .ticket-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .ticket-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .ticket-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-open { background: #fff3e0; color: #f57c00; }
        .status-in_progress { background: #e3f2fd; color: #1976d2; }
        .status-waiting { background: #fce4ec; color: #c2185b; }
        .status-resolved { background: #e8f5e9; color: #388e3c; }
        .status-closed { background: #f5f5f5; color: #757575; }
        
        .ticket-category {
            padding: 4px 10px;
            background: #f1f5f9;
            border-radius: 4px;
            font-size: 11px;
            color: #64748b;
        }
        
        .ticket-actions {
            display: flex;
            gap: 8px;
        }
        
        .ticket-actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .btn-view { background: #e3f2fd; color: #1976d2; }
        .btn-view:hover { background: #bbdefb; }
        .btn-reply { background: #e8f5e9; color: #388e3c; }
        .btn-reply:hover { background: #c8e6c9; }
        .btn-delete { background: #ffebee; color: #d32f2f; }
        .btn-delete:hover { background: #ffcdd2; }
        
        /* Filters */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .filters-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-wrapper {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        
        .search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .filter-select {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            min-width: 140px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-lg {
            max-width: 800px;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header h3 i {
            color: #4CAF50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #94a3b8;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Form */
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
        
        .checkbox-label input {
            width: 18px;
            height: 18px;
            accent-color: #4CAF50;
        }
        
        /* Ticket Detail */
        .ticket-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .ticket-detail-title h4 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #1e293b;
        }
        
        .ticket-detail-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .ticket-detail-badges {
            display: flex;
            gap: 8px;
        }
        
        .ticket-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .info-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .info-item label {
            font-size: 12px;
            color: #64748b;
            display: block;
            margin-bottom: 4px;
        }
        
        .info-item span {
            font-weight: 600;
            color: #1e293b;
        }
        
        .ticket-description {
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        
        .ticket-description h5 {
            margin: 0 0 12px;
            font-size: 14px;
            color: #64748b;
        }
        
        .ticket-description p {
            margin: 0;
            line-height: 1.6;
            color: #1e293b;
        }
        
        /* Replies */
        .replies-section h5 {
            margin: 0 0 16px;
            font-size: 14px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .reply-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 3px solid #4CAF50;
        }
        
        .reply-item.internal {
            background: #fff3e0;
            border-left-color: #f57c00;
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .reply-author {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .reply-time {
            font-size: 12px;
            color: #94a3b8;
        }
        
        .reply-content {
            color: #475569;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .internal-badge {
            font-size: 10px;
            padding: 2px 8px;
            background: #f57c00;
            color: white;
            border-radius: 10px;
            margin-left: 8px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
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
        }
        
        .pagination-btn:hover { background: #f1f5f9; }
        .pagination-btn.active { background: #4CAF50; color: white; border-color: #4CAF50; }
        
        @media (max-width: 1200px) {
            .support-stats { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .support-stats { grid-template-columns: repeat(2, 1fr); }
            .filters-form { flex-direction: column; align-items: stretch; }
            .form-row { grid-template-columns: 1fr; }
            .ticket-info-grid { grid-template-columns: 1fr; }
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
                    <h1><i class="fas fa-headset"></i> Help Desk</h1>
                    <p>Quản lý ticket hỗ trợ người dùng</p>
                </div>
                <div class="page-header-right">
                    <button class="btn btn-primary" onclick="openModal('create-ticket-modal')">
                        <i class="fas fa-plus"></i> Tạo Ticket
                    </button>
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
            <div class="support-stats">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-ticket-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Tổng Ticket</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon open"><i class="fas fa-folder-open"></i></div>
                    <div class="stat-number"><?php echo $stats['open_count'] ?? 0; ?></div>
                    <div class="stat-label">Đang mở</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon progress"><i class="fas fa-spinner"></i></div>
                    <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                    <div class="stat-label">Đang xử lý</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon waiting"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['waiting'] ?? 0; ?></div>
                    <div class="stat-label">Chờ phản hồi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon resolved"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['resolved'] ?? 0; ?></div>
                    <div class="stat-label">Đã giải quyết</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon urgent"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-number"><?php echo $stats['urgent'] ?? 0; ?></div>
                    <div class="stat-label">Khẩn cấp</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="search-input" placeholder="Tìm kiếm ticket..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach ($status_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="priority" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả độ ưu tiên</option>
                        <?php foreach ($priority_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $priority_filter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="category" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($category_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $category_filter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 16px;">
                        <i class="fas fa-filter"></i>
                    </button>
                </form>
            </div>
            
            <!-- Tickets List -->
            <div class="tickets-card">
                <div class="tickets-header">
                    <h3><i class="fas fa-list"></i> Danh sách Ticket</h3>
                    <span class="badge badge-primary"><?php echo $total_tickets; ?> ticket</span>
                </div>
                
                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <i class="fas fa-ticket-alt"></i>
                        <h3>Không có ticket nào</h3>
                        <p>Chưa có ticket hỗ trợ nào được tạo</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-row">
                            <div class="ticket-priority priority-<?php echo $ticket['priority']; ?>"></div>
                            <div class="ticket-main">
                                <div class="ticket-title">
                                    <span class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                    <?php if ($ticket['reply_count'] > 0): ?>
                                        <span class="badge badge-info" style="font-size: 10px;"><i class="fas fa-comment"></i> <?php echo $ticket['reply_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($ticket['user_name'] ?? 'N/A'); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                    <?php if ($ticket['assigned_name']): ?>
                                        <span><i class="fas fa-user-check"></i> <?php echo htmlspecialchars($ticket['assigned_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="ticket-category"><?php echo $category_labels[$ticket['category']] ?? $ticket['category']; ?></span>
                            <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                <?php echo $status_labels[$ticket['status']] ?? $ticket['status']; ?>
                            </span>
                            <div class="ticket-actions">
                                <button class="btn-view" onclick="viewTicket(<?php echo $ticket['id']; ?>)" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-reply" onclick="replyTicket(<?php echo $ticket['id']; ?>)" title="Phản hồi">
                                    <i class="fas fa-reply"></i>
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa ticket này?')">
                                    <input type="hidden" name="action" value="delete_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <button type="submit" class="btn-delete" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Create Ticket Modal -->
    <div class="modal-overlay" id="create-ticket-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Tạo Ticket mới</h3>
                <button class="modal-close" onclick="closeModal('create-ticket-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_ticket">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Người gửi</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Chọn người dùng</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tiêu đề <span style="color: #f44336;">*</span></label>
                        <input type="text" name="subject" class="form-input" required placeholder="Nhập tiêu đề ticket">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Danh mục</label>
                            <select name="category" class="form-select">
                                <?php foreach ($category_labels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Độ ưu tiên</label>
                            <select name="priority" class="form-select">
                                <?php foreach ($priority_labels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $key === 'medium' ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mô tả <span style="color: #f44336;">*</span></label>
                        <textarea name="description" class="form-textarea" required placeholder="Mô tả chi tiết vấn đề..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-ticket-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tạo Ticket</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Ticket Modal -->
    <div class="modal-overlay" id="view-ticket-modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-ticket-alt"></i> Chi tiết Ticket</h3>
                <button class="modal-close" onclick="closeModal('view-ticket-modal')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="ticket-detail-content">
                <!-- Content loaded via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('view-ticket-modal')">Đóng</button>
            </div>
        </div>
    </div>
    
    <!-- Reply Ticket Modal -->
    <div class="modal-overlay" id="reply-ticket-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-reply"></i> Phản hồi Ticket</h3>
                <button class="modal-close" onclick="closeModal('reply-ticket-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reply_ticket">
                <input type="hidden" name="ticket_id" id="reply-ticket-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nội dung phản hồi <span style="color: #f44336;">*</span></label>
                        <textarea name="message" class="form-textarea" required placeholder="Nhập nội dung phản hồi..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_internal">
                            <span>Ghi chú nội bộ (không hiển thị cho người dùng)</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reply-ticket-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Gửi phản hồi</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const tickets = <?php echo json_encode($tickets); ?>;
        const statusLabels = <?php echo json_encode($status_labels); ?>;
        const priorityLabels = <?php echo json_encode($priority_labels); ?>;
        const categoryLabels = <?php echo json_encode($category_labels); ?>;
        
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function viewTicket(ticketId) {
            const ticket = tickets.find(t => t.id == ticketId);
            if (!ticket) return;
            
            const content = document.getElementById('ticket-detail-content');
            content.innerHTML = `
                <div class="ticket-detail-header">
                    <div class="ticket-detail-title">
                        <h4>${ticket.ticket_number} - ${ticket.subject}</h4>
                        <div class="ticket-detail-meta">
                            <span class="ticket-category">${categoryLabels[ticket.category] || ticket.category}</span>
                        </div>
                    </div>
                    <div class="ticket-detail-badges">
                        <span class="ticket-status status-${ticket.status}">${statusLabels[ticket.status] || ticket.status}</span>
                    </div>
                </div>
                <div class="ticket-info-grid">
                    <div class="info-item">
                        <label>Người gửi</label>
                        <span>${ticket.user_name || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <span>${ticket.user_email || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <label>Độ ưu tiên</label>
                        <span>${priorityLabels[ticket.priority] || ticket.priority}</span>
                    </div>
                    <div class="info-item">
                        <label>Người xử lý</label>
                        <span>${ticket.assigned_name || 'Chưa phân công'}</span>
                    </div>
                    <div class="info-item">
                        <label>Ngày tạo</label>
                        <span>${new Date(ticket.created_at).toLocaleString('vi-VN')}</span>
                    </div>
                    <div class="info-item">
                        <label>Cập nhật</label>
                        <span>${new Date(ticket.updated_at).toLocaleString('vi-VN')}</span>
                    </div>
                </div>
                <div class="ticket-description">
                    <h5>Mô tả</h5>
                    <p>${ticket.description.replace(/\n/g, '<br>')}</p>
                </div>
            `;
            
            openModal('view-ticket-modal');
        }
        
        function replyTicket(ticketId) {
            document.getElementById('reply-ticket-id').value = ticketId;
            openModal('reply-ticket-modal');
        }
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>