<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Phân tích";
$current_page = "analytics";

try {
    // Tổng quan
    $overview = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
            (SELECT COUNT(*) FROM users WHERE role = 'teacher') as total_teachers,
            (SELECT COUNT(*) FROM classes) as total_classes,
            (SELECT COUNT(*) FROM classes WHERE status = 'active') as active_classes,
            (SELECT COUNT(*) FROM class_enrollments) as total_enrollments
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Tăng trưởng người dùng 7 ngày qua
    $user_growth = $pdo->query("
        SELECT date(created_at) as date, COUNT(*) as count
        FROM users
        WHERE created_at >= date('now', '-7 days')
        GROUP BY date(created_at)
        ORDER BY date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Phân bố vai trò
    $role_distribution = $pdo->query("
        SELECT role, COUNT(*) as count
        FROM users
        GROUP BY role
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .metric-card .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 16px;
        }
        
        .metric-card .metric-icon.users { background: #e3f2fd; color: #1976d2; }
        .metric-card .metric-icon.students { background: #e8f5e9; color: #388e3c; }
        .metric-card .metric-icon.teachers { background: #fff3e0; color: #f57c00; }
        .metric-card .metric-icon.classes { background: #f3e5f5; color: #7b1fa2; }
        .metric-card .metric-icon.active { background: #e0f2f1; color: #00897b; }
        .metric-card .metric-icon.enrollments { background: #fce4ec; color: #c2185b; }
        
        .metric-card .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .metric-card .metric-label {
            font-size: 14px;
            color: #64748b;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        
        .chart-container h3 {
            margin: 0 0 20px 0;
            font-size: 16px;
            color: #1e293b;
        }
        
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .analytics-grid {
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
                    <h1><i class="fas fa-chart-line"></i> Phân tích</h1>
                    <p>Phân tích dữ liệu và xu hướng hệ thống</p>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php else: ?>
            
            <!-- Metrics -->
            <div class="analytics-grid">
                <div class="metric-card">
                    <div class="metric-icon users"><i class="fas fa-users"></i></div>
                    <div class="metric-value"><?php echo number_format($overview['total_users']); ?></div>
                    <div class="metric-label">Tổng người dùng</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon students"><i class="fas fa-user-graduate"></i></div>
                    <div class="metric-value"><?php echo number_format($overview['total_students']); ?></div>
                    <div class="metric-label">Sinh viên</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon teachers"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="metric-value"><?php echo number_format($overview['total_teachers']); ?></div>
                    <div class="metric-label">Giảng viên</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon classes"><i class="fas fa-chalkboard"></i></div>
                    <div class="metric-value"><?php echo number_format($overview['total_classes']); ?></div>
                    <div class="metric-label">Tổng lớp học</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon active"><i class="fas fa-check-circle"></i></div>
                    <div class="metric-value"><?php echo number_format($overview['active_classes']); ?></div>
                    <div class="metric-label">Lớp đang hoạt động</div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon enrollments"><i class="fas fa-user-plus"></i></div>
                    <div class="metric-value"><?php echo number_format($overview['total_enrollments']); ?></div>
                    <div class="metric-label">Lượt ghi danh</div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="charts-row">
                <div class="chart-container">
                    <h3><i class="fas fa-chart-area"></i> Tăng trưởng người dùng (7 ngày qua)</h3>
                    <canvas id="userGrowthChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Phân bố vai trò</h3>
                    <canvas id="roleDistributionChart"></canvas>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>

    <script>
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($user_growth ?? [], 'date')); ?>,
                datasets: [{
                    label: 'Người dùng mới',
                    data: <?php echo json_encode(array_column($user_growth ?? [], 'count')); ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
        
        // Role Distribution Chart
        const roleCtx = document.getElementById('roleDistributionChart').getContext('2d');
        const roleLabels = { student: 'Sinh viên', teacher: 'Giảng viên', admin: 'Admin' };
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(fn($r) => $roleLabels[$r['role']] ?? $r['role'], $role_distribution ?? [])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($role_distribution ?? [], 'count')); ?>,
                    backgroundColor: ['#4CAF50', '#FF9800', '#2196F3']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    </script>
</body>
</html>
