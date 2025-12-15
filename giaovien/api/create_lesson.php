<?php
// File: giaovien/api/create_lesson.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// Start output buffering to catch any stray output
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    
    $chapter_id = $_POST['chapter_id'] ?? null;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? null;
    $type = $_POST['type'] ?? 'theory';
    $order_index = $_POST['order_index'] ?? 1;
    $min_duration_minutes = $_POST['min_duration_minutes'] ?? 5;
    $max_score = $_POST['max_score'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    if (!$chapter_id || empty($title)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Chapter ID and title are required']);
        exit();
    }
    
    // Verify teacher owns the class that contains this chapter
    $stmt = $pdo->prepare("
        SELECT ch.id, c.id as class_id
        FROM chapters ch
        JOIN classes c ON ch.class_id = c.id
        WHERE ch.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$chapter_id, $teacher_id]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Chapter not found']);
        exit();
    }
    
    // Handle file upload - SUPPORT PDF AND IMAGES UP TO 100MB
    $file_path = null;
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pdf_file'];
        
        // Define allowed file types
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/pjpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
        
        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) {
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'Chỉ chấp nhận file PDF hoặc ảnh (JPG, PNG, GIF, WebP)'
            ]);
            exit();
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_mimes)) {
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'Loại file không hợp lệ'
            ]);
            exit();
        }
        
        // Validate file size (100MB = 100 * 1024 * 1024 bytes)
        $max_file_size = 100 * 1024 * 1024; // 100MB
        if ($file['size'] > $max_file_size) {
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'File không được vượt quá 100MB. File hiện tại: ' . round($file['size'] / 1024 / 1024, 2) . 'MB'
            ]);
            exit();
        }
        
        // Create upload directory if not exists
        $upload_dir = __DIR__ . '/../../uploads/lessons/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Không thể tạo thư mục upload'
                ]);
                exit();
            }
        }
        
        // Generate unique filename
        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $file_name;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'Không thể tải file lên server'
            ]);
            exit();
        }
        
        // Store relative path for database
        $file_path = '/uploads/lessons/' . $file_name;
        
    } else {
        // Handle upload errors
        if (isset($_FILES['pdf_file'])) {
            $upload_error = $_FILES['pdf_file']['error'];
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File vượt quá kích thước cho phép trong php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File vượt quá kích thước cho phép',
                UPLOAD_ERR_PARTIAL => 'File chỉ được tải lên một phần',
                UPLOAD_ERR_NO_FILE => 'Không có file nào được tải lên',
                UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm',
                UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file vào đĩa',
                UPLOAD_ERR_EXTENSION => 'Một extension PHP đã dừng việc tải file'
            ];
            
            if (isset($error_messages[$upload_error])) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => $error_messages[$upload_error]
                ]);
                exit();
            }
        }
        
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Vui lòng chọn file để tải lên'
        ]);
        exit();
    }
    
    // Convert empty dates to null
    $start_date = !empty($start_date) ? $start_date : null;
    $end_date = !empty($end_date) ? $end_date : null;
    $max_score = !empty($max_score) ? $max_score : null;
    
    // Insert lesson into database
    $stmt = $pdo->prepare("
        INSERT INTO lessons (
            chapter_id, title, description, type, order_index, 
            file_path, min_duration_minutes, max_score, 
            start_date, end_date, created_at
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATETIME('now'))
    ");
    
    $stmt->execute([
        $chapter_id, 
        $title, 
        $description, 
        $type, 
        $order_index,
        $file_path, 
        $min_duration_minutes, 
        $max_score,
        $start_date, 
        $end_date
    ]);
    
    $lesson_id = $pdo->lastInsertId();
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Thêm bài học thành công',
        'lesson_id' => $lesson_id,
        'file_path' => $file_path
    ]);
    
} catch(PDOException $e) {
    ob_end_clean();
    error_log('Create lesson error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi database: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    ob_end_clean();
    error_log('Create lesson error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>