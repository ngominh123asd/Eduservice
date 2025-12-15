<?php
// Đảm bảo session đã được khởi tạo (không cần start lại)
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
}
?>
<header class="admin-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Tìm kiếm..." id="global-search">
        </div>
    </div>
    
    <div class="header-right">
        <button class="header-btn" id="theme-toggle" title="Chế độ tối">
            <i class="fas fa-moon"></i>
        </button>
        
        <div class="header-dropdown">
            <button class="header-btn notification-btn" id="notification-btn">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </button>
            <div class="dropdown-menu notification-menu" id="notification-menu">
                <div class="dropdown-header">
                    <h4>Thông báo</h4>
                    <a href="#">Đánh dấu đã đọc</a>
                </div>
                <div class="dropdown-body">
                    <a href="#" class="notification-item unread">
                        <div class="notification-icon bg-primary">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="notification-content">
                            <p>5 người dùng mới đăng ký</p>
                            <span>2 phút trước</span>
                        </div>
                    </a>
                    <a href="#" class="notification-item unread">
                        <div class="notification-icon bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                            <p>Dung lượng lưu trữ sắp đầy</p>
                            <span>1 giờ trước</span>
                        </div>
                    </a>
                    <a href="#" class="notification-item">
                        <div class="notification-icon bg-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="notification-content">
                            <p>Backup hoàn thành</p>
                            <span>3 giờ trước</span>
                        </div>
                    </a>
                </div>
                <div class="dropdown-footer">
                    <a href="notifications.php">Xem tất cả thông báo</a>
                </div>
            </div>
        </div>
        
        <div class="header-dropdown">
            <button class="user-menu-btn" id="user-menu-btn">
                <div class="user-avatar">
                    <img src="../images/default-avatar.png" alt="Avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user']); ?>&background=4CAF50&color=fff'">
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']); ?></span>
                    <span class="user-role">Quản trị viên</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu user-menu" id="user-menu">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>Hồ sơ cá nhân</span>
                </a>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    <span>Cài đặt</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="../dangnhap/dangxuat.php" class="dropdown-item text-danger" onclick="return confirm('Bạn có chắc muốn đăng xuất?')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
// Sidebar Toggle
document.getElementById('sidebar-toggle').addEventListener('click', function() {
    document.getElementById('admin-sidebar').classList.toggle('collapsed');
    document.querySelector('.main-wrapper').classList.toggle('expanded');
});

// Dropdown Toggles
document.querySelectorAll('.header-dropdown').forEach(dropdown => {
    const btn = dropdown.querySelector('button');
    const menu = dropdown.querySelector('.dropdown-menu');
    
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        // Close other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(m => {
            if (m !== menu) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
});

// Theme Toggle
document.getElementById('theme-toggle').addEventListener('click', function() {
    document.body.classList.toggle('dark-mode');
    const icon = this.querySelector('i');
    icon.classList.toggle('fa-moon');
    icon.classList.toggle('fa-sun');
    localStorage.setItem('admin-theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
});

// Load saved theme
if (localStorage.getItem('admin-theme') === 'dark') {
    document.body.classList.add('dark-mode');
    document.querySelector('#theme-toggle i').classList.replace('fa-moon', 'fa-sun');
}
</script>
