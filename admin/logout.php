<?php
session_start();

// Ghi log đăng xuất
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../db/db_config.php';
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'admin_logout', 'Admin logout', ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (PDOException $e) {
        // Bỏ qua lỗi log
    }
}

// Xóa session
session_unset();
session_destroy();

// Chuyển về trang đăng nhập admin
header("Location: login.php");
exit();
