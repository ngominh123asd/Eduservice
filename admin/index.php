<?php
// Include session configuration
require_once __DIR__ . '/config/session.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Debug: Log lý do redirect
    error_log("=== REDIRECT TO LOGIN ===");
    error_log("Session user: " . (isset($_SESSION['user']) ? $_SESSION['user'] : 'NOT SET'));
    error_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));
    error_log("Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET'));
    error_log("=========================");
    
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Dashboard";
$current_page = "dashboard";

// Lấy thống kê
try {
    // Tổng số người dùng theo vai trò
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Tổng số lớp học đang hoạt động
    $stmt = $pdo->query("SELECT COUNT(*) FROM classes WHERE status = 'active'");
    $active_classes = $stmt->fetchColumn();
    
    // Tổng số bài tập hiện hành
    $stmt = $pdo->query("SELECT COUNT(*) FROM assignments WHERE due_date >= CURDATE()");
    $active_assignments = $stmt->fetchColumn();
    
    // Thống kê lớp học theo tháng (6 tháng gần nhất)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM classes 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $classes_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Xu hướng đăng ký người dùng mới (30 ngày gần nhất)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $user_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hoạt động gần đây
    $stmt = $pdo->query("
        (SELECT 'user' as type, CONCAT('Người dùng mới: ', full_name) as description, created_at 
         FROM users ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'class' as type, CONCAT('Lớp học mới: ', class_name) as description, created_at 
         FROM classes ORDER BY created_at DESC LIMIT 5)
        ORDER BY created_at DESC LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi kết nối: " . $e->getMessage();
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
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Tổng quan hệ thống EDUSERVICE</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo array_sum($user_stats ?? [0]); ?></h3>
                        <p>Tổng người dùng</p>
                        <div class="stat-details">
                            <span><i class="fas fa-user-graduate"></i> <?php echo $user_stats['student'] ?? 0; ?> SV</span>
                            <span><i class="fas fa-chalkboard-teacher"></i> <?php echo $user_stats['teacher'] ?? 0; ?> GV</span>
                            <span><i class="fas fa-user-shield"></i> <?php echo $user_stats['admin'] ?? 0; ?> Admin</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_classes ?? 0; ?></h3>
                        <p>Lớp học đang hoạt động</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_assignments ?? 0; ?></h3>
                        <p>Bài tập hiện hành</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($user_registrations ?? []); ?></h3>
                        <p>Đăng ký mới (30 ngày)</p>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Phân bố người dùng</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="userDistributionChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> Lớp học theo tháng</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="classesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- User Registration Trend -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Xu hướng đăng ký người dùng</h3>
                </div>
                <div class="chart-body">
                    <canvas id="registrationTrendChart"></canvas>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="activity-card">
                <div class="activity-header">
                    <h3><i class="fas fa-history"></i> Hoạt động gần đây</h3>
                    <a href="logs.php" class="btn btn-sm btn-outline">Xem tất cả</a>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <i class="fas fa-<?php echo $activity['type'] === 'user' ? 'user-plus' : 'chalkboard'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <span class="activity-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-state">Chưa có hoạt động nào</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // User Distribution Chart
        const userDistCtx = document.getElementById('userDistributionChart').getContext('2d');
        new Chart(userDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sinh viên', 'Giảng viên', 'Quản trị viên'],
                datasets: [{
                    data: [
                        <?php echo $user_stats['student'] ?? 0; ?>,
                        <?php echo $user_stats['teacher'] ?? 0; ?>,
                        <?php echo $user_stats['admin'] ?? 0; ?>
                    ],
                    backgroundColor: ['#4CAF50', '#2196F3', '#FF9800'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Classes by Month Chart
        const classesCtx = document.getElementById('classesChart').getContext('2d');
        new Chart(classesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($classes_by_month ?? [], 'month')); ?>,
                datasets: [{
                    label: 'Số lớp học',
                    data: <?php echo json_encode(array_column($classes_by_month ?? [], 'count')); ?>,
                    backgroundColor: '#4CAF50',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Registration Trend Chart
        const trendCtx = document.getElementById('registrationTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($user_registrations ?? [], 'date')); ?>,
                datasets: [{
                    label: 'Đăng ký mới',
                    data: <?php echo json_encode(array_column($user_registrations ?? [], 'count')); ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
