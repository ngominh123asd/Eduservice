<?php
/**
 * File test session - Truy cập để kiểm tra session hoạt động
 * URL: http://localhost/Eduservice/admin/test-session.php
 */

// Cấu hình session
ini_set('session.save_handler', 'files');
ini_set('session.save_path', sys_get_temp_dir());
session_start();

// Thêm test data vào session
if (!isset($_SESSION['test_count'])) {
    $_SESSION['test_count'] = 0;
}
$_SESSION['test_count']++;
$_SESSION['test_time'] = date('Y-m-d H:i:s');

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Session | EDUSERVICE</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4CAF50;
            margin-top: 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 200px;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status.ok {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status.error {
            background: #ffebee;
            color: #c62828;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #388E3C;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Kiểm tra Session</h1>
        
        <table class="info-table">
            <tr>
                <td>Session Status:</td>
                <td>
                    <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
                        <span class="status ok">✓ ACTIVE</span>
                    <?php else: ?>
                        <span class="status error">✗ NOT ACTIVE</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Session ID:</td>
                <td><code><?php echo session_id(); ?></code></td>
            </tr>
            <tr>
                <td>Session Save Path:</td>
                <td><code><?php echo session_save_path(); ?></code></td>
            </tr>
            <tr>
                <td>Session Save Handler:</td>
                <td><code><?php echo ini_get('session.save_handler'); ?></code></td>
            </tr>
            <tr>
                <td>Test Counter:</td>
                <td><strong style="font-size: 20px; color: #4CAF50;"><?php echo $_SESSION['test_count']; ?></strong></td>
            </tr>
            <tr>
                <td>Last Update:</td>
                <td><code><?php echo $_SESSION['test_time']; ?></code></td>
            </tr>
        </table>
        
        <h3>📋 Session Data:</h3>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow: auto;"><?php print_r($_SESSION); ?></pre>
        
        <h3>✅ Hướng dẫn kiểm tra:</h3>
        <ol style="line-height: 1.8;">
            <li>Refresh trang này nhiều lần</li>
            <li>Kiểm tra <strong>Test Counter</strong> có tăng lên không</li>
            <li>Nếu counter tăng → Session hoạt động tốt</li>
            <li>Nếu counter luôn là 1 → Session không được lưu</li>
        </ol>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff3e0; border-radius: 4px;">
            <strong>💡 Nếu session không hoạt động:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>Kiểm tra quyền ghi của thư mục: <code><?php echo session_save_path(); ?></code></li>
                <li>Đảm bảo PHP có thể tạo file trong thư mục đó</li>
                <li>Kiểm tra file <code>php.ini</code> cấu hình session</li>
            </ul>
        </div>
        
        <a href="javascript:location.reload()" class="btn">🔄 Refresh để test</a>
        <a href="login.php" class="btn" style="background: #2196F3;">← Về trang đăng nhập</a>
    </div>
</body>
</html>