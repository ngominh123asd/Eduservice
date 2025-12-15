<?php
session_start();
header('Content-Type: application/json');

// Set timezone to Vietnam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    $class_id = $_GET['id'] ?? null;
    
    if (!$class_id) {
        throw new Exception('Class ID required');
    }
    
    // Verify enrollment
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM class_enrollments 
        WHERE class_id = ? AND user_id = ?
    ");
    $stmt->execute([$class_id, $user_id]);
    
    if (!$stmt->fetchColumn()) {
        throw new Exception('Not enrolled in this class');
    }
    
    // Get class info
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.class_name,
            c.description,
            u.fullname as teacher_name
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        throw new Exception('Class not found');
    }
    
    // Get all chapters first
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title as chapter_name,
            order_index as chapter_order
        FROM chapters
        WHERE class_id = ?
        ORDER BY order_index ASC
    ");
    $stmt->execute([$class_id]);
    $chaptersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chapters = [];
    
    // For each chapter, get lessons with completion status
    foreach ($chaptersData as $chapterRow) {
        $chapter = [
            'id' => $chapterRow['id'],
            'chapter_name' => $chapterRow['chapter_name'],
            'chapter_order' => $chapterRow['chapter_order'],
            'lessons' => []
        ];
        
        // Get lessons for this chapter
        $stmt = $pdo->prepare("
            SELECT 
                l.id,
                l.title as lesson_name,
                l.type as lesson_type,
                l.order_index as lesson_order,
                lp.complete_time
            FROM lessons l
            LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
            WHERE l.chapter_id = ?
            ORDER BY l.order_index ASC
        ");
        $stmt->execute([$user_id, $chapterRow['id']]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($lessons as $lesson) {
            $chapter['lessons'][] = [
                'id' => $lesson['id'],
                'lesson_name' => $lesson['lesson_name'],
                'lesson_type' => $lesson['lesson_type'],
                'lesson_order' => $lesson['lesson_order'],
                'is_completed' => !empty($lesson['complete_time']) ? 1 : 0
            ];
        }
        
        $chapters[] = $chapter;
    }
    
    echo json_encode([
        'success' => true,
        'class' => $class,
        'chapters' => $chapters
    ], JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    error_log("Database error in class-detail.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error in class-detail.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>