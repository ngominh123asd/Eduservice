<?php
/**
 * File khởi tạo dữ liệu mẫu cho database
 * Truy cập: http://localhost/Eduservice/db/seed.php
 */

require_once __DIR__ . '/db_config.php';

try {
    // Tắt foreign key constraints tạm thời để xóa dữ liệu
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    // Xóa dữ liệu theo thứ tự (từ bảng con đến bảng cha)
    $pdo->exec("DELETE FROM activity_logs");
    $pdo->exec("DELETE FROM academic_products");
    $pdo->exec("DELETE FROM class_enrollments");
    $pdo->exec("DELETE FROM assignments");
    $pdo->exec("DELETE FROM lessons");
    $pdo->exec("DELETE FROM classes");
    $pdo->exec("DELETE FROM users WHERE role = 'admin'");
    
    // Bật lại foreign key constraints
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Thêm tài khoản admin mới với mật khẩu được hash đúng
    $admin_password = password_hash('admin', PASSWORD_DEFAULT);
    
    // Sử dụng INSERT đơn giản hơn, chỉ với các cột cần thiết
    $stmt = $pdo->prepare("
        INSERT INTO users (fullname, email, password, role, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        'Administrator',
        'admin@vnu.edu.vn',
        $admin_password,
        'admin',
        'active'
    ]);
    
    if ($result) {
        // Verify password ngay sau khi tạo
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute(['admin@vnu.edu.vn']);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $verification_test = password_verify('admin', $admin['password']);
        
        echo "<div style='padding: 20px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px; color: #2e7d32; font-family: Arial, sans-serif;'>";
        echo "<h2 style='margin-top: 0;'>✓ Khởi tạo dữ liệu thành công!</h2>";
        echo "<p><strong>Tài khoản Admin đã được tạo:</strong></p>";
        echo "<ul style='line-height: 1.8;'>";
        echo "<li><strong>ID:</strong> <code style='background: #fff; padding: 4px 8px; border-radius: 4px;'>#" . $admin['id'] . "</code></li>";
        echo "<li><strong>Email:</strong> <code style='background: #fff; padding: 4px 8px; border-radius: 4px; font-weight: 600;'>admin@vnu.edu.vn</code></li>";
        echo "<li><strong>Mật khẩu:</strong> <code style='background: #fff; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #d32f2f;'>admin</code></li>";
        echo "<li><strong>Vai trò:</strong> <code style='background: #fff; padding: 4px 8px; border-radius: 4px;'>Admin</code></li>";
        echo "</ul>";
        
        echo "<div style='margin-top: 20px; padding: 12px; background: " . ($verification_test ? '#e8f5e9' : '#ffebee') . "; border: 1px solid " . ($verification_test ? '#c8e6c9' : '#ffcdd2') . "; border-radius: 6px;'>";
        echo "<strong>🔐 Kiểm tra mật khẩu:</strong><br>";
        echo "<div style='margin-top: 8px;'>";
        echo "<table style='width: 100%; font-size: 12px; margin-top: 8px;'>";
        echo "<tr><td style='padding: 4px; width: 150px;'><strong>Password hash:</strong></td><td style='padding: 4px;'><code style='word-break: break-all; background: #fff; padding: 2px 4px; font-size: 10px;'>" . htmlspecialchars(substr($admin['password'], 0, 40)) . "...</code></td></tr>";
        echo "<tr><td style='padding: 4px;'><strong>Algorithm:</strong></td><td style='padding: 4px;'><code style='background: #fff; padding: 2px 4px;'>" . password_get_info($admin['password'])['algoName'] . "</code></td></tr>";
        echo "<tr><td style='padding: 4px;'><strong>Test login:</strong></td><td style='padding: 4px;'><strong style='color: " . ($verification_test ? '#2e7d32' : '#d32f2f') . ";'>" . ($verification_test ? '✓ PASS - Đăng nhập OK!' : '✗ FAIL - Có lỗi!') . "</strong></td></tr>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
        
        echo "<div style='margin-top: 20px; padding: 12px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;'>";
        echo "<strong>⚠️ Lưu ý quan trọng:</strong><br>";
        echo "<ul style='margin: 8px 0 0 20px; font-size: 14px; line-height: 1.8;'>";
        echo "<li>Database mới đã được tạo tại: <code>d:\\Eduservice\\db\\edservices.db</code></li>";
        echo "<li>Vui lòng thay đổi mật khẩu ngay sau khi đăng nhập lần đầu</li>";
        echo "<li>Mật khẩu mặc định <code style='background: #fff; padding: 2px 6px;'>admin</code> chỉ dùng để setup ban đầu</li>";
        echo "<li>File này chỉ dùng 1 lần, sau đó nên xóa để bảo mật</li>";
        echo "</ul>";
        echo "</div>";
        
        if ($verification_test) {
            echo "<p style='text-align: center; margin-top: 24px;'>";
            echo "<a href='../admin/login.php' style='display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #4CAF50, #388E3C); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3); transition: all 0.3s;' onmouseover='this.style.transform=\"translateY(-2px)\"; this.style.boxShadow=\"0 6px 20px rgba(76, 175, 80, 0.4)\"' onmouseout='this.style.transform=\"translateY(0)\"; this.style.boxShadow=\"0 4px 12px rgba(76, 175, 80, 0.3)\"'>";
            echo "🚀 Đi đến trang đăng nhập Admin";
            echo "</a>";
            echo "</p>";
        }
        echo "</div>";
    } else {
        throw new Exception("Không thể tạo tài khoản admin");
    }
    
} catch (PDOException $e) {
    echo "<div style='padding: 20px; background: #ffebee; border: 1px solid #ffcdd2; border-radius: 8px; color: #c62828; font-family: Arial, sans-serif;'>";
    echo "<h2 style='margin-top: 0;'>✗ Lỗi khởi tạo dữ liệu</h2>";
    echo "<p style='font-weight: 600; font-size: 16px;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<div style='margin-top: 20px; padding: 16px; background: #fff; border-radius: 6px; border-left: 4px solid #d32f2f;'>";
    echo "<strong style='font-size: 16px;'>💡 Cách khắc phục:</strong>";
    echo "<ol style='margin: 12px 0 0 20px; line-height: 2;'>";
    echo "<li><strong>Xóa file database cũ:</strong><br><code style='background: #f5f5f5; padding: 6px 10px; border-radius: 4px; display: inline-block; margin-top: 6px;'>d:\\Eduservice\\db\\edservices.db</code></li>";
    echo "<li><strong>Refresh lại trang này</strong> để tạo database mới</li>";
    echo "<li>Nếu vẫn lỗi, kiểm tra quyền ghi của thư mục <code>d:\\Eduservice\\db</code></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<details style='margin-top: 16px; padding: 12px; background: #fff; border-radius: 4px;'>";
    echo "<summary style='cursor: pointer; font-weight: 600; color: #d32f2f;'>📋 Chi tiết kỹ thuật (click để xem)</summary>";
    echo "<pre style='margin-top: 12px; padding: 12px; background: #f5f5f5; border-radius: 4px; overflow: auto; font-size: 11px; line-height: 1.5;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</details>";
    echo "</div>";
    exit();
} catch (Exception $e) {
    echo "<div style='padding: 20px; background: #ffebee; border: 1px solid #ffcdd2; border-radius: 8px; color: #c62828; font-family: Arial, sans-serif;'>";
    echo "<h2 style='margin-top: 0;'>✗ Lỗi</h2>";
    echo "<p style='font-weight: 600;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khởi tạo Database | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .container {
            max-width: 750px;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        ul, ol {
            margin-left: 20px;
        }
        
        table {
            border-collapse: collapse;
        }
        
        table td {
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Content được thêm bằng PHP echo ở trên -->
    </div>
</body>
</html>
