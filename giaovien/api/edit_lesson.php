<?php
// File: giaovien/api/edit_lesson.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    // Handle GET request to fetch lesson data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $lesson_id = $_GET['id'] ?? null;
        $teacher_id = $_SESSION['user_id'];
        
        if (!$lesson_id) {
            echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT l.* 
            FROM lessons l
            JOIN chapters ch ON l.chapter_id = ch.id
            JOIN classes c ON ch.class_id = c.id
            WHERE l.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$lesson_id, $teacher_id]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lesson) {
            echo json_encode(['success' => false, 'message' => 'Lesson not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'lesson' => $lesson
        ]);
        exit();
    }
    
    // Handle POST request to update lesson
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $teacher_id = $_SESSION['user_id'];
        
        $lesson_id = $_POST['lesson_id'] ?? null;
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? null;
        $type = $_POST['type'] ?? 'theory';
        $order_index = $_POST['order_index'] ?? 1;
        $min_duration_minutes = $_POST['min_duration_minutes'] ?? 5;
        $max_score = $_POST['max_score'] ?? null;
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        
        if (!$lesson_id || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Lesson ID and title are required']);
            exit();
        }
        
        // Verify teacher owns the class that contains this lesson
        $stmt = $pdo->prepare("
            SELECT l.id, l.file_path
            FROM lessons l
            JOIN chapters ch ON l.chapter_id = ch.id
            JOIN classes c ON ch.class_id = c.id
            WHERE l.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$lesson_id, $teacher_id]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lesson) {
            echo json_encode(['success' => false, 'message' => 'Lesson not found']);
            exit();
        }
        
        $file_path = $lesson['file_path'];
        
        // Handle new file upload if provided
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old file
            if ($file_path) {
                $old_file = __DIR__ . '/../../' . $file_path;
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Upload new file
            $upload_dir = __DIR__ . '/../../uploads/lessons/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_path)) {
                $file_path = '/uploads/lessons/' . $file_name;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                exit();
            }
        }
        
        // Convert empty dates to null
        $start_date = !empty($start_date) ? $start_date : null;
        $end_date = !empty($end_date) ? $end_date : null;
        $max_score = !empty($max_score) ? $max_score : null;
        
        $stmt = $pdo->prepare("
            UPDATE lessons 
            SET title = ?, description = ?, type = ?, order_index = ?,
                file_path = ?, min_duration_minutes = ?, max_score = ?,
                start_date = ?, end_date = ?, updated_at = DATETIME('now')
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $type, $order_index,
            $file_path, $min_duration_minutes, $max_score,
            $start_date, $end_date, $lesson_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Lesson updated successfully'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>