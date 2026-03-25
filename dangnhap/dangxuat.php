<?php
session_start();
session_unset(); // Xóa tất cả biến session
session_destroy(); // Hủy toàn bộ session


// Chuyển về trang trang chủ (html hoặc php)
header("Location: ../gioithieu/gioithieu.html");
exit();
?>