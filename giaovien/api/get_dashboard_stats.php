<?php
// File: giaovien/api/get_dashboard_stats.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    
    // Get total classes and students
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM classes WHERE teacher_id = :teacher_id) as total_classes,
            (SELECT COUNT(DISTINCT e.student_id) 
             FROM enrollments e 
             JOIN classes c ON e.class_id = c.id 
             WHERE c.teacher_id = :teacher_id) as total_students,
            (SELECT COUNT(*) 
             FROM submissions s 
             JOIN assignments a ON s.assignment_id = a.id 
             JOIN classes c ON a.class_id = c.id 
             WHERE c.teacher_id = :teacher_id AND s.status = 'pending') as pending_submissions,
            (SELECT COUNT(*) 
             FROM lessons l 
             JOIN chapters ch ON l.chapter_id = ch.id 
             JOIN classes c ON ch.class_id = c.id 
             WHERE c.teacher_id = :teacher_id) as total_lessons
    ");
    
    $stmt->execute(['teacher_id' => $teacher_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_classes' => (int)$stats['total_classes'],
            'total_students' => (int)$stats['total_students'],
            'pending_submissions' => (int)$stats['pending_submissions'],
            'total_lessons' => (int)$stats['total_lessons']
        ]
    ]);

} catch(PDOException $e) {
    error_log('Database error in get_dashboard_stats: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>