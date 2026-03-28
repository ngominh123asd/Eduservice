<?php
/**
 * Tệp khởi tạo cấu hình Session toàn cục.
 * Tệp này sẽ được tự động chạy trước mọi file PHP (thông qua auto_prepend_file),
 * đảm bảo thư mục lưu session luôn đồng nhất và không bị xóa giữa các request AJAX song song 
 * trên môi trường Serverless (chẳng hạn Wasmer WCGI).
 */

// Kiểm tra xem ứng dụng có đang chạy trên Wasmer Volume không (có thư mục /data)
if (is_dir('/data')) {
    $sess_dir = '/data/sessions';
} else {
    // Chạy trên môi trường local hoặc không có volume
    $sess_dir = __DIR__ . '/db/sessions';
}

if (!is_dir($sess_dir)) {
    @mkdir($sess_dir, 0777, true);
}

// Chỉnh lại đường dẫn lưu session vào thư mục dùng chung /db để liên tục qua các WebAssembly Worker
session_save_path($sess_dir);
