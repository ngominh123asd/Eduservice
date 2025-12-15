<?php
// File: giaovien/api/delete_lesson.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $lesson_id = $data['lesson_id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$lesson_id) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
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

    // Delete file if exists
    if ($lesson['file_path']) {
        $file_path = __DIR__ . '/../../' . $lesson['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete lesson (cascade will handle related progress records)
    $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Lesson deleted successfully'
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>