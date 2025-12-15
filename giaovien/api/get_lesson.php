<?php
// File: giaovien/api/get_lesson.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $lesson_id = $_GET['id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$lesson_id) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
        exit();
    }
    
    // Get lesson details with chapter and class info
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            ch.title as chapter_title,
            ch.class_id,
            c.class_name,
            c.teacher_id,
            COUNT(DISTINCT ce.user_id) as enrolled_students,
            COUNT(DISTINCT CASE WHEN lp.complete_time IS NOT NULL THEN lp.user_id END) as completed_students
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        JOIN classes c ON ch.class_id = c.id
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id
        WHERE l.id = ? AND c.teacher_id = ?
        GROUP BY l.id
    ");
    $stmt->execute([$lesson_id, $teacher_id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit();
    }
    
    // Get lesson statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT lp.user_id) as total_students,
            COUNT(DISTINCT CASE WHEN lp.complete_time = 1 THEN lp.user_id END) as completed_count,
            COUNT(DISTINCT CASE WHEN lp.submission_text IS NOT NULL THEN lp.user_id END) as submission_count,
            COUNT(DISTINCT CASE WHEN lp.score IS NOT NULL THEN lp.user_id END) as graded_count,
            ROUND(AVG(lp.score), 1) as avg_score,
            ROUND(AVG(lp.time_spent), 1) as avg_time_spent
        FROM class_enrollments ce
        LEFT JOIN lesson_progress lp ON ce.user_id = lp.user_id AND lp.lesson_id = ?
        WHERE ce.class_id = ?
    ");
    $stmt->execute([$lesson_id, $lesson['class_id']]);
    $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student progress for this lesson
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.fullname as student_name,
            u.email as student_email,
            lp.start_time,
            lp.complete_time,
            lp.complete_time,
            lp.time_spent,
            lp.score,
            lp.submission_text,
            lp.file_path,
            lp.graded_at,
            lp.feedback,
            CASE 
                WHEN lp.id IS NULL THEN 'not_started'
                WHEN lp.complete_time = 1 THEN 'completed'
                ELSE 'in_progress'
            END as status
        FROM class_enrollments ce
        JOIN users u ON ce.user_id = u.id
        LEFT JOIN lesson_progress lp ON u.id = lp.user_id AND lp.lesson_id = ?
        WHERE ce.class_id = ?
        ORDER BY u.fullname ASC
    ");
    $stmt->execute([$lesson_id, $lesson['class_id']]);
    $student_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'lesson' => $lesson,
        'statistics' => $statistics,
        'student_progress' => $student_progress
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>