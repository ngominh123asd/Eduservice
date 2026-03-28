<?php
/**
 * Session Configuration
 * Include file này ở đầu mọi file PHP cần dùng session
 */

// Chỉ cấu hình session nếu chưa active
if (session_status() === PHP_SESSION_NONE) {
    // Tạo thư mục sessions nếu chưa có (Đã comment để dùng chung với frontend)
    // $session_path = __DIR__ . '/../sessions';
    // if (!is_dir($session_path)) {
    //     mkdir($session_path, 0777, true);
    // }
    
    // Cấu hình session TRƯỚC KHI start
    // ini_set('session.save_handler', 'files');
    // ini_set('session.save_path', $session_path);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_maxlifetime', 3600); // 1 giờ
    ini_set('session.cookie_lifetime', 0); // Đến khi đóng browser
    ini_set('session.cookie_httponly', 1); // Bảo mật
    ini_set('session.use_only_cookies', 1); // Bảo mật
    ini_set('session.cookie_samesite', 'Lax'); // Bảo mật CSRF
    
    // Start session
    session_start();
}
?>
