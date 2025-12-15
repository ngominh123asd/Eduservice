<?php
function getMenu()
{
    // Check if user is logged in using user_id (more reliable)
    $isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['user']);

    if ($isLoggedIn) {
        // Get user info - ƯU TIÊN session keys từ xulydangnhap.php
        $fullname = $_SESSION['user'] ?? $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';
        $role = $_SESSION['role'] ?? 'student';
        $avatar = $_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($fullname) . '&background=4CAF50&color=fff';

        // Debug logging
        error_log("=== MENU DEBUG ===");
        error_log("Session user: " . ($_SESSION['user'] ?? 'NOT SET'));
        error_log("Session fullname: " . ($_SESSION['fullname'] ?? 'NOT SET'));
        error_log("Session name: " . ($_SESSION['name'] ?? 'NOT SET'));
        error_log("Final fullname: " . $fullname);
        error_log("Role: " . $role);
        error_log("==================");

        // Determine home page based on role
        switch ($role) {
            case 'teacher':
                $homePage = '/giaovien/trangchu_giaovien.php';
                break;
            default:
                $homePage = '/saudn/trangchusaudn.php';
                break;
        }
        
        // Determine help page based on role
        if ($role === 'teacher') {
            $helpPage = '/huongdan/huongdan_giaovien.php';
        } else {
            $helpPage = '/huongdan/huongdan_hocsinh.php';
        }

        // Teacher/Student menu
        $profileLink = ($role === 'teacher') ? '/giaovien/caidat.php' : '/saudn/hoso.php';
        $settingsLink = ($role === 'teacher') ? '/giaovien/caidat.php' : '/saudn/caidat.php';
        return '
            <li class="auth-menu">
                <a href="' . $homePage . '">Trang chủ</a>
            </li>
            <li class="auth-menu">
                <a href="/saudn/congdong.php">Cộng đồng</a>
            </li>
            <li class="auth-menu">
                <a href="' . $helpPage . '">Hướng dẫn sử dụng</a>
            </li>
            <li class="auth-menu user-dropdown">
                <a href="#" class="user-menu-trigger">
                    <img src="' . htmlspecialchars($avatar) . '" alt="Avatar" class="user-avatar" onerror="this.src=\'https://ui-avatars.com/api/?name=' . urlencode($fullname) . '&background=4CAF50&color=fff\'">
                    <span>' . htmlspecialchars($fullname) . '</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="dropdown-menu">
                    <li><a href="' . $profileLink . '"><i class="fas fa-user"></i> Hồ sơ</a></li>
                    <li><a href="' . $settingsLink . '"><i class="fas fa-cog"></i> Cài đặt</a></li>
                    <li><a href="#" onclick="xacNhanDangXuat()"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                </ul>
            </li>
        ';
    } else {
        return '
            <li class="guest-menu">
                <a href="/trangchu/trangchu.html">TRANG CHỦ</a>
            </li>
            <li class="guest-menu">
                <a href="/gioithieu/gioithieu.html">GIỚI THIỆU</a>
            </li>
            <li class="guest-menu"><a href="/huongdan/huongdan_khach.html">HƯỚNG DẪN</a></li>
            <li class="guest-menu login-link"><a href="/dangnhap/dangnhap.php">ĐĂNG NHẬP</a></li>
        ';
    }
}
?>
