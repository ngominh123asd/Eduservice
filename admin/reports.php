<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Báo cáo";
$current_page = "reports";

try {
    // Thống kê người dùng theo tháng
    $user_stats = $pdo->query("
        SELECT 
            strftime('%Y-%m', created_at) as month,
            COUNT(*) as total,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers
        FROM users 
        WHERE created_at >= date('now', '-12 months')
        GROUP BY strftime('%Y-%m', created_at)
        ORDER BY month DESC
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Thống kê lớp học
    $class_stats = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM classes
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Top giảng viên có nhiều lớp nhất
    $top_teachers = $pdo->query("
        SELECT u.id, u.fullname, u.email, COUNT(c.id) as class_count
        FROM users u
        LEFT JOIN classes c ON u.id = c.teacher_id
        WHERE u.role = 'teacher'
        GROUP BY u.id
        ORDER BY class_count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Lớp học có nhiều học sinh nhất
    $top_classes = $pdo->query("
        SELECT c.id, c.class_name, c.code, u.fullname as teacher_name,
               COUNT(ce.id) as student_count
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id
        GROUP BY c.id
        ORDER BY student_count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Hoạt động gần đây
    $recent_activities = $pdo->query("
        SELECT al.*, u.fullname
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi: " . $e->getMessage();
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
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .report-card-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .report-card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .report-card-header h3 i {
            color: #4CAF50;
        }
        
        .report-card-body {
            padding: 20px;
        }
        
        .stats-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .stats-list li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .stats-list li:last-child {
            border-bottom: none;
        }
        
        .stats-list .label {
            color: #64748b;
        }
        
        .stats-list .value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content .action {
            font-weight: 500;
            color: #1e293b;
            font-size: 14px;
        }
        
        .activity-content .description {
            font-size: 13px;
            color: #64748b;
            margin-top: 2px;
        }
        
        .activity-content .time {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .ranking-item:last-child {
            border-bottom: none;
        }
        
        .ranking-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            color: #64748b;
        }
        
        .ranking-number.top-3 {
            background: #4CAF50;
            color: white;
        }
        
        .ranking-info {
            flex: 1;
        }
        
        .ranking-info .name {
            font-weight: 500;
            color: #1e293b;
        }
        
        .ranking-info .email {
            font-size: 12px;
            color: #94a3b8;
        }
        
        .ranking-count {
            font-weight: 600;
            color: #4CAF50;
        }
        
        @media (max-width: 1024px) {
            .reports-grid {
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
                    <h1><i class="fas fa-chart-bar"></i> Báo cáo</h1>
                    <p>Xem báo cáo và thống kê hệ thống</p>
                </div>
                <div class="page-header-right">
                    <button class="btn btn-outline" onclick="exportReport()">
                        <i class="fas fa-download"></i> Xuất báo cáo
                    </button>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php else: ?>
            
            <div class="reports-grid">
                <!-- Thống kê lớp học -->
                <div class="report-card">
                    <div class="report-card-header">
                        <h3><i class="fas fa-chalkboard"></i> Thống kê lớp học</h3>
                    </div>
                    <div class="report-card-body">
                        <ul class="stats-list">
                            <?php 
                            $status_labels = ['active' => 'Đang hoạt động', 'draft' => 'Bản nháp', 'archived' => 'Đã lưu trữ'];
                            foreach ($class_stats as $stat): 
                            ?>
                            <li>
                                <span class="label"><?php echo $status_labels[$stat['status']] ?? $stat['status']; ?></span>
                                <span class="value"><?php echo $stat['count']; ?> lớp</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Top giảng viên -->
                <div class="report-card">
                    <div class="report-card-header">
                        <h3><i class="fas fa-user-tie"></i> Top giảng viên</h3>
                    </div>
                    <div class="report-card-body">
                        <ul class="ranking-list">
                            <?php foreach ($top_teachers as $index => $teacher): ?>
                            <li class="ranking-item">
                                <span class="ranking-number <?php echo $index < 3 ? 'top-3' : ''; ?>"><?php echo $index + 1; ?></span>
                                <div class="ranking-info">
                                    <div class="name"><?php echo htmlspecialchars($teacher['fullname']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($teacher['email']); ?></div>
                                </div>
                                <span class="ranking-count"><?php echo $teacher['class_count']; ?> lớp</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Top lớp học -->
                <div class="report-card">
                    <div class="report-card-header">
                        <h3><i class="fas fa-users"></i> Lớp học đông nhất</h3>
                    </div>
                    <div class="report-card-body">
                        <ul class="ranking-list">
                            <?php foreach ($top_classes as $index => $class): ?>
                            <li class="ranking-item">
                                <span class="ranking-number <?php echo $index < 3 ? 'top-3' : ''; ?>"><?php echo $index + 1; ?></span>
                                <div class="ranking-info">
                                    <div class="name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($class['teacher_name'] ?? 'Chưa phân công'); ?></div>
                                </div>
                                <span class="ranking-count"><?php echo $class['student_count']; ?> học sinh</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Hoạt động gần đây -->
                <div class="report-card">
                    <div class="report-card-header">
                        <h3><i class="fas fa-history"></i> Hoạt động gần đây</h3>
                    </div>
                    <div class="report-card-body">
                        <div class="activity-list">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                    <div class="description"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                    <div class="time">
                                        <?php echo $activity['fullname'] ?? 'Hệ thống'; ?> • 
                                        <?php echo $activity['created_at'] ? date('d/m/Y H:i', strtotime($activity['created_at'])) : 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function exportReport() {
            Swal.fire({
                icon: 'info',
                title: 'Đang phát triển',
                text: 'Chức năng xuất báo cáo đang được phát triển'
            });
        }
    </script>
</body>
</html>
