<?php
// File: admin/includes/sidebar.php
$current_page = $current_page ?? '';
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>EDUSERVICE</span>
        </div>
        <span class="admin-badge">Admin</span>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-title">Tổng quan</span>
            <a href="index.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Quản lý</span>
            <a href="users.php" class="nav-item <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Người dùng</span>
            </a>
            <a href="classes.php" class="nav-item <?php echo $current_page === 'classes' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard"></i>
                <span>Lớp học</span>
            </a>
            <a href="content.php" class="nav-item <?php echo $current_page === 'content' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>Nội dung học liệu</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Phân tích</span>
            <a href="reports.php" class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Báo cáo</span>
            </a>
            <a href="analytics.php" class="nav-item <?php echo $current_page === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Phân tích</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Hệ thống</span>
            <a href="settings.php" class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Cấu hình</span>
            </a>
            <a href="logs.php" class="nav-item <?php echo $current_page === 'logs' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Nhật ký</span>
            </a>
            <a href="backup.php" class="nav-item <?php echo $current_page === 'backup' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>Sao lưu</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Truyền thông</span>
            <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span>Thông báo</span>
            </a>
            <a href="email.php" class="nav-item <?php echo $current_page === 'email' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Email</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Hỗ trợ</span>
            <a href="support.php" class="nav-item <?php echo $current_page === 'support' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i>
                <span>Help Desk</span>
            </a>
            <a href="knowledge-base.php" class="nav-item <?php echo $current_page === 'knowledge' ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i>
                <span>Knowledge Base</span>
            </a>
        </div>
        
        <div class="nav-section">
            <span class="nav-section-title">Dữ liệu</span>
            <a href="import.php" class="nav-item <?php echo $current_page === 'import' ? 'active' : ''; ?>">
                <i class="fas fa-file-import"></i>
                <span>Import</span>
            </a>
            <a href="export.php" class="nav-item <?php echo $current_page === 'export' ? 'active' : ''; ?>">
                <i class="fas fa-file-export"></i>
                <span>Export</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../saudn/trangchusaudn.php" class="nav-item">
            <i class="fas fa-arrow-left"></i>
            <span>Về trang học tập</span>
        </a>
    </div>
</aside>