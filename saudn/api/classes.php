<?php
session_start();
header('Content-Type: application/json');

// Set timezone to Vietnam
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    
    // Get all classes the student is enrolled in
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.class_name,
            c.code,
            c.description,
            u.fullname as teacher_name
        FROM classes c
        JOIN class_enrollments ce ON c.id = ce.class_id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE ce.user_id = ?
        ORDER BY c.class_name ASC
    ");
    $stmt->execute([$user_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each class, get lesson statistics
    foreach ($classes as &$class) {
        // Count total lessons in this class
        $stmt = $pdo->prepare("
            SELECT COUNT(l.id) as total
            FROM lessons l
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE ch.class_id = ?
        ");
        $stmt->execute([$class['id']]);
        $class['total_lessons'] = (int)$stmt->fetchColumn();
        
        // Count completed lessons for this user in this class
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT lp.lesson_id) as completed
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE ch.class_id = ? 
            AND lp.user_id = ? 
            AND lp.complete_time IS NOT NULL
        ");
        $stmt->execute([$class['id'], $user_id]);
        $class['completed_lessons'] = (int)$stmt->fetchColumn();
        
        // Calculate progress percentage
        if ($class['total_lessons'] > 0) {
            $class['progress'] = round(($class['completed_lessons'] / $class['total_lessons']) * 100, 1);
        } else {
            $class['progress'] = 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'classes' => $classes
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
