<?php
session_start();

// Hàm kiểm tra và chuyển hướng
function redirectUser() {
    // Kiểm tra xem người dùng đã đăng nhập chưa
    if (!isset($_SESSION['user']) || !isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Chưa đăng nhập -> chuyển đến trang chủ công khai
        header("Location: gioithieu/gioithieu.html");
        exit();
    }
    
    // Đã đăng nhập -> kiểm tra vai trò
    $role = $_SESSION['role'];
    
    if ($role === 'teacher') {
        // Là giáo viên -> chuyển đến trang giáo viên
        header("Location: giaovien/trangchu_giaovien.php");
        exit();
    } elseif ($role === 'student') {
        // Là học sinh -> chuyển đến trang học sinh
        header("Location: saudn/trangchusaudn.php");
        exit();
    } elseif ($role === 'admin') {
        // Là admin -> chuyển đến trang admin
        header("Location: admin/index.php");
        exit();
    } else {
        // Vai trò không xác định -> đăng xuất và chuyển về trang chủ
        session_destroy();
        header("Location: gioithieu/gioithieu.html");
        exit();
    }
}

// Thực hiện kiểm tra và chuyển hướng
redirectUser();
?>