<?php
/**
 * File cấu hình kết nối database SQLite
 * Sử dụng file này trong tất cả các file PHP khác
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Đường dẫn đến file database SQLite
// Nếu đang chạy trên Wasmer Edge và có mount được volume vào /data
if (is_dir('/data')) {
    $db_path = '/data/edservices.db';
    
    // RẤT QUAN TRỌNG: Lần đầu tiên /data volume được mount, nó sẽ trống trơn.
    // Ta cần copy file database có sẵn từ source code sang volume này.
    if (!file_exists($db_path) && file_exists(__DIR__ . '/edservices.db')) {
        copy(__DIR__ . '/edservices.db', $db_path);
    }
} else {
    // Khởi chạy trên local
    $db_path = __DIR__ . '/edservices.db';
}

// Kiểm tra xem thư mục đích có tồn tại không, nếu không thì tạo (cho local)
$dir_path = dirname($db_path);
if (!is_dir($dir_path)) {
    mkdir($dir_path, 0777, true);
}

try {
    // Kết nối đến SQLite database
    $pdo = new PDO(
        'sqlite:' . $db_path,
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Bật foreign key constraints
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Tạo bảng nếu chưa tồn tại
    initializeTables($pdo);
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Lỗi kết nối cơ sở dữ liệu. Vui lòng liên hệ quản trị viên.");
}

/**
 * Khởi tạo các bảng cơ sở dữ liệu
 */
function initializeTables($pdo) {
    try {
        // Bảng users - SỬA: Không có cột last_login
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fullname TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'student' CHECK(role IN ('student', 'teacher', 'admin')),
                status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'suspended')),
                avatar TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Bảng classes - SỬA: Giữ nguyên cột code
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS classes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                class_name TEXT NOT NULL,
                description TEXT,
                teacher_id INTEGER,
                status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft', 'active', 'archived')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES users(id)
            )
        ");
        
        // Bảng class_enrollments
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS class_enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (class_id) REFERENCES classes(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE(class_id, user_id)
            )
        ");
        
        // Bảng assignments
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                due_date DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (class_id) REFERENCES classes(id)
            )
        ");
        
        // Bảng activity_logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                description TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Bảng lessons
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lessons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                lesson_type TEXT DEFAULT 'theory' CHECK(lesson_type IN ('theory', 'practice', 'test')),
                min_duration INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (class_id) REFERENCES classes(id)
            )
        ");
        
        // Bảng academic_products (sản phẩm học thuật)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS academic_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                class_id INTEGER,
                title TEXT NOT NULL,
                description TEXT,
                content TEXT,
                status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft', 'submitted', 'reviewed')),
                score REAL,
                feedback TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (class_id) REFERENCES classes(id)
            )
        ");
        
    } catch (PDOException $e) {
        error_log("Error initializing tables: " . $e->getMessage());
        throw $e;
    }
}
?>